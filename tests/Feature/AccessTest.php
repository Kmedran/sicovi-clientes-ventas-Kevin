<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessService;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use Tests\DatabaseTestCase;

class AccessTest extends DatabaseTestCase
{
    private User $admin;

    private const PASSWORD = 'SeguraLocal!2026';

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = app(AccessService::class)->createAdministrator([
            'name' => 'Administrador de pruebas', 'email' => 'admin@example.test', 'password' => self::PASSWORD,
        ]);
    }

    public function test_guest_is_redirected_and_valid_login_normalizes_username(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->post(route('login.store'), ['username' => '  ADMIN ', 'password' => self::PASSWORD])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->admin);
        $this->assertNotNull($this->admin->fresh()->last_login_at);
    }

    public function test_invalid_and_inactive_accounts_get_the_same_login_error(): void
    {
        $this->post(route('login.store'), ['username' => 'missing', 'password' => self::PASSWORD])
            ->assertSessionHasErrors(['username' => 'No se pudo iniciar sesión con esas credenciales.']);
        $this->admin->forceFill(['active' => false])->save();
        $this->post(route('login.store'), ['username' => $this->admin->username, 'password' => self::PASSWORD])
            ->assertSessionHasErrors(['username' => 'No se pudo iniciar sesión con esas credenciales.']);
        $this->assertGuest();
    }

    public function test_malformed_array_inputs_are_validation_errors_instead_of_server_errors(): void
    {
        $this->post(route('login.store'), ['username' => ['unexpected'], 'password' => self::PASSWORD])->assertSessionHasErrors('username');
        $this->get(route('login'))->assertOk();
        $this->actingAs($this->admin)->post(route('users.store'), array_replace($this->newUserData(), ['name' => ['unexpected'], 'email' => ['unexpected']]))
            ->assertSessionHasErrors(['name', 'email']);
        $this->put(route('users.update', $this->admin), ['name' => ['unexpected'], 'email' => ['unexpected'], 'role_id' => $this->admin->role_id])
            ->assertSessionHasErrors(['name', 'email']);
        $this->put(route('roles.update', $this->admin->role), ['name' => ['unexpected'], 'active' => true, 'permissions' => []])
            ->assertSessionHasErrors('name');
        $this->put(route('account.password'), ['current_password' => ['unexpected'], 'password' => 'NuevaClave!2026', 'password_confirmation' => 'NuevaClave!2026'])
            ->assertSessionHasErrors('current_password');
    }

    public function test_repeated_failed_login_is_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), ['username' => 'limited', 'password' => 'incorrecta'])
                ->assertSessionHasErrors('username');
        }
        $this->post(route('login.store'), ['username' => 'limited', 'password' => self::PASSWORD])
            ->assertSessionHas('errors', fn ($errors) => str_contains($errors->first('username'), 'Demasiados intentos'));
        $this->assertGuest();
    }

    public function test_logout_removes_authenticated_access(): void
    {
        $this->actingAs($this->admin)->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_deactivated_user_with_existing_session_is_logged_out(): void
    {
        $user = $this->userForRole('seller');
        $this->actingAs($user);
        User::whereKey($user->id)->update(['active' => false]);
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_inactive_role_cannot_login_or_continue_a_session(): void
    {
        $user = $this->userForRole('seller');
        $user->role()->update(['active' => false]);
        $this->post(route('login.store'), ['username' => $user->username, 'password' => self::PASSWORD])->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_read_only_roles_cannot_write_by_direct_requests(): void
    {
        foreach (['seller', 'viewer'] as $code) {
            $this->flushSession();
            $this->actingAs($this->userForRole($code));
            $this->get(route('users.index'))->assertForbidden();
            $this->post(route('users.store'), [])->assertForbidden();
            $this->put(route('roles.update', Role::where('code', 'seller')->firstOrFail()), [])->assertForbidden();
            $this->put(route('settings.update'), [])->assertForbidden();
            $this->post(route('products.store'), ['reference_cost' => '1.00'])->assertForbidden();
            $this->post(route('catalogs.store', 'brands'), [])->assertForbidden();
        }
    }

    public function test_creating_user_normalizes_email_hashes_password_and_excludes_secrets_from_audit(): void
    {
        $this->actingAs($this->admin)->post(route('users.store'), $this->newUserData())
            ->assertRedirect(route('users.index'));
        $user = User::where('email', 'nuevo@example.test')->firstOrFail();
        $this->assertSame('Nuevo Usuario', $user->name);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->assertNotSame(self::PASSWORD, $user->password);
        $this->assertArrayNotHasKey('password', $user->toArray());
        $event = AuditEvent::where('action', 'users.created')->where('entity_id', $user->id)->firstOrFail();
        $this->assertSame($this->admin->id, $event->actor_id);
        $this->assertArrayNotHasKey('password', $event->after_values);
        $this->assertStringNotContainsString(self::PASSWORD, $event->toJson());
    }

    public function test_normalized_duplicate_email_is_rejected_without_creating_a_user(): void
    {
        $data = $this->newUserData();
        $data['email'] = ' ADMIN@EXAMPLE.TEST ';
        $this->actingAs($this->admin)->post(route('users.store'), $data)->assertSessionHasErrors('email');
        $this->assertSame(1, User::count());
    }

    public function test_user_editor_cannot_inject_role_or_active_status(): void
    {
        $role = Role::create(['code' => 'test_editor', 'name' => 'Editor de prueba', 'active' => true]);
        $role->permissions()->attach(Permission::where('code', 'users.update')->value('id'));
        $editor = $this->userForRole('test_editor');
        $target = $this->userForRole('seller');
        $this->actingAs($editor)->put(route('users.update', $target), [
            'name' => 'Intento de privilegios', 'email' => $target->email, 'role_id' => $this->admin->role_id, 'active' => false,
        ])->assertSessionHasErrors(['role_id', 'active']);
        $this->assertSame($target->role_id, $target->fresh()->role_id);
        $this->assertTrue($target->fresh()->active);
        $this->actingAs($editor)->put(route('users.update', $target), ['name' => 'Nombre autorizado', 'username' => $target->username, 'email' => $target->email])
            ->assertRedirect(route('users.index'));
        $this->assertSame('Nombre autorizado', $target->fresh()->name);
    }

    public function test_last_administrator_cannot_be_deactivated_or_demoted(): void
    {
        $this->actingAs($this->admin)->patch(route('users.deactivate', $this->admin), ['active' => false])
            ->assertSessionHasErrors('administration');
        $this->put(route('users.update', $this->admin), [
            'name' => $this->admin->name, 'username' => $this->admin->username, 'email' => $this->admin->email, 'role_id' => Role::where('code', 'seller')->value('id'),
        ])->assertSessionHasErrors('administration');
        $this->assertTrue($this->admin->fresh()->active);
        $this->assertSame('admin', $this->admin->fresh()->role->code);
    }

    public function test_administrator_role_must_keep_recovery_permissions_and_active_status(): void
    {
        $role = $this->admin->role;
        $codes = $role->permissions()->pluck('code')->reject(fn ($code) => $code === 'roles.manage')->values()->all();
        $this->actingAs($this->admin)->put(route('roles.update', $role), [
            'name' => $role->name, 'active' => true, 'permissions' => $codes,
        ])->assertSessionHasErrors('administration');
        $this->assertTrue($this->admin->hasPermission('roles.manage'));
        $this->put(route('roles.update', $role), [
            'name' => $role->name, 'active' => false, 'permissions' => $role->permissions()->pluck('code')->all(),
        ])->assertSessionHasErrors('administration');
        $this->assertTrue($role->fresh()->active);
    }

    public function test_one_of_two_administrators_can_be_deactivated_but_not_the_remaining_one(): void
    {
        $second = $this->userForRole('admin');
        $access = app(AccessService::class);
        $access->setUserActive($second, $this->admin, false);
        try {
            $access->setUserActive($second, $second, false);
            $this->fail('Se permitió desactivar al administrador restante.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('administration', $exception->errors());
        }
        $this->assertTrue($second->fresh()->active);
        $this->assertFalse($this->admin->fresh()->active);
    }

    public function test_permissions_are_not_cached_on_an_existing_user_instance(): void
    {
        $user = $this->userForRole('viewer');
        $this->assertTrue($user->hasPermission('products.view'));
        $user->role->permissions()->detach(Permission::where('code', 'products.view')->value('id'));
        $this->assertFalse($user->hasPermission('products.view'));
        $this->actingAs($user)->get(route('products.index'))->assertForbidden();
    }

    public function test_admin_password_reset_revokes_database_sessions_and_records_no_password(): void
    {
        $user = $this->userForRole('seller');
        $this->insertSession($user);
        $this->actingAs($this->admin)->put(route('users.reset-password', $user), [
            'password' => 'NuevaClave!2026', 'password_confirmation' => 'NuevaClave!2026', 'reason' => 'Solicitud presencial del usuario.',
        ])->assertRedirect(route('users.index'));
        $this->assertTrue(Hash::check('NuevaClave!2026', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $event = AuditEvent::where('action', 'users.password_reset')->firstOrFail();
        $this->assertSame([], $event->after_values);
        $this->assertStringNotContainsString('NuevaClave!2026', $event->toJson());
    }

    public function test_personal_password_change_requires_current_password_and_logs_out(): void
    {
        $this->insertSession($this->admin);
        $data = ['current_password' => 'incorrecta', 'password' => 'NuevaClave!2026', 'password_confirmation' => 'NuevaClave!2026'];
        $this->actingAs($this->admin)->put(route('account.password'), $data)->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check(self::PASSWORD, $this->admin->fresh()->password));
        $data['current_password'] = self::PASSWORD;
        $this->put(route('account.password'), $data)->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertTrue(Hash::check('NuevaClave!2026', $this->admin->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['user_id' => $this->admin->id]);
    }

    public function test_failed_mandatory_audit_rolls_back_user_modification(): void
    {
        $audit = Mockery::mock(AuditService::class);
        $audit->shouldReceive('record')->once()->andThrow(new RuntimeException('Fallo controlado de auditoría.'));
        $access = new AccessService($audit);
        try {
            $access->updateUser($this->admin, $this->admin, ['name' => 'No debe persistir', 'email' => $this->admin->email]);
            $this->fail('La auditoría fallida debía revertir la transacción.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo controlado de auditoría.', $exception->getMessage());
        }
        $this->assertSame('Administrador de pruebas', $this->admin->fresh()->name);
    }

    public function test_interactive_command_creates_a_hashed_account_and_refuses_duplicates(): void
    {
        $this->artisan('sicovi:crear-administrador')
            ->expectsQuestion('Nombre completo', 'Cuenta local de prueba')
            ->expectsQuestion('Nombre de usuario', 'comando')
            ->expectsQuestion('Correo electrónico', 'comando@example.test')
            ->expectsQuestion('Contraseña (12 o más caracteres, mayúscula, minúscula, número y símbolo)', self::PASSWORD)
            ->expectsQuestion('Repita la contraseña', self::PASSWORD)
            ->assertSuccessful();
        $user = User::where('email', 'comando@example.test')->firstOrFail();
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->assertSame('admin', $user->role->code);
        $hash = $user->password;
        $this->artisan('sicovi:crear-administrador')
            ->expectsQuestion('Nombre completo', 'Nombre que no debe sobrescribir')
            ->expectsQuestion('Nombre de usuario', 'comando')
            ->expectsQuestion('Correo electrónico', 'COMANDO@example.test')
            ->assertFailed();
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertSame('Cuenta local de prueba', $user->fresh()->name);
    }

    private function userForRole(string $roleCode): User
    {
        $user = new User;
        $user->forceFill([
            'name' => 'Usuario '.$roleCode, 'email' => $roleCode.'-'.User::count().'@example.test',
            'password' => self::PASSWORD, 'role_id' => Role::where('code', $roleCode)->value('id'), 'active' => true,
        ])->save();

        return $user;
    }

    private function newUserData(): array
    {
        return [
            'name' => '  Nuevo   Usuario  ', 'username' => 'nuevo', 'email' => '  NUEVO@example.test ', 'role_id' => Role::where('code', 'seller')->value('id'),
            'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD,
        ];
    }

    private function insertSession(User $user): void
    {
        DB::table('sessions')->insert([
            'id' => 'session-'.$user->id, 'user_id' => $user->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'SICOVI test', 'payload' => base64_encode(serialize([])), 'last_activity' => time(),
        ]);
    }
}
