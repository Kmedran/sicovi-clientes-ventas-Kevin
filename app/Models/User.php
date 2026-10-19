<?php

namespace App\Models;

use App\Support\DemoAccess;
use App\Support\Usernames;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'username', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'active' => 'boolean', 'is_demo' => 'boolean', 'last_login_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (! array_key_exists('username', $user->getAttributes()) || $user->username === null) {
                $base = Usernames::fromEmail((string) $user->email);
                $user->username = Usernames::available($base, fn (string $candidate): bool => $user->newQuery()->where('username', $candidate)->exists());
            }
        });
    }

    protected function username(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : Usernames::normalize($value));
    }

    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value) => mb_strtolower(trim($value)));
    }

    protected function name(): Attribute
    {
        return Attribute::make(set: fn (string $value) => preg_replace('/\s+/u', ' ', trim($value)));
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function permissionOverrides(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')->withPivot('allowed');
    }

    /** Current assignments remain visible when editing an inactive account. */
    public function effectivePermissionCodes(): array
    {
        return $this->effectivePermissionsQuery()->orderBy('code')->pluck('code')->all();
    }

    /** Read the database on every decision so revocations affect existing sessions. */
    public function hasPermission(string $code): bool
    {
        $current = static::query()->whereKey($this->getKey())->first(['id', 'is_demo']);
        if (! $current || ($current->is_demo && ! app(DemoAccess::class)->allowed())) {
            return false;
        }

        return $this->effectivePermissionsQuery(true, $current->is_demo)->where('code', $code)->exists();
    }

    /** An explicit denial overrides the role; an explicit grant adds to it. */
    private function effectivePermissionsQuery(bool $requireActive = false, bool $allowDemo = true): Builder
    {
        return Permission::query()->whereExists(function (QueryBuilder $users) use ($requireActive, $allowDemo): void {
            $users->selectRaw('1')->from('users')->join('roles', 'roles.id', '=', 'users.role_id')->where('users.id', $this->getKey());
            if ($requireActive) {
                $users->where('users.active', true)->where('roles.active', true);
                if (! $allowDemo) {
                    $users->where('users.is_demo', false);
                }
            }
            $users->where(function (QueryBuilder $effective): void {
                $effective->where(function (QueryBuilder $inherited): void {
                    $inherited->whereExists(function (QueryBuilder $defaults): void {
                        $defaults->selectRaw('1')->from('role_permissions')->whereColumn('role_permissions.role_id', 'roles.id')->whereColumn('role_permissions.permission_id', 'permissions.id');
                    })->whereNotExists(function (QueryBuilder $denied): void {
                        $denied->selectRaw('1')->from('user_permissions')->whereColumn('user_permissions.user_id', 'users.id')->whereColumn('user_permissions.permission_id', 'permissions.id')->where('user_permissions.allowed', false);
                    });
                })->orWhereExists(function (QueryBuilder $allowed): void {
                    $allowed->selectRaw('1')->from('user_permissions')->whereColumn('user_permissions.user_id', 'users.id')->whereColumn('user_permissions.permission_id', 'permissions.id')->where('user_permissions.allowed', true);
                });
            });
        });
    }
}
