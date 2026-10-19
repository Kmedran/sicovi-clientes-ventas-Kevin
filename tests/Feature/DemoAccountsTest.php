<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use App\Services\DemoUserService;
use App\Support\DemoAccess;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\DatabaseTestCase;
use TypeError;

class DemoAccountsTest extends DatabaseTestCase
{
    /** Public demonstration fixtures explicitly requested for local use only. */
    private const ACCOUNTS = [
        ['adminm@gmail.com', 'admin', 'admin'],
        ['vendedorm@gmail.com', 'vendedor', 'seller'],
        ['supervisorm@gmail.com', 'supervisor', 'supervisor'],
        ['bodegam@gmail.com', 'bodega', 'warehouse'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['sicovi.demo_accounts' => true]);
    }

    public function test_explicitly_enabled_local_provisioning_creates_four_hashed_demo_accounts(): void
    {
        $this->assertSame(0, User::count());
        $this->assertTrue($this->app->make(DemoAccess::class)->allowed());

        $results = $this->app->make(DemoUserService::class)->provision();

        $this->assertCount(4, $results);
        $this->assertSame(['created'], array_values(array_unique(array_column($results, 'status'))));
        $this->assertSame(4, User::count());
        foreach (self::ACCOUNTS as [$email, $password, $role]) {
            $user = User::where('email', $email)->sole();
            $this->assertTrue($user->is_demo);
            $this->assertTrue($user->active);
            $this->assertSame($role, $user->role->code);
            $this->assertTrue(Hash::check($password, $user->password));
            $this->assertNotSame($password, $user->getRawOriginal('password'));
            $this->assertArrayNotHasKey('password', $user->toArray());
            $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => strtolower($email), 'username' => strstr($email, '@', true), 'is_demo' => true]);
        }
    }

    public function test_demo_audit_contains_account_metadata_but_no_password_or_hash(): void
    {
        $this->app->make(DemoUserService::class)->provision('m');
        $events = AuditEvent::where('action', 'users.demo_created')->get();

        $this->assertCount(4, $events);
        foreach ($events as $event) {
            $this->assertNull($event->actor_id);
            $this->assertTrue($event->after_values['is_demo']);
            foreach (['password', 'password_confirmation', 'remember_token', 'raw_password'] as $field) {
                $this->assertArrayNotHasKey($field, $event->before_values);
                $this->assertArrayNotHasKey($field, $event->after_values);
            }
            foreach (self::ACCOUNTS as [$email, $password]) {
                $this->assertFalse(in_array($password, array_values($event->after_values), true));
                $this->assertStringNotContainsString(User::where('email', $email)->sole()->password, $event->toJson());
            }
        }
    }

    public function test_each_of_the_four_demo_credentials_authenticates_through_the_real_login_route(): void
    {
        $this->app->make(DemoUserService::class)->provision('m');

        foreach (self::ACCOUNTS as [$email, $password]) {
            $user = User::where('email', $email)->sole();
            $this->post(route('login.store'), ['username' => $user->username, 'password' => $password])->assertRedirect(route('dashboard'));
            $this->assertAuthenticatedAs($user);
            $this->get(route('dashboard'))->assertOk();
            $this->post(route('logout'))->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    public function test_uppercase_demo_login_matches_the_lowercase_stored_username(): void
    {
        $this->app->make(DemoUserService::class)->provision('m');

        $this->post(route('login.store'), ['username' => '  ADMINM  ', 'password' => 'admin'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs(User::where('email', 'adminm@gmail.com')->sole());
        $this->assertDatabaseHas('users', ['email' => 'adminm@gmail.com', 'is_demo' => true]);
        $this->assertDatabaseMissing('users', ['email' => 'ADMINM@GMAIL.COM']);
    }

    public function test_demo_accounts_receive_the_minimum_permissions_of_their_assigned_profiles(): void
    {
        $this->app->make(DemoUserService::class)->provision('m');
        $admin = User::where('email', 'adminm@gmail.com')->sole();
        $seller = User::where('email', 'vendedorm@gmail.com')->sole();
        $supervisor = User::where('email', 'supervisorm@gmail.com')->sole();
        $warehouse = User::where('email', 'bodegam@gmail.com')->sole();

        $this->assertTrue($admin->hasPermission('products.create'));
        $this->assertTrue($admin->hasPermission('users.create'));
        $this->assertTrue($admin->hasPermission('settings.update'));
        $this->assertTrue($supervisor->hasPermission('products.view_cost'));
        foreach ([$seller, $supervisor, $warehouse] as $user) {
            $this->assertTrue($user->hasPermission('products.view'));
            foreach (['products.create', 'products.update', 'products.deactivate', 'categories.manage', 'users.create', 'roles.manage', 'settings.update'] as $permission) {
                $this->assertFalse($user->hasPermission($permission));
            }
        }
        $this->assertFalse($seller->hasPermission('products.view_cost'));
        $this->assertFalse($warehouse->hasPermission('products.view_cost'));

        $this->actingAs($admin)->post(route('catalogs.store', 'brands'), ['name' => 'Marca de demostración autorizada', 'active' => '1'])
            ->assertRedirect(route('catalogs.index', 'brands'));
        $this->assertDatabaseHas('brands', ['name' => 'Marca de demostración autorizada']);
        foreach ([$seller, $supervisor, $warehouse] as $user) {
            $this->flushSession();
            $this->actingAs($user)->post(route('catalogs.store', 'brands'), ['name' => 'Marca sin permiso', 'active' => '1'])
                ->assertForbidden();
        }
        $this->assertDatabaseMissing('brands', ['name' => 'Marca sin permiso']);
    }

    public function test_reprovisioning_is_idempotent_and_preserves_password_name_and_active_status(): void
    {
        $service = $this->app->make(DemoUserService::class);
        $service->provision('m');
        $seller = User::where('email', 'vendedorm@gmail.com')->sole();
        $seller->forceFill(['name' => 'Nombre personalizado conservado', 'password' => 'ClavePersonal!2026', 'active' => false])->save();
        $hash = $seller->getRawOriginal('password');
        $auditCount = AuditEvent::count();

        $results = $service->provision('m');

        $this->assertSame(4, User::count());
        $this->assertSame(['existing'], array_values(array_unique(array_column($results, 'status'))));
        $this->assertSame($auditCount, AuditEvent::count());
        $this->assertSame('Nombre personalizado conservado', $seller->fresh()->name);
        $this->assertSame($hash, $seller->fresh()->getRawOriginal('password'));
        $this->assertTrue(Hash::check('ClavePersonal!2026', $seller->fresh()->password));
        $this->assertFalse($seller->fresh()->active);
        $this->assertSame('seller', $seller->fresh()->role->code);
    }

    public function test_a_conflicting_normal_account_aborts_the_whole_batch_without_modifying_it(): void
    {
        // The third planned email conflicts, so admin/vendor must not be left behind.
        $existing = $this->normalUser('supervisorm@gmail.com', 'supervisor');
        $existing->refresh();
        $before = $existing->getAttributes();
        $audits = AuditEvent::count();

        try {
            $this->app->make(DemoUserService::class)->provision('m');
            $this->fail('Provisioning must refuse a normal account with a planned demo email.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }

        $this->assertSame(1, User::count());
        $this->assertSame($before, $existing->fresh()->getAttributes());
        $this->assertSame($audits, AuditEvent::count());
        foreach (['adminm@gmail.com', 'vendedorm@gmail.com', 'bodegam@gmail.com'] as $email) {
            $this->assertDatabaseMissing('users', compact('email'));
        }
    }

    public function test_an_existing_demo_account_with_a_different_role_is_not_reassigned(): void
    {
        $existing = $this->normalUser('supervisorm@gmail.com', 'seller');
        $existing->forceFill(['is_demo' => true])->save();
        $existing->refresh();
        $before = $existing->getAttributes();

        try {
            $this->app->make(DemoUserService::class)->provision('m');
            $this->fail('Provisioning must refuse an existing account with a different role.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }

        $this->assertSame(1, User::count());
        $this->assertSame($before, $existing->fresh()->getAttributes());
        $this->assertSame(0, AuditEvent::count());
    }

    public function test_a_mandatory_audit_failure_rolls_back_new_demo_accounts(): void
    {
        $audit = $this->createMock(AuditService::class);
        $audit->expects($this->once())->method('record')->willThrowException(new RuntimeException('Controlled demo audit failure'));
        $service = new DemoUserService($audit, $this->app->make(DemoAccess::class));

        try {
            $service->provision('m');
            $this->fail('A failed mandatory audit must abort the demo batch.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Controlled demo audit failure', $exception->getMessage());
        }

        $this->assertSame(0, User::count());
        $this->assertSame(0, AuditEvent::count());
    }

    public function test_a_disabled_required_role_does_not_leave_a_partial_demo_batch(): void
    {
        Role::where('code', 'warehouse')->sole()->update(['active' => false]);

        try {
            $this->app->make(DemoUserService::class)->provision('m');
            $this->fail('Provisioning requires all intended roles to be active.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('roles', $exception->errors());
        }

        $this->assertSame(0, User::count());
        $this->assertSame(0, AuditEvent::count());
    }

    #[DataProvider('invalidInitials')]
    public function test_invalid_initials_are_validation_errors_without_new_accounts(string $initial): void
    {
        try {
            $this->app->make(DemoUserService::class)->provision($initial);
            $this->fail('Invalid initials must not create accounts.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('initial', $exception->errors());
        }

        $this->assertSame(0, User::count());
        $this->assertSame(0, AuditEvent::count());
    }

    public static function invalidInitials(): array
    {
        return [['m@bad'], [''], ['manuel'], ['m m'], ['ñ'], ['12']];
    }

    public function test_array_initial_is_rejected_by_the_service_type_without_writes(): void
    {
        try {
            $this->app->make(DemoUserService::class)->provision(['m']);
            $this->fail('The service must reject an array initial.');
        } catch (TypeError) {
            $this->assertSame(0, User::count());
            $this->assertSame(0, AuditEvent::count());
        }
    }

    public function test_initials_are_trimmed_and_stored_with_lowercase_emails(): void
    {
        $this->app->make(DemoUserService::class)->provision(' M ');

        $this->assertSame(4, User::count());
        foreach (self::ACCOUNTS as [$email]) {
            $this->assertDatabaseHas('users', compact('email'));
        }
    }

    public function test_the_explicit_flag_is_required_before_any_provisioning(): void
    {
        config(['sicovi.demo_accounts' => false]);
        $this->assertFalse($this->app->make(DemoAccess::class)->allowed());

        try {
            $this->app->make(DemoUserService::class)->provision('m');
            $this->fail('Provisioning must be disabled without the explicit demo flag.');
        } catch (RuntimeException) {
            $this->assertSame(0, User::count());
            $this->assertSame(0, AuditEvent::count());
        }
    }

    public function test_disabling_demo_access_rejects_demo_login_but_preserves_normal_user_login(): void
    {
        $this->app->make(DemoUserService::class)->provision('m');
        $normal = $this->normalUser();
        config(['sicovi.demo_accounts' => false]);

        $this->post(route('login.store'), ['username' => 'adminm', 'password' => 'admin'])->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->post(route('login.store'), ['username' => $normal->username, 'password' => 'NormalUser!2026'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($normal);
    }

    public function test_disabling_demo_access_revokes_an_already_authenticated_demo_session(): void
    {
        $this->app->make(DemoUserService::class)->provision('m');
        $this->post(route('login.store'), ['username' => 'adminm', 'password' => 'admin'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        config(['sicovi.demo_accounts' => false]);

        $this->get(route('dashboard'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_production_blocks_provisioning_even_with_the_demo_flag_enabled(): void
    {
        $environment = $this->app->environment();
        $this->app->instance('env', 'production');

        try {
            $this->assertFalse($this->app->make(DemoAccess::class)->allowed());
            $this->app->make(DemoUserService::class)->provision('m');
            $this->fail('A production environment must not provision demo accounts.');
        } catch (RuntimeException) {
            $this->assertSame(0, User::count());
            $this->assertSame(0, AuditEvent::count());
        } finally {
            $this->app->instance('env', $environment);
        }
    }

    public function test_production_rejects_demo_login_and_previous_session_while_normal_login_still_works(): void
    {
        $this->app->make(DemoUserService::class)->provision('m');
        $normal = $this->normalUser();
        $this->post(route('login.store'), ['username' => 'adminm', 'password' => 'admin'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $environment = $this->app->environment();
        $this->app->instance('env', 'production');

        try {
            $this->get(route('dashboard'))->assertRedirect(route('login'));
            $this->assertGuest();
            // Production enables CSRF middleware; account for the token renewed by logout.
            $this->post(route('login.store'), ['_token' => session()->token(), 'username' => 'adminm', 'password' => 'admin'])->assertSessionHasErrors('username');
            $this->assertGuest();
            $this->post(route('login.store'), ['_token' => session()->token(), 'username' => $normal->username, 'password' => 'NormalUser!2026'])
                ->assertRedirect(route('dashboard'));
            $this->assertAuthenticatedAs($normal);
        } finally {
            $this->app->instance('env', $environment);
        }
    }

    public function test_demo_passwords_do_not_relax_the_password_policy_for_normal_user_creation(): void
    {
        $normal = $this->normalUser();

        $this->actingAs($normal)->post(route('users.store'), [
            'name' => 'Cuenta normal con clave simple', 'username' => 'simple', 'email' => 'simple@example.test',
            'role_id' => Role::where('code', 'seller')->sole()->id,
            'password' => 'vendedor', 'password_confirmation' => 'vendedor',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'simple@example.test']);
        $this->assertSame(1, User::count());
        $this->assertFalse($normal->fresh()->is_demo);
    }

    private function normalUser(string $email = 'normal@example.test', string $role = 'admin'): User
    {
        $user = new User;
        $user->forceFill([
            'name' => 'Usuario normal de pruebas', 'email' => $email, 'password' => 'NormalUser!2026',
            'role_id' => Role::where('code', $role)->sole()->id, 'active' => true, 'is_demo' => false,
        ])->save();

        return $user;
    }
}
