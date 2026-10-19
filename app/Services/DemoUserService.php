<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Support\DemoAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DemoUserService
{
    public function __construct(private readonly AuditService $audit, private readonly DemoAccess $demoAccess) {}

    /**
     * Creates only explicitly enabled local demonstration accounts.
     * Existing demo accounts retain their password, role, state and personal data.
     *
     * @return array<int, array{username: string, email: string, role: string, status: string}>
     */
    public function provision(string $initial = 'm'): array
    {
        $this->demoAccess->assertAllowed();
        $initial = strtolower(trim($initial));
        if (! preg_match('/\A[a-z]{1,4}\z/', $initial)) {
            throw ValidationException::withMessages(['initial' => 'La inicial debe contener de una a cuatro letras ASCII, sin espacios internos.']);
        }

        return DB::transaction(function () use ($initial): array {
            $this->demoAccess->assertAllowed();
            DB::select('select pg_advisory_xact_lock(?)', [AccessService::ADMINISTRATION_LOCK]);

            $definitions = [
                ['role' => 'admin', 'prefix' => 'admin', 'name' => 'Administrador', 'password' => 'admin'],
                ['role' => 'seller', 'prefix' => 'vendedor', 'name' => 'Vendedor', 'password' => 'vendedor'],
                ['role' => 'supervisor', 'prefix' => 'supervisor', 'name' => 'Supervisor', 'password' => 'supervisor'],
                ['role' => 'warehouse', 'prefix' => 'bodega', 'name' => 'Bodega', 'password' => 'bodega'],
            ];
            $roles = Role::whereIn('code', array_column($definitions, 'role'))->lockForUpdate()->get()->keyBy('code');
            foreach ($definitions as $definition) {
                if (! ($role = $roles->get($definition['role'])) || ! $role->active) {
                    throw ValidationException::withMessages(['roles' => 'Los cuatro roles de demostración deben existir y estar activos. Ejecute SystemSeeder y revise sus estados.']);
                }
            }
            $admin = $roles->get('admin');
            if (array_diff(AccessService::RECOVERY_PERMISSIONS, $admin->permissions()->pluck('code')->all()) !== []) {
                throw ValidationException::withMessages(['roles' => 'El rol Administrador debe conservar los permisos de recuperación de usuarios y roles.']);
            }

            $existingUsers = [];
            foreach ($definitions as $definition) {
                $username = $definition['prefix'].$initial;
                $email = $username.'@gmail.com';
                if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    throw ValidationException::withMessages(['email' => 'No se pudo construir un correo válido para la demostración.']);
                }
                $existing = User::whereRaw('lower(btrim(email)) = ?', [$email])->lockForUpdate()->first();
                if ($existing && (! (bool) $existing->is_demo || $existing->role_id !== $roles->get($definition['role'])->id)) {
                    throw ValidationException::withMessages(['email' => 'Una cuenta prevista para demostración ya existe como cuenta normal o con un rol diferente. No se modificó ninguna cuenta.']);
                }
                if (! $existing && User::where('username', $username)->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['username' => 'Un nombre de usuario previsto para demostración ya pertenece a otra cuenta. No se modificó ninguna cuenta.']);
                }
                $existingUsers[$email] = $existing;
            }

            $results = [];
            foreach ($definitions as $definition) {
                $username = $definition['prefix'].$initial;
                $email = $username.'@gmail.com';
                if ($existingUsers[$email]) {
                    $results[] = ['username' => $existingUsers[$email]->username, 'email' => $email, 'role' => $definition['role'], 'status' => 'existing'];

                    continue;
                }
                $user = new User;
                $user->forceFill([
                    'name' => $definition['name'].' '.strtoupper($initial).' (demostración)',
                    'username' => $username,
                    'email' => $email,
                    'password' => $definition['password'],
                    'role_id' => $roles->get($definition['role'])->id,
                    'active' => true,
                    'is_demo' => true,
                ])->save();
                $this->audit->record(null, 'users.demo_created', $user, [], $user->only(['name', 'username', 'email', 'role_id', 'active', 'is_demo']), 'Cuenta creada para demostración local explícitamente habilitada.');
                $results[] = ['username' => $user->username, 'email' => $email, 'role' => $definition['role'], 'status' => 'created'];
            }

            return $results;
        });
    }
}
