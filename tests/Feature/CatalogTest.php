<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\InventoryBalance;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Subcategory;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CatalogService;
use Database\Seeders\DemoSeeder;
use Database\Seeders\SystemSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\DatabaseTestCase;

class CatalogTest extends DatabaseTestCase
{
    private User $admin;

    private Category $category;

    private Unit $unit;

    public function test_accented_catalog_names_are_case_insensitive_in_http_and_postgresql(): void
    {
        Brand::create(['name' => 'Marca Ágil', 'active' => true]);
        $this->actingAs($this->admin)->post('/catalogs/brands', ['name' => 'marca ágil', 'active' => '1'])->assertSessionHasErrors('name');
        $this->assertSame(1, Brand::count());
        try {
            DB::transaction(fn () => DB::table('brands')->insert(['name' => 'MARCA ÁGIL', 'active' => true]));
            $this->fail('PostgreSQL debe rechazar nombres equivalentes con tildes.');
        } catch (QueryException $exception) {
            $this->assertSame('23505', $exception->getCode());
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->actor('admin');
        $this->category = Category::create(['code' => 'TEST-CAT', 'name' => 'Categoría de pruebas', 'active' => true]);
        $this->unit = Unit::create(['code' => 'TEST-UND', 'name' => 'Unidad de pruebas', 'abbreviation' => 'ut', 'decimal_places' => 0, 'active' => true]);
    }

    public function test_administrator_can_create_read_update_and_deactivate_a_catalog_record(): void
    {
        $this->actingAs($this->admin)->post('/catalogs/categories', [
            'code' => '  office  ', 'name' => '  Artículos   de oficina  ', 'description' => 'Datos ficticios', 'active' => '1',
        ])->assertRedirect('/catalogs/categories');

        $category = Category::where('code', 'OFFICE')->sole();
        $this->assertSame('Artículos de oficina', $category->name);
        $this->get('/catalogs/categories/'.$category->id.'/edit')->assertOk();
        $this->get('/catalogs/categories?q=oficina&active=1')->assertOk()
            ->assertViewHas('records', fn ($records): bool => $records->total() === 1 && $records->first()->is($category));

        $this->put('/catalogs/categories/'.$category->id, [
            'code' => 'OFFICE', 'name' => 'Oficina actualizada', 'description' => 'Edición persistida', 'active' => '0',
        ])->assertRedirect('/catalogs/categories');

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Oficina actualizada', 'active' => false]);
        $this->get('/catalogs/categories?active=0')->assertOk()
            ->assertViewHas('records', fn ($records): bool => $records->contains('id', $category->id));
        $this->get('/catalogs/categories?q=sin-resultados-123')->assertOk()
            ->assertViewHas('records', fn ($records): bool => $records->total() === 0);
    }

    public function test_catalog_lists_paginate_and_preserve_search_filters(): void
    {
        foreach (range(1, 17) as $number) {
            Brand::create(['name' => sprintf('Marca paginada %02d', $number), 'active' => true]);
        }

        $this->actingAs($this->admin)->get('/catalogs/brands?q=paginada&active=1&page=2')->assertOk()
            ->assertViewHas('records', function ($records): bool {
                return $records->total() === 17 && $records->count() === 2
                    && str_contains($records->url(1), 'q=paginada')
                    && str_contains($records->url(1), 'active=1');
            });
    }

    #[DataProvider('readOnlyRoles')]
    public function test_read_only_roles_cannot_write_by_sending_direct_requests(string $role): void
    {
        $product = $this->createProduct();
        $actor = $this->actor($role);
        $before = $product->fresh()->getAttributes();

        $this->actingAs($actor)->post('/products', $this->productData(['sku' => 'FORBIDDEN']))->assertForbidden();
        $this->put('/products/'.$product->id, $this->productData(['name' => 'Cambio no autorizado']))->assertForbidden();
        $this->patch('/products/'.$product->id.'/deactivate')->assertForbidden();
        $this->post('/catalogs/categories', ['code' => 'DENIED', 'name' => 'Sin permiso', 'active' => '1'])->assertForbidden();
        $this->put('/catalogs/categories/'.$this->category->id, ['code' => 'TEST-CAT', 'name' => 'Cambio no autorizado', 'active' => '0'])->assertForbidden();

        $this->assertSame($before, $product->fresh()->getAttributes());
        $this->assertDatabaseMissing('products', ['sku' => 'FORBIDDEN']);
        $this->assertDatabaseMissing('categories', ['code' => 'DENIED']);
    }

    #[DataProvider('readOnlyRoles')]
    public function test_users_without_cost_permission_never_receive_the_cost_in_product_html(string $role): void
    {
        $product = $this->createProduct(['reference_cost' => '987654.32']);
        $actor = $this->actor($role);
        $this->assertFalse($actor->hasPermission('products.view_cost'));

        foreach (['/products', '/products/'.$product->id] as $url) {
            $response = $this->actingAs($actor)->get($url)->assertOk();
            $response->assertDontSee('987654.32', false)
                ->assertDontSee('987,654.32', false)
                ->assertDontSee('reference_cost', false);
        }
        $this->assertArrayNotHasKey('reference_cost', $product->toArray());
    }

    public static function readOnlyRoles(): array
    {
        return ['seller' => ['seller'], 'viewer' => ['viewer']];
    }

    public function test_catalog_names_are_unique_after_case_and_whitespace_normalization(): void
    {
        Brand::create(['name' => 'Marca normalizada', 'active' => true]);
        $before = Brand::count();

        $this->actingAs($this->admin)->post('/catalogs/brands', ['name' => '  MARCA   NORMALIZADA  ', 'active' => '1'])
            ->assertSessionHasErrors('name');

        $this->assertSame($before, Brand::count());
    }

    public function test_database_also_rejects_normalized_duplicate_names_without_a_form(): void
    {
        Brand::create(['name' => 'Marca normalizada', 'active' => true]);

        $this->assertPostgresRejects(fn () => DB::table('brands')->insert(['name' => '  MARCA   NORMALIZADA  ', 'active' => true]), '23505');

        $this->assertSame(1, Brand::where('name', 'Marca normalizada')->count());
    }

    public function test_subcategory_names_are_unique_within_their_category(): void
    {
        Subcategory::create(['category_id' => $this->category->id, 'name' => 'Accesorios', 'active' => true]);
        $other = Category::create(['code' => 'OTHER', 'name' => 'Otra categoría', 'active' => true]);
        $payload = ['category_id' => $this->category->id, 'name' => ' accesorios ', 'active' => '1'];

        $this->actingAs($this->admin)->post('/catalogs/subcategories', $payload)->assertSessionHasErrors('name');
        $this->post('/catalogs/subcategories', array_replace($payload, ['category_id' => $other->id]))
            ->assertRedirect('/catalogs/subcategories');

        $this->assertSame(2, Subcategory::whereRaw('lower(name) = ?', ['accesorios'])->count());
    }

    public function test_product_crud_persists_normalized_values_and_initializes_one_zero_balance(): void
    {
        $this->actingAs($this->admin)->post('/products', $this->productData([
            'sku' => '  sample-01  ', 'name' => '  Producto   de prueba  ', 'barcode' => '0012345678905',
        ]))->assertRedirect();

        $product = Product::where('sku', 'SAMPLE-01')->sole();
        $this->assertSame('Producto de prueba', $product->name);
        $this->assertSame('0012345678905', $product->barcode);
        $this->assertSame('25.00', $product->sale_price);
        $this->assertSame(1, $product->inventoryBalances()->count());
        $this->assertSame('0.000', $product->inventoryBalances()->sole()->quantity);
        $this->get('/products/'.$product->id)->assertOk();

        $this->put('/products/'.$product->id, $this->productData(['sku' => 'SAMPLE-01', 'name' => 'Producto actualizado', 'sale_price' => '30.00']))
            ->assertRedirect('/products/'.$product->id);
        $this->assertSame('30.00', $product->fresh()->sale_price);
        $this->assertSame(1, $product->inventoryBalances()->count());
        $this->assertSame('0.000', $product->inventoryBalances()->sole()->quantity);

        $this->patch('/products/'.$product->id.'/deactivate')->assertRedirect();
        $this->assertFalse($product->fresh()->active);
        $this->patch('/products/'.$product->id.'/deactivate')->assertRedirect();
        $this->assertTrue($product->fresh()->active);
    }

    public function test_duplicate_sku_and_barcode_are_rejected_without_partial_records(): void
    {
        $this->createProduct(['sku' => 'UNIQUE-01', 'barcode' => '000111222']);
        $products = Product::count();
        $balances = InventoryBalance::count();

        $this->actingAs($this->admin)->post('/products', $this->productData(['sku' => ' unique-01 ', 'barcode' => null]))
            ->assertSessionHasErrors('sku');
        $this->post('/products', $this->productData(['sku' => 'UNIQUE-02', 'barcode' => ' 000111222 ']))
            ->assertSessionHasErrors('barcode');

        $this->assertSame($products, Product::count());
        $this->assertSame($balances, InventoryBalance::count());
    }

    public function test_empty_optional_barcodes_are_null_and_do_not_collide(): void
    {
        $this->actingAs($this->admin)->post('/products', $this->productData(['sku' => 'NO-BARCODE-A', 'barcode' => '   ']))->assertRedirect();
        $this->post('/products', $this->productData(['sku' => 'NO-BARCODE-B', 'barcode' => '']))->assertRedirect();

        $this->assertSame(2, Product::whereIn('sku', ['NO-BARCODE-A', 'NO-BARCODE-B'])->whereNull('barcode')->count());
    }

    public function test_incompatible_category_and_subcategory_are_rejected_by_application_and_postgresql(): void
    {
        $other = Category::create(['code' => 'OTHER', 'name' => 'Otra categoría', 'active' => true]);
        $subcategory = Subcategory::create(['category_id' => $other->id, 'name' => 'Subcategoría ajena', 'active' => true]);

        $this->actingAs($this->admin)->post('/products', $this->productData(['subcategory_id' => $subcategory->id]))
            ->assertSessionHasErrors('subcategory_id');
        $this->assertDatabaseMissing('products', ['sku' => 'TEST-PRODUCT']);

        $product = $this->createProduct();
        $this->assertPostgresRejects(fn () => DB::table('products')->where('id', $product->id)->update(['subcategory_id' => $subcategory->id]), '23503');

        $this->assertNull($product->fresh()->subcategory_id);
    }

    #[DataProvider('relationFields')]
    public function test_inactive_catalogs_cannot_be_assigned_to_a_new_product(string $field): void
    {
        $record = match ($field) {
            'category_id' => Category::create(['code' => 'INACTIVE', 'name' => 'Categoría inactiva', 'active' => false]),
            'subcategory_id' => Subcategory::create(['category_id' => $this->category->id, 'name' => 'Subcategoría inactiva', 'active' => false]),
            'brand_id' => Brand::create(['name' => 'Marca inactiva', 'active' => false]),
            'unit_id' => Unit::create(['code' => 'INACTIVE', 'name' => 'Unidad inactiva', 'abbreviation' => 'in', 'decimal_places' => 0, 'active' => false]),
        };

        $this->actingAs($this->admin)->post('/products', $this->productData([$field => $record->id]))->assertSessionHasErrors($field);

        $this->assertDatabaseMissing('products', ['sku' => 'TEST-PRODUCT']);
    }

    public static function relationFields(): array
    {
        return ['category' => ['category_id'], 'subcategory' => ['subcategory_id'], 'brand' => ['brand_id'], 'unit' => ['unit_id']];
    }

    public function test_editing_preserves_a_current_inactive_relation_without_silent_reassignment(): void
    {
        $product = $this->createProduct();
        // Represents an existing relation imported before the current restriction.
        $this->category->update(['active' => false]);

        $this->actingAs($this->admin)->get('/products/'.$product->id.'/edit')->assertOk()
            ->assertViewHas('categories', fn ($categories): bool => $categories->contains('id', $this->category->id));
        $this->put('/products/'.$product->id, $this->productData(['name' => 'Edición con relación conservada']))->assertRedirect();

        $this->assertSame($this->category->id, $product->fresh()->category_id);
        $this->assertFalse($this->category->fresh()->active);
    }

    public function test_catalogs_used_by_active_products_cannot_be_deactivated(): void
    {
        $brand = Brand::create(['name' => 'Marca vinculada', 'active' => true]);
        $subcategory = Subcategory::create(['category_id' => $this->category->id, 'name' => 'Subcategoría vinculada', 'active' => true]);
        $this->createProduct(['brand_id' => $brand->id, 'subcategory_id' => $subcategory->id]);
        $cases = [
            ['categories', $this->category, ['code' => $this->category->code]],
            ['subcategories', $subcategory, ['category_id' => $this->category->id]],
            ['brands', $brand, []],
            ['units', $this->unit, ['code' => $this->unit->code, 'abbreviation' => $this->unit->abbreviation, 'decimal_places' => 0]],
        ];

        foreach ($cases as [$key, $record, $extra]) {
            $this->actingAs($this->admin)->put('/catalogs/'.$key.'/'.$record->id, $extra + ['name' => $record->name, 'active' => '0'])
                ->assertSessionHasErrors('active');
            $this->assertTrue($record->fresh()->active);
        }
    }

    public function test_precision_of_a_unit_already_used_by_products_cannot_change(): void
    {
        $this->createProduct();

        $this->actingAs($this->admin)->put('/catalogs/units/'.$this->unit->id, [
            'code' => $this->unit->code, 'name' => $this->unit->name, 'abbreviation' => 'ut', 'decimal_places' => 3, 'active' => '1',
        ])->assertSessionHasErrors('decimal_places');

        $this->assertSame(0, $this->unit->fresh()->decimal_places);
    }

    #[DataProvider('invalidProductNumbers')]
    public function test_invalid_amounts_and_quantities_are_rejected_without_saving(string $field, string $value): void
    {
        $this->actingAs($this->admin)->post('/products', $this->productData([$field => $value]))->assertSessionHasErrors($field);

        $this->assertDatabaseMissing('products', ['sku' => 'TEST-PRODUCT']);
        $this->assertSame(0, InventoryBalance::count());
    }

    public static function invalidProductNumbers(): array
    {
        return [
            'negative price' => ['sale_price', '-0.01'],
            'excess money precision' => ['sale_price', '1.001'],
            'price overflow' => ['sale_price', '1000000000000.00'],
            'negative cost' => ['reference_cost', '-1.00'],
            'scientific notation' => ['reference_cost', '1e3'],
            'not a number' => ['sale_price', 'NaN'],
            'fractional whole unit' => ['minimum_stock', '1.5'],
            'excess quantity precision' => ['minimum_stock', '0.0001'],
            'quantity overflow' => ['minimum_stock', '100000000000.000'],
        ];
    }

    public function test_zero_prices_costs_and_minimum_stock_are_valid(): void
    {
        $this->actingAs($this->admin)->post('/products', $this->productData(['sale_price' => '0', 'reference_cost' => '0', 'minimum_stock' => '0']))
            ->assertRedirect();

        $product = Product::where('sku', 'TEST-PRODUCT')->sole();
        $this->assertSame('0.00', $product->sale_price);
        $this->assertSame('0.00', $product->reference_cost);
        $this->assertSame('0.000', $product->minimum_stock);
    }

    public function test_a_fractional_unit_accepts_its_configured_precision(): void
    {
        $unit = Unit::create(['code' => 'TEST-KG', 'name' => 'Kilogramo de pruebas', 'abbreviation' => 'kgp', 'decimal_places' => 3, 'active' => true]);

        $this->actingAs($this->admin)->post('/products', $this->productData(['unit_id' => $unit->id, 'minimum_stock' => '0.333']))->assertRedirect();

        $this->assertSame('0.333', Product::where('sku', 'TEST-PRODUCT')->sole()->minimum_stock);
    }

    public function test_manipulated_stock_context_and_actor_fields_are_rejected(): void
    {
        $forbidden = ['stock' => '100', 'quantity' => '100', 'warehouse_id' => '999', 'actor_id' => '999', 'active' => '0'];

        $this->actingAs($this->admin)->post('/products', $this->productData($forbidden))->assertSessionHasErrors(array_keys($forbidden));
        $this->assertDatabaseMissing('products', ['sku' => 'TEST-PRODUCT']);
        $this->assertSame(0, InventoryBalance::count());

        $product = $this->createProduct();
        $this->put('/products/'.$product->id, $this->productData(['stock' => '100']))->assertSessionHasErrors('stock');
        $this->assertSame('0.000', $product->inventoryBalances()->sole()->quantity);
    }

    public function test_product_creation_and_zero_balance_roll_back_if_mandatory_audit_fails(): void
    {
        $audit = $this->createMock(AuditService::class);
        $audit->expects($this->once())->method('record')->willThrowException(new RuntimeException('Controlled audit failure'));
        $this->app->instance(AuditService::class, $audit);
        $before = [Product::count(), InventoryBalance::count(), AuditEvent::count()];

        try {
            $this->app->make(CatalogService::class)->saveProduct(new Product, $this->productData(), $this->admin);
            $this->fail('Mandatory audit failure must abort the product transaction.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Controlled audit failure', $exception->getMessage());
        }

        $this->assertSame($before, [Product::count(), InventoryBalance::count(), AuditEvent::count()]);
        $this->assertDatabaseMissing('products', ['sku' => 'TEST-PRODUCT']);
    }

    public function test_product_audit_identifies_actor_and_changed_entity(): void
    {
        $product = $this->createProduct();
        $event = AuditEvent::where('entity_type', $product->getMorphClass())->where('entity_id', $product->id)->sole();

        $this->assertSame($this->admin->id, $event->actor_id);
        $this->assertSame('created', $event->action);
        $this->assertSame('TEST-PRODUCT', $event->after_values['sku']);
        $this->assertNotNull($event->occurred_at);
    }

    public function test_audit_allowlist_discards_passwords_tokens_and_arbitrary_request_fields(): void
    {
        DB::transaction(function (): void {
            $this->app->make(AuditService::class)->record($this->admin, 'test.changed', $this->admin, [], [
                'name' => 'Nombre permitido', 'password' => 'secret-password-sentinel',
                'remember_token' => 'secret-token-sentinel', 'request_body' => ['credential' => 'secret-request-sentinel'],
            ]);
        });
        $event = AuditEvent::where('action', 'test.changed')->sole();

        $this->assertSame(['name' => 'Nombre permitido'], $event->after_values);
        $this->assertStringNotContainsString('secret-', json_encode($event->toArray(), JSON_THROW_ON_ERROR));
    }

    public function test_system_seeder_preserves_custom_configuration_and_revoked_role_permissions(): void
    {
        $company = Company::sole();
        $company->update(['trade_name' => 'Empresa personalizada de pruebas']);
        $role = Role::where('code', 'viewer')->sole();
        $permission = Permission::where('code', 'products.view')->sole();
        $role->permissions()->detach($permission->id);
        $counts = [Role::count(), Permission::count(), Unit::count()];

        $this->seed(SystemSeeder::class);

        $this->assertSame('Empresa personalizada de pruebas', $company->fresh()->trade_name);
        $this->assertFalse($role->permissions()->whereKey($permission->id)->exists());
        $this->assertSame($counts, [Role::count(), Permission::count(), Unit::count()]);
    }

    public function test_repeated_demo_seeding_preserves_product_edits_and_does_not_duplicate_zero_balances(): void
    {
        $this->seed(DemoSeeder::class);
        $product = Product::where('sku', 'PRODUCTO-A')->sole();
        $product->update(['name' => 'Nombre personalizado de pruebas', 'sale_price' => '31.50', 'active' => false]);
        $counts = [Category::count(), Subcategory::count(), Brand::count(), Product::count(), InventoryBalance::count()];

        $this->seed(DemoSeeder::class);

        $this->assertSame($counts, [Category::count(), Subcategory::count(), Brand::count(), Product::count(), InventoryBalance::count()]);
        $this->assertSame('Nombre personalizado de pruebas', $product->fresh()->name);
        $this->assertSame('31.50', $product->fresh()->sale_price);
        $this->assertFalse($product->fresh()->active);
        $this->assertSame(1, $product->inventoryBalances()->count());
        $this->assertFalse(InventoryBalance::where('quantity', '<>', '0.000')->exists());
    }

    public function test_demo_seeder_is_blocked_in_production_before_writing(): void
    {
        $before = [Product::count(), Category::count(), InventoryBalance::count()];
        $environment = $this->app->environment();
        $this->app->instance('env', 'production');

        try {
            $this->app->make(DemoSeeder::class)->run();
            $this->fail('DemoSeeder must reject production.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('DemoSeeder', $exception->getMessage());
        } finally {
            $this->app->instance('env', $environment);
        }

        $this->assertSame($before, [Product::count(), Category::count(), InventoryBalance::count()]);
    }

    private function actor(string $role): User
    {
        $user = new User;
        $user->forceFill([
            'name' => 'Usuario de pruebas '.$role,
            'email' => 'catalog-'.$role.'@example.test',
            'password' => 'Fixture-only-passphrase-2026!',
            'role_id' => Role::where('code', $role)->sole()->id,
            'active' => true,
        ])->save();

        return $user;
    }

    private function productData(array $overrides = []): array
    {
        return array_replace([
            'sku' => 'TEST-PRODUCT', 'barcode' => null, 'name' => 'Producto de pruebas',
            'category_id' => $this->category->id, 'subcategory_id' => null, 'brand_id' => null,
            'unit_id' => $this->unit->id, 'sale_price' => '25.00', 'reference_cost' => '15.00', 'minimum_stock' => '2',
        ], $overrides);
    }

    private function createProduct(array $overrides = []): Product
    {
        return $this->app->make(CatalogService::class)->saveProduct(new Product, $this->productData($overrides), $this->admin);
    }

    /** A nested transaction/savepoint keeps PostgreSQL usable after an expected constraint failure. */
    private function assertPostgresRejects(callable $operation, string $sqlState): void
    {
        try {
            DB::transaction($operation);
            $this->fail('PostgreSQL must reject the invalid write.');
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, $exception->errorInfo[0] ?? (string) $exception->getCode());
        }
    }
}
