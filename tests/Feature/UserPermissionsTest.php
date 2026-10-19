<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessService;
use Database\Seeders\SystemSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\DatabaseTestCase;

class UserPermissionsTest extends DatabaseTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = app(AccessService::class)->createAdministrator([
            'name' => 'Administrador de permisos',
            'email' => 'permissions-admin@example.test',
            'password' => 'Fixture-only-passphrase-2026!',
        ]);
    }

    public function test_creating_a_seller_stores_only_individual_differences_without_changing_the_role_or_other_users(): void
    {
        $seller = Role::where('code', 'seller')->sole();
        $other = $this->userForRole('seller');
        $defaults = $this->roleCodes($seller);
        $selected = $this->withChanges($defaults, ['products.view_cost'], ['brands.view']);

        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Vendedor con permisos propios', 'username' => 'individual-seller', 'email' => 'individual-seller@example.test',
            'role_id' => $seller->id,
            'password' => 'Fixture-only-passphrase-2026!',
            'password_confirmation' => 'Fixture-only-passphrase-2026!',
            'permissions_present' => true, 'permissions' => $selected,
        ])->assertRedirect(route('users.index'));

        $created = User::where('email', 'individual-seller@example.test')->sole();
        $this->assertSame($selected, $created->effectivePermissionCodes());
        $this->assertOverride($created, 'products.view_cost', true);
        $this->assertOverride($created, 'brands.view', false);
        $this->assertSame(2, DB::table('user_permissions')->where('user_id', $created->id)->count());
        $this->assertSame($defaults, $this->roleCodes($seller));
        $this->assertSame($defaults, $other->effectivePermissionCodes());
        $this->assertSame(0, DB::table('user_permissions')->where('user_id', $other->id)->count());
    }

    public function test_creation_with_default_selection_or_without_marker_creates_no_overrides(): void
    {
        $seller = Role::where('code', 'seller')->sole();
        foreach (['selected-defaults', 'no-marker'] as $case) {
            $data = [
                'name' => 'Usuario '.$case, 'username' => $case, 'email' => $case.'@example.test', 'role_id' => $seller->id,
                'password' => 'Fixture-only-passphrase-2026!', 'password_confirmation' => 'Fixture-only-passphrase-2026!',
            ];
            if ($case === 'selected-defaults') {
                $data += ['permissions_present' => true, 'permissions' => $this->roleCodes($seller)];
            }
            $this->actingAs($this->admin)->post(route('users.store'), $data)->assertRedirect(route('users.index'));
            $user = User::where('email', $case.'@example.test')->sole();
            $this->assertSame($this->roleCodes($seller), $user->effectivePermissionCodes());
            $this->assertSame(0, DB::table('user_permissions')->where('user_id', $user->id)->count());
        }
    }

    public function test_administrator_forms_render_permission_checkboxes_and_the_targets_actual_effective_selection(): void
    {
        $target = $this->userForRole('seller');
        $selected = $this->withChanges($target->effectivePermissionCodes(), ['products.view_cost'], ['brands.view']);
        $this->savePermissions($target, $selected)->assertRedirect(route('users.index'));

        $create = $this->actingAs($this->admin)->get(route('users.create'))->assertOk();
        $createInputs = $this->permissionInputs($create->getContent());
        $this->assertCount(Permission::count(), $createInputs);
        $this->assertArrayHasKey('products.view_cost', $createInputs);
        $this->assertStringContainsString('name="permissions_present"', $create->getContent());

        $edit = $this->get(route('users.edit', $target))->assertOk();
        $editInputs = $this->permissionInputs($edit->getContent());
        $checked = array_keys(array_filter($editInputs));
        sort($checked);
        $this->assertSame($selected, $checked);
        $this->assertTrue($editInputs['products.view_cost']);
        $this->assertFalse($editInputs['brands.view']);
    }

    public function test_grants_and_revocations_apply_to_the_same_existing_user_instance_and_next_authenticated_request(): void
    {
        $target = $this->userForRole('seller');
        $defaults = $target->effectivePermissionCodes();
        $this->assertFalse($target->hasPermission('users.view'));
        $this->actingAs($target)->get(route('users.index'))->assertForbidden();

        // Each simulated account needs its own session password hash.
        $this->flushSession();
        $this->savePermissions($target, $this->withChanges($defaults, ['users.view']))->assertRedirect(route('users.index'));
        $this->assertTrue($target->hasPermission('users.view'));
        $this->flushSession();
        $this->actingAs($target)->get(route('users.index'))->assertOk();

        $this->flushSession();
        $this->savePermissions($target, $defaults)->assertRedirect(route('users.index'));
        $this->assertFalse($target->hasPermission('users.view'));
        $this->flushSession();
        $this->actingAs($target)->get(route('users.index'))->assertForbidden();
        $this->assertSame(0, DB::table('user_permissions')->where('user_id', $target->id)->count());
    }

    public function test_create_and_edit_forms_recover_from_malformed_old_identity_fields(): void
    {
        $target = $this->userForRole('seller');
        $oldInput = [
            'name' => ['unexpected'], 'username' => ['unexpected'],
            'email' => ['unexpected'], 'role_id' => [$target->role_id],
        ];

        foreach ([route('users.create'), route('users.edit', $target)] as $url) {
            $response = $this->actingAs($this->admin)->withSession(['_old_input' => $oldInput])->get($url)->assertOk();
            $document = new \DOMDocument;
            $previous = libxml_use_internal_errors(true);
            try {
                $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            $xpath = new \DOMXPath($document);
            foreach (['name', 'username', 'email'] as $field) {
                $input = $xpath->query('//input[@name="'.$field.'"]')->item(0);
                $this->assertNotNull($input);
                $this->assertSame('', $input->getAttribute('value'));
            }
            $this->assertSame(0, $xpath->query('//select[@name="role_id"]/option[@selected]')->count());
        }
        $this->assertSame($target->role_id, $target->fresh()->role_id);
    }

    public function test_present_marker_with_no_checked_permissions_explicitly_denies_all_defaults(): void
    {
        $target = $this->userForRole('seller');
        // An ordinary browser omits the permissions array when no checkbox is checked.
        $this->actingAs($this->admin)->put(route('users.update', $target), $this->identity($target) + ['permissions_present' => true])
            ->assertRedirect(route('users.index'));

        $this->assertSame([], $target->effectivePermissionCodes());
        $this->assertSame(count($this->roleCodes($target->role)), DB::table('user_permissions')->where('user_id', $target->id)->where('allowed', false)->count());
        $this->flushSession();
        $this->actingAs($target)->get(route('products.index'))->assertForbidden();
    }

    public function test_missing_marker_preserves_overrides_but_a_role_change_without_marker_resets_them(): void
    {
        $target = $this->userForRole('seller');
        $selected = $this->withChanges($target->effectivePermissionCodes(), ['products.view_cost'], ['brands.view']);
        $this->savePermissions($target, $selected)->assertRedirect(route('users.index'));

        $this->actingAs($this->admin)->put(route('users.update', $target), array_replace($this->identity($target), ['name' => 'Nombre actualizado']))
            ->assertRedirect(route('users.index'));
        $this->assertSame('Nombre actualizado', $target->fresh()->name);
        $this->assertSame($selected, $target->effectivePermissionCodes());
        $this->assertSame(2, DB::table('user_permissions')->where('user_id', $target->id)->count());

        $warehouse = Role::where('code', 'warehouse')->sole();
        $this->put(route('users.update', $target), array_replace($this->identity($target), ['role_id' => $warehouse->id]))
            ->assertRedirect(route('users.index'));
        $this->assertSame($warehouse->id, $target->fresh()->role_id);
        $this->assertSame($this->roleCodes($warehouse), $target->effectivePermissionCodes());
        $this->assertSame(0, DB::table('user_permissions')->where('user_id', $target->id)->count());
    }

    public function test_false_marker_preserves_overrides_when_no_selection_is_submitted(): void
    {
        $target = $this->userForRole('seller');
        $selected = $this->withChanges($target->effectivePermissionCodes(), ['products.view_cost'], ['brands.view']);
        $this->savePermissions($target, $selected)->assertRedirect(route('users.index'));

        $this->actingAs($this->admin)->put(route('users.update', $target), $this->identity($target) + ['permissions_present' => false])
            ->assertRedirect(route('users.index'));

        $this->assertSame($selected, $target->effectivePermissionCodes());
        $this->assertSame(2, DB::table('user_permissions')->where('user_id', $target->id)->count());
    }

    #[DataProvider('unconfirmedSelectionMarkers')]
    public function test_nonempty_permission_selection_without_a_true_marker_is_rejected(?bool $marker): void
    {
        $target = $this->userForRole('seller');
        $before = $target->effectivePermissionCodes();
        $data = $this->identity($target) + ['permissions' => ['roles.manage']];
        if ($marker !== null) {
            $data['permissions_present'] = $marker;
        }

        $this->actingAs($this->admin)->put(route('users.update', $target), $data)->assertSessionHasErrors('permissions');

        $this->assertSame($before, $target->effectivePermissionCodes());
        $this->assertSame(0, DB::table('user_permissions')->where('user_id', $target->id)->count());
    }

    public static function unconfirmedSelectionMarkers(): array
    {
        return [[null], [false]];
    }

    public function test_user_editor_without_role_management_cannot_submit_permission_fields_or_raise_privileges(): void
    {
        $editorRole = Role::create(['code' => 'permission_editor', 'name' => 'Editor sin permisos de acceso']);
        $editorRole->permissions()->attach(Permission::where('code', 'users.update')->sole()->id);
        $editor = $this->userForRole('permission_editor');
        $target = $this->userForRole('seller');
        $before = $target->effectivePermissionCodes();

        $this->actingAs($editor)->put(route('users.update', $target), [
            'name' => $target->name, 'username' => $target->username, 'email' => $target->email,
            'permissions_present' => true, 'permissions' => ['roles.manage', 'users.update'],
        ])->assertSessionHasErrors(['permissions_present', 'permissions']);
        $this->assertSame($before, $target->effectivePermissionCodes());
        $this->assertSame(0, DB::table('user_permissions')->where('user_id', $target->id)->count());

        $this->put(route('users.update', $target), ['name' => 'Edición básica autorizada', 'username' => $target->username, 'email' => $target->email])
            ->assertRedirect(route('users.index'));
        $this->assertSame('Edición básica autorizada', $target->fresh()->name);
        $this->assertSame($before, $target->effectivePermissionCodes());
    }

    #[DataProvider('administrationPermissions')]
    public function test_last_administrator_cannot_lose_an_effective_recovery_permission_and_all_changes_roll_back(string $code): void
    {
        $before = $this->admin->effectivePermissionCodes();
        $auditCount = AuditEvent::count();
        $data = $this->identity($this->admin) + ['permissions_present' => true, 'permissions' => $this->withChanges($before, [], [$code])];
        $data['name'] = 'Este nombre debe revertirse';

        $this->actingAs($this->admin)->put(route('users.update', $this->admin), $data)->assertSessionHasErrors('administration');

        $this->assertSame('Administrador de permisos', $this->admin->fresh()->name);
        $this->assertSame($before, $this->admin->effectivePermissionCodes());
        $this->assertSame(0, DB::table('user_permissions')->where('user_id', $this->admin->id)->count());
        $this->assertSame($auditCount, AuditEvent::count());
    }

    public static function administrationPermissions(): array
    {
        return [['users.update'], ['roles.manage']];
    }

    public function test_one_of_two_administrators_can_have_a_recovery_override_but_the_remaining_complete_administrator_is_protected(): void
    {
        $second = $this->userForRole('admin');
        $this->savePermissions($second, $this->withChanges($second->effectivePermissionCodes(), [], ['roles.manage']))
            ->assertRedirect(route('users.index'));
        $this->assertFalse($second->hasPermission('roles.manage'));
        $this->assertTrue($this->admin->hasPermission('roles.manage'));

        $this->savePermissions($this->admin, $this->withChanges($this->admin->effectivePermissionCodes(), [], ['users.update']))
            ->assertSessionHasErrors('administration');

        $this->assertTrue($this->admin->hasPermission('users.update'));
        $this->assertFalse($second->hasPermission('roles.manage'));
        $this->assertOverride($second, 'roles.manage', false);
    }

    public function test_role_default_changes_propagate_except_where_an_explicit_individual_allow_or_deny_exists(): void
    {
        $target = $this->userForRole('seller');
        $other = $this->userForRole('seller');
        $role = $target->role;
        $originalDefaults = $this->roleCodes($role);
        $this->savePermissions($target, $this->withChanges($originalDefaults, ['products.view_cost'], ['brands.view']))
            ->assertRedirect(route('users.index'));
        $this->assertSame(2, DB::table('user_permissions')->where('user_id', $target->id)->count());

        $newDefaults = $this->withChanges($originalDefaults, ['users.view', 'products.view_cost'], ['products.view', 'brands.view']);
        $this->actingAs($this->admin)->put(route('roles.update', $role), [
            'name' => $role->name, 'active' => true, 'permissions' => $newDefaults,
        ])->assertRedirect(route('roles.index'));
        $this->assertTrue($target->hasPermission('users.view'));
        $this->assertFalse($target->hasPermission('products.view'));
        $this->assertTrue($target->hasPermission('products.view_cost'));
        $this->assertFalse($target->hasPermission('brands.view'));
        $this->assertSame($newDefaults, $other->effectivePermissionCodes());

        $restoredDefaults = $this->withChanges($newDefaults, ['brands.view'], ['products.view_cost']);
        $this->put(route('roles.update', $role), ['name' => $role->name, 'active' => true, 'permissions' => $restoredDefaults])
            ->assertRedirect(route('roles.index'));
        $this->assertTrue($target->hasPermission('products.view_cost'));
        $this->assertFalse($target->hasPermission('brands.view'));
        $this->assertFalse($other->hasPermission('products.view_cost'));
        $this->assertTrue($other->hasPermission('brands.view'));
        $this->assertSame(2, DB::table('user_permissions')->where('user_id', $target->id)->count());
    }

    public function test_unknown_permission_codes_are_rejected_without_partial_user_changes(): void
    {
        $target = $this->userForRole('seller');
        $before = $target->effectivePermissionCodes();
        $this->savePermissions($target, [...$before, 'invented.superuser'])->assertSessionHasErrors('permissions.'.count($before));

        $this->assertSame($before, $target->effectivePermissionCodes());
        $this->assertSame(0, DB::table('user_permissions')->where('user_id', $target->id)->count());
    }

    public function test_repeating_structural_seeding_does_not_remove_individual_permission_overrides(): void
    {
        $target = $this->userForRole('seller');
        $selected = $this->withChanges($target->effectivePermissionCodes(), ['products.view_cost'], ['brands.view']);
        $this->savePermissions($target, $selected)->assertRedirect(route('users.index'));

        $this->seed(SystemSeeder::class);

        $this->assertSame($selected, $target->effectivePermissionCodes());
        $this->assertOverride($target, 'products.view_cost', true);
        $this->assertOverride($target, 'brands.view', false);
    }

    private function savePermissions(User $target, array $selected)
    {
        return $this->actingAs($this->admin)->put(route('users.update', $target), $this->identity($target) + [
            'permissions_present' => true, 'permissions' => $selected,
        ]);
    }

    private function identity(User $user): array
    {
        $fresh = $user->fresh();

        return ['name' => $fresh->name, 'username' => $fresh->username, 'email' => $fresh->email, 'role_id' => $fresh->role_id];
    }

    private function userForRole(string $code): User
    {
        $user = new User;
        $user->forceFill([
            'name' => 'Cuenta de prueba '.$code, 'email' => 'user-permission-'.User::count().'@example.test',
            'password' => 'Fixture-only-passphrase-2026!', 'role_id' => Role::where('code', $code)->sole()->id,
            'active' => true,
        ])->save();

        return $user;
    }

    private function roleCodes(Role $role): array
    {
        return $role->permissions()->orderBy('code')->pluck('code')->all();
    }

    private function withChanges(array $codes, array $grants = [], array $revocations = []): array
    {
        $codes = array_values(array_unique(array_diff([...$codes, ...$grants], $revocations)));
        sort($codes);

        return $codes;
    }

    private function assertOverride(User $user, string $code, bool $allowed): void
    {
        $this->assertDatabaseHas('user_permissions', [
            'user_id' => $user->id,
            'permission_id' => Permission::where('code', $code)->sole()->id,
            'allowed' => $allowed,
        ]);
    }

    /** @return array<string, bool> Permission code mapped to its rendered checked state. */
    private function permissionInputs(string $html): array
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new \DOMXPath($document);
        $values = [];
        foreach ($xpath->query('//input[@type="checkbox" and @name="permissions[]"]') as $input) {
            $values[$input->getAttribute('value')] = $input->hasAttribute('checked');
        }

        return $values;
    }
}
