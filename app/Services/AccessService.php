<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\DemoAccess;
use App\Support\Usernames;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccessService
{
    /** Shared with SystemSeeder: serialize every change to administration access. */
    public const ADMINISTRATION_LOCK = 74126001;

    public const RECOVERY_PERMISSIONS = [
        'users.view', 'users.create', 'users.update', 'users.deactivate',
        'users.reset_password', 'roles.view', 'roles.manage',
    ];

    public function __construct(private readonly AuditService $audit) {}

    public function createUser(User $actor, array $data): User
    {
        return DB::transaction(function () use ($actor, $data): User {
            $this->lockAdministration();
            Gate::forUser($actor)->authorize('users.create');
            Gate::forUser($actor)->authorize('roles.manage');
            $data = $this->normalizeUsername($data);
            $role = Role::whereKey($data['role_id'])->where('active', true)->first();
            if (! $role) {
                throw ValidationException::withMessages(['role_id' => 'Seleccione un rol activo.']);
            }
            $user = new User;
            $user->forceFill(Arr::only($data, ['name', 'username', 'email', 'password']) + ['role_id' => $role->id, 'active' => true])->save();
            if ($this->permissionSelectionSubmitted($actor, $data)) {
                $this->applyPermissionSelection($user, $data['permissions'] ?? []);
            }
            $this->ensureAdministratorRemains();
            $this->audit->record($actor, 'users.created', $user, [], $this->userValues($user));

            return $user;
        });
    }

    public function updateUser(User $actor, User $target, array $data): User
    {
        return DB::transaction(function () use ($actor, $target, $data): User {
            $this->lockAdministration();
            Gate::forUser($actor)->authorize('users.update');
            $permissionsSubmitted = $this->permissionSelectionSubmitted($actor, $data);
            $target = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $data = $this->normalizeUsername($data, $target->id);
            $before = $this->userValues($target);
            $attributes = Arr::only($data, ['name', 'username', 'email']);
            if (array_key_exists('role_id', $data)) {
                Gate::forUser($actor)->authorize('roles.manage');
                $role = Role::findOrFail($data['role_id']);
                if (! $role->active && $role->id !== $target->role_id) {
                    throw ValidationException::withMessages(['role_id' => 'Seleccione un rol activo.']);
                }
                $attributes['role_id'] = $role->id;
            }
            $target->forceFill($attributes)->save();
            if ($permissionsSubmitted) {
                $this->applyPermissionSelection($target, $data['permissions'] ?? []);
            } elseif ($before['role_id'] !== $target->role_id) {
                // A newly assigned role starts from its defaults unless selections were explicit.
                DB::table('user_permissions')->where('user_id', $target->id)->delete();
            }
            $this->ensureAdministratorRemains();
            if ($before['role_id'] !== $target->role_id) {
                $this->revokeSessions($target);
            }
            $this->audit->record($actor, 'users.updated', $target, $before, $this->userValues($target));

            return $target;
        });
    }

    public function setUserActive(User $actor, User $target, bool $active): void
    {
        DB::transaction(function () use ($actor, $target, $active): void {
            $this->lockAdministration();
            Gate::forUser($actor)->authorize('users.deactivate');
            $target = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $before = $this->userValues($target);
            if ($active && ! $target->role()->where('active', true)->exists()) {
                throw ValidationException::withMessages(['active' => 'Active primero el rol de esta cuenta.']);
            }
            $target->forceFill(['active' => $active])->save();
            $this->ensureAdministratorRemains();
            $this->revokeSessions($target);
            $this->audit->record($actor, $active ? 'users.activated' : 'users.deactivated', $target, $before, $this->userValues($target));
        });
    }

    public function resetPassword(User $actor, User $target, string $password, string $reason): void
    {
        DB::transaction(function () use ($actor, $target, $password, $reason): void {
            $this->lockAdministration();
            Gate::forUser($actor)->authorize('users.reset_password');
            $target = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $target->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            $this->revokeSessions($target);
            $this->audit->record($actor, 'users.password_reset', $target, [], [], $reason);
        });
    }

    public function changePassword(User $actor, string $currentPassword, string $password): void
    {
        DB::transaction(function () use ($actor, $currentPassword, $password): void {
            $this->lockAdministration();
            $target = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            if (! $target->active || ! $target->role()->where('active', true)->exists() || ! Hash::check($currentPassword, $target->password)) {
                throw ValidationException::withMessages(['current_password' => 'La contraseña actual no es válida.']);
            }
            $target->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            $this->revokeSessions($target);
            $this->audit->record($actor, 'users.password_changed', $target);
        });
    }

    public function updateRole(User $actor, Role $role, array $data): void
    {
        DB::transaction(function () use ($actor, $role, $data): void {
            $this->lockAdministration();
            Gate::forUser($actor)->authorize('roles.manage');
            $role = Role::whereKey($role->id)->lockForUpdate()->firstOrFail();
            $before = $role->only(['code', 'name', 'description', 'active']);
            $before['permission_codes'] = $role->permissions()->orderBy('code')->pluck('code')->all();
            $role->fill(Arr::only($data, ['name', 'description', 'active']))->save();
            $codes = array_values(array_unique($data['permissions']));
            $permissions = Permission::whereIn('code', $codes)->pluck('id');
            if ($permissions->count() !== count($codes)) {
                throw ValidationException::withMessages(['permissions' => 'Hay permisos desconocidos.']);
            }
            $role->permissions()->sync($permissions);
            $this->ensureAdministratorRemains();
            if (! $role->active) {
                DB::table('sessions')->whereIn('user_id', User::where('role_id', $role->id)->select('id'))->delete();
            }
            $after = $role->only(['code', 'name', 'description', 'active']);
            $after['permission_codes'] = $role->permissions()->orderBy('code')->pluck('code')->all();
            $this->audit->record($actor, 'roles.updated', $role, $before, $after);
        });
    }

    /** Called only by the local interactive command; never accepts an HTTP actor. */
    public function createAdministrator(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $this->lockAdministration();
            $data = $this->normalizeUsername($data);
            if (User::where('email', $data['email'])->exists()) {
                throw ValidationException::withMessages(['email' => 'El correo ya tiene una cuenta; no se sobrescribió.']);
            }
            $role = Role::where('code', 'admin')->where('active', true)->first();
            if (! $role || count(array_diff(self::RECOVERY_PERMISSIONS, $role->permissions()->pluck('code')->all())) > 0) {
                throw ValidationException::withMessages(['role' => 'Ejecute SystemSeeder y compruebe el rol Administrador antes de crear la cuenta.']);
            }
            $user = new User;
            $user->forceFill(Arr::only($data, ['name', 'username', 'email', 'password']) + ['role_id' => $role->id, 'active' => true])->save();
            $this->audit->record(null, 'users.administrator_created', $user, [], $this->userValues($user), 'Creación local mediante comando interactivo.');

            return $user;
        });
    }

    private function lockAdministration(): void
    {
        DB::select('select pg_advisory_xact_lock(?)', [self::ADMINISTRATION_LOCK]);
    }

    private function ensureAdministratorRemains(): void
    {
        $role = Role::where('code', 'admin')->where('active', true)->first();
        $hasPermissions = $role && count(array_diff(self::RECOVERY_PERMISSIONS, $role->permissions()->pluck('code')->all())) === 0;
        $recoverable = false;
        if ($hasPermissions) {
            foreach (User::where('role_id', $role->id)->where('active', true)->get() as $candidate) {
                if ($candidate->is_demo && ! app(DemoAccess::class)->allowed()) {
                    continue;
                }
                if (array_diff(self::RECOVERY_PERMISSIONS, $candidate->effectivePermissionCodes()) === []) {
                    $recoverable = true;
                    break;
                }
            }
        }
        if (! $recoverable) {
            throw ValidationException::withMessages(['administration' => 'Debe conservar al menos un administrador activo con permisos para gestionar usuarios y roles.']);
        }
    }

    private function permissionSelectionSubmitted(User $actor, array $data): bool
    {
        if (array_key_exists('permissions_present', $data) || array_key_exists('permissions', $data)) {
            Gate::forUser($actor)->authorize('roles.manage');
        }
        $submitted = in_array($data['permissions_present'] ?? false, [true, 1, '1'], true);
        if (! $submitted && ! empty($data['permissions'])) {
            throw ValidationException::withMessages(['permissions' => 'Confirme el formulario de permisos para cambiar las asignaciones del usuario.']);
        }

        return $submitted;
    }

    private function applyPermissionSelection(User $user, mixed $selected): void
    {
        if (! is_array($selected) || count(array_filter($selected, 'is_string')) !== count($selected)) {
            throw ValidationException::withMessages(['permissions' => 'La selección de permisos debe ser una lista de códigos válidos.']);
        }
        $codes = array_values(array_unique($selected));
        $selectedIds = Permission::whereIn('code', $codes)->pluck('id')->all();
        if (count($selectedIds) !== count($codes)) {
            throw ValidationException::withMessages(['permissions' => 'Hay permisos desconocidos.']);
        }
        $defaultIds = DB::table('role_permissions')->where('role_id', $user->role_id)->pluck('permission_id')->all();
        $rows = [];
        foreach (array_diff($selectedIds, $defaultIds) as $id) {
            $rows[] = ['user_id' => $user->id, 'permission_id' => $id, 'allowed' => true];
        }
        foreach (array_diff($defaultIds, $selectedIds) as $id) {
            $rows[] = ['user_id' => $user->id, 'permission_id' => $id, 'allowed' => false];
        }
        DB::table('user_permissions')->where('user_id', $user->id)->delete();
        if ($rows !== []) {
            DB::table('user_permissions')->insert($rows);
        }
    }

    private function revokeSessions(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }

    private function userValues(User $user): array
    {
        return $user->only(['name', 'username', 'email', 'role_id', 'active']) + ['permission_codes' => $user->effectivePermissionCodes()];
    }

    private function normalizeUsername(array $data, ?int $ignoreId = null): array
    {
        if (! array_key_exists('username', $data)) {
            return $data;
        }
        if (! is_string($data['username'])) {
            throw ValidationException::withMessages(['username' => 'Ingrese un nombre de usuario válido.']);
        }
        $username = Usernames::normalize($data['username']);
        if (! Usernames::isValid($username)) {
            throw ValidationException::withMessages(['username' => 'Use entre 3 y 40 caracteres: letras minúsculas, números, punto, guion o guion bajo; comience con una letra o número.']);
        }
        if (User::where('username', $username)->when($ignoreId !== null, fn ($query) => $query->where('id', '<>', $ignoreId))->exists()) {
            throw ValidationException::withMessages(['username' => 'Ese nombre de usuario ya está registrado.']);
        }
        $data['username'] = $username;

        return $data;
    }
}
