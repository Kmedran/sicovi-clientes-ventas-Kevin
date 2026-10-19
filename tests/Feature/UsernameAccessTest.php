<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\DatabaseTestCase;

class UsernameAccessTest extends DatabaseTestCase
{
    private const PASSWORD = 'Fixture-only-passphrase-2026!';

    public function test_failed_login_preserves_the_manually_entered_username_for_retry_without_returning_the_password(): void
    {
        $this->administrator();
        $this->from(route('login'))->post(route('login.store'), ['username' => 'admin.test', 'password' => 'wrong-password'])
            ->assertRedirect(route('login'))->assertSessionHasErrors('username');
        $response = $this->get(route('login'))->assertOk();
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new \DOMXPath($document);
        $this->assertSame('admin.test', $xpath->query('//input[@name="username"]')->item(0)->getAttribute('value'));
        $this->assertSame('true', $xpath->query('//form[@data-login-form]')->item(0)->getAttribute('data-preserve-input'));
        $this->assertSame(0, $xpath->query('//input[@name="password"]/@value')->count());
        $this->assertStringNotContainsString('wrong-password', $response->getContent());
    }

    private function administrator(): User
    {
        return app(AccessService::class)->createAdministrator([
            'name' => 'Administrador de usuarios', 'username' => 'admin.test',
            'email' => 'admin@example.test', 'password' => self::PASSWORD,
        ]);
    }

    public function test_administrator_assigns_a_normalized_username_and_the_account_logs_in_without_email(): void
    {
        $admin = $this->administrator();
        $this->actingAs($admin)->post(route('users.store'), $this->newAccount() + ['username' => '  VENDEDOR.M  '])
            ->assertRedirect(route('users.index'));
        $user = User::where('email', 'new@example.test')->sole();
        $this->assertSame('vendedor.m', $user->username);
        $this->assertSame('vendedor.m', AuditEvent::where('action', 'users.created')->sole()->after_values['username']);
        $this->post(route('logout'));

        $this->post(route('login.store'), ['username' => ' VENDEDOR.M ', 'password' => self::PASSWORD])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_username_is_required_and_case_equivalent_duplicates_are_rejected(): void
    {
        $admin = $this->administrator();
        $this->actingAs($admin)->post(route('users.store'), $this->newAccount())->assertSessionHasErrors('username');
        $this->post(route('users.store'), $this->newAccount() + ['username' => ' ADMIN.TEST '])->assertSessionHasErrors('username');
        $this->assertSame(1, User::count());
    }

    public function test_email_updates_do_not_change_username_and_explicit_renaming_changes_the_login(): void
    {
        $admin = $this->administrator();
        $user = app(AccessService::class)->createUser($admin, $this->newAccount() + ['username' => 'vendedor.m']);
        $identity = ['name' => $user->name, 'username' => $user->username, 'email' => 'updated@example.test', 'role_id' => $user->role_id];
        $this->actingAs($admin)->put(route('users.update', $user), $identity)->assertRedirect(route('users.index'));
        $this->assertSame('vendedor.m', $user->fresh()->username);
        $this->put(route('users.update', $user), array_replace($identity, ['username' => ' CAJA.M ']))->assertRedirect(route('users.index'));
        $event = AuditEvent::where('action', 'users.updated')->orderByDesc('id')->firstOrFail();
        $this->assertSame('vendedor.m', $event->before_values['username']);
        $this->assertSame('caja.m', $event->after_values['username']);
        $this->assertSame($user->role_id, $user->fresh()->role_id);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
        $this->post(route('logout'));
        $this->post(route('login.store'), ['username' => 'vendedor.m', 'password' => self::PASSWORD])->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->post(route('login.store'), ['username' => 'caja.m', 'password' => self::PASSWORD])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_requires_the_username_instead_of_a_full_email_or_an_email_only_payload(): void
    {
        $admin = $this->administrator();
        $this->post(route('login.store'), ['username' => $admin->email, 'password' => self::PASSWORD])->assertSessionHasErrors('username');
        $this->post(route('login.store'), ['email' => $admin->email, 'password' => self::PASSWORD])->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_case_and_spaces_cannot_bypass_the_login_attempt_limit(): void
    {
        $this->administrator();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), ['username' => $attempt % 2 ? ' ADMIN.TEST ' : 'admin.test', 'password' => 'wrong'])
                ->assertSessionHasErrors('username');
        }
        $this->post(route('login.store'), ['username' => 'admin.test', 'password' => self::PASSWORD])
            ->assertSessionHas('errors', fn ($errors) => str_contains($errors->first('username'), 'Demasiados intentos'));
        $this->assertGuest();
    }

    #[DataProvider('invalidDatabaseUsernames')]
    public function test_postgresql_protects_usernames_even_for_direct_writes(?string $username, string $state): void
    {
        $user = $this->administrator();
        try {
            DB::transaction(fn () => DB::table('users')->insert([
                'name' => 'Intento inválido', 'username' => $username, 'email' => 'other@example.test',
                'password' => 'not-a-real-account', 'role_id' => $user->role_id,
            ]));
            $this->fail('PostgreSQL must reject the invalid username.');
        } catch (QueryException $exception) {
            $this->assertSame($state, $exception->getCode());
        }
        $this->assertSame(1, User::count());
    }

    public static function invalidDatabaseUsernames(): array
    {
        return [
            ['ADMIN', '23514'], [' admin ', '23514'], ['ab', '23514'], ['a@b', '23514'],
            ['.admin', '23514'], [null, '23502'], ['admin.test', '23505'], [str_repeat('a', 41), '22001'],
        ];
    }

    public function test_upgrade_backfills_unique_usernames_without_changing_existing_accounts(): void
    {
        // DDL is transactional on the guarded PostgreSQL test connection.
        $migration = require database_path('migrations/2026_10_19_000005_add_usernames_to_users.php');
        $migration->down();
        $hash = Hash::make(self::PASSWORD);
        $roleId = Role::where('code', 'seller')->sole()->id;
        $before = [];
        foreach (['same@one.test', 'same@two.test', 'x@three.test'] as $index => $email) {
            $id = DB::table('users')->insertGetId([
                'name' => 'Cuenta existente '.$index, 'email' => $email, 'password' => $hash,
                'role_id' => $roleId, 'active' => $index !== 1,
            ]);
            $before[$id] = (array) DB::table('users')->where('id', $id)->first();
        }
        $migration->up();
        $users = DB::table('users')->orderBy('id')->get();
        $this->assertCount(3, $users->pluck('username')->unique());
        $this->assertSame('same', $users->first()->username);
        foreach ($users as $user) {
            $this->assertMatchesRegularExpression('/\A[a-z0-9][a-z0-9._-]{2,39}\z/', $user->username);
            $after = (array) $user;
            unset($after['username']);
            $this->assertSame($before[$user->id], $after);
        }
    }

    private function newAccount(): array
    {
        return [
            'name' => 'Vendedor de prueba', 'email' => 'new@example.test',
            'role_id' => Role::where('code', 'seller')->sole()->id,
            'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD,
        ];
    }
}
