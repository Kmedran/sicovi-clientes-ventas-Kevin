<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\OrganizationContext;
use Database\Seeders\SystemSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\DatabaseTestCase;

class BaseDataTest extends DatabaseTestCase
{
    public function test_schema_runs_on_the_effective_isolated_postgresql_connection_and_uses_timezone_aware_instants(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $actual = DB::selectOne('select current_database() as database, current_schema() as schema');
        $this->assertSame('sicovi_test', $actual->database);
        $this->assertSame('sicovi', $actual->schema);

        $columns = DB::table('information_schema.columns')->where('table_schema', 'sicovi')
            ->whereIn('table_name', ['users', 'products', 'audit_events'])
            ->whereIn('column_name', ['created_at', 'updated_at', 'last_login_at', 'occurred_at'])
            ->get();

        $this->assertCount(6, $columns);
        foreach ($columns as $column) {
            $this->assertSame('timestamp with time zone', $column->data_type, $column->table_name.'.'.$column->column_name);
        }
    }

    #[DataProvider('organizationTables')]
    public function test_database_prevents_a_second_organization_record(string $table): void
    {
        $context = app(OrganizationContext::class);
        $attributes = match ($table) {
            'companies' => ['trade_name' => 'Segunda empresa'],
            'branches' => ['company_id' => $context->company()->id, 'code' => 'SECOND', 'name' => 'Segunda sucursal'],
            'warehouses' => ['branch_id' => $context->branch()->id, 'code' => 'SECOND', 'name' => 'Segunda bodega'],
        };

        $this->assertPostgresRejects(fn () => DB::table($table)->insert($attributes), '23505');
        $this->assertSame(1, DB::table($table)->count());
    }

    public static function organizationTables(): array
    {
        return [['companies'], ['branches'], ['warehouses']];
    }

    public function test_unique_operational_branch_and_warehouse_cannot_be_disabled_through_sql(): void
    {
        foreach (['branches', 'warehouses'] as $table) {
            $this->assertPostgresRejects(fn () => DB::table($table)->update(['active' => false]), '23514');
            $this->assertTrue((bool) DB::table($table)->value('active'));
        }
    }

    #[DataProvider('normalizedIdentifiers')]
    public function test_database_rejects_equivalent_codes_email_sku_and_optional_barcode(string $kind): void
    {
        $product = $this->sampleProduct();
        $user = $this->sampleUser();
        $operation = match ($kind) {
            'category_code' => fn () => DB::table('categories')->insert(['code' => ' base-cat ', 'name' => 'Nombre distinto']),
            'unit_code' => fn () => DB::table('units')->insert(['code' => ' und ', 'name' => 'Nombre distinto', 'abbreviation' => 'x']),
            'email' => fn () => DB::table('users')->insert(['name' => 'Otro usuario', 'username' => 'other-user', 'email' => ' BASE-USER@EXAMPLE.TEST ', 'password' => 'not-a-real-account', 'role_id' => $user->role_id]),
            'sku' => fn () => DB::table('products')->insert(['sku' => ' base-product ', 'name' => 'Otro producto', 'category_id' => $product->category_id, 'unit_id' => $product->unit_id]),
            'barcode' => fn () => DB::table('products')->insert(['sku' => 'OTHER-SKU', 'barcode' => ' 000012345 ', 'name' => 'Otro producto', 'category_id' => $product->category_id, 'unit_id' => $product->unit_id]),
        };

        $this->assertPostgresRejects($operation, $kind === 'email' ? '23514' : '23505');
        $this->assertSame(1, Product::count());
        $this->assertSame(1, User::count());
    }

    public static function normalizedIdentifiers(): array
    {
        return [['category_code'], ['unit_code'], ['email'], ['sku'], ['barcode']];
    }

    public function test_postgresql_rejects_a_new_uppercase_or_untrimmed_email_even_without_a_duplicate(): void
    {
        $this->assertPostgresRejects(fn () => DB::table('users')->insert([
            'name' => 'Correo no normalizado', 'username' => 'nuevo', 'email' => '  NUEVO@EXAMPLE.TEST ',
            'password' => 'invalid-fixture-never-persisted', 'role_id' => Role::where('code', 'seller')->sole()->id,
        ]), '23514');
        $this->assertSame(0, User::count());
    }

    public function test_negative_stock_and_duplicate_product_warehouse_rows_are_rejected_by_postgresql(): void
    {
        $product = $this->sampleProduct();
        $warehouse = app(OrganizationContext::class)->warehouse();
        $balance = InventoryBalance::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => '0.000']);

        $this->assertPostgresRejects(fn () => DB::table('inventory_balances')->where('id', $balance->id)->update(['quantity' => '-0.001']), '23514');
        $this->assertPostgresRejects(fn () => DB::table('inventory_balances')->insert(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => '0.000']), '23505');

        $this->assertSame('0.000', $balance->fresh()->quantity);
        $this->assertSame(1, $product->inventoryBalances()->count());
    }

    public function test_normal_update_migrate_and_system_seed_preserve_existing_user_product_and_organization(): void
    {
        $product = $this->sampleProduct();
        $product->update(['name' => 'Nombre modificado antes de actualizar', 'sale_price' => '42.75', 'active' => false]);
        $user = $this->sampleUser();
        $user->update(['name' => 'Nombre de usuario conservado']);
        $company = Company::sole();
        $company->update(['trade_name' => 'Organización configurada por el administrador', 'phone' => '5555-1234']);
        $passwordHash = $user->getRawOriginal('password');
        $before = [User::count(), Product::count(), Company::count()];

        // The incremental update uses migrate on the already installed schema.
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->seed(SystemSeeder::class);

        $this->assertSame($before, [User::count(), Product::count(), Company::count()]);
        $this->assertSame('Nombre modificado antes de actualizar', $product->fresh()->name);
        $this->assertSame('42.75', $product->fresh()->sale_price);
        $this->assertFalse($product->fresh()->active);
        $this->assertSame('Nombre de usuario conservado', $user->fresh()->name);
        $this->assertSame($passwordHash, $user->fresh()->getRawOriginal('password'));
        $this->assertSame('Organización configurada por el administrador', $company->fresh()->trade_name);
        $this->assertSame('5555-1234', $company->fresh()->phone);
    }

    private function sampleProduct(): Product
    {
        $category = Category::create(['code' => 'BASE-CAT', 'name' => 'Categoría base de prueba']);

        return Product::create([
            'sku' => 'BASE-PRODUCT', 'barcode' => '000012345', 'name' => 'Producto base de prueba',
            'category_id' => $category->id, 'unit_id' => Unit::where('code', 'UND')->sole()->id,
            'sale_price' => '25.00', 'reference_cost' => '15.00', 'minimum_stock' => '0.000',
        ]);
    }

    private function sampleUser(): User
    {
        $user = new User;
        $user->forceFill([
            'name' => 'Usuario base de prueba', 'email' => 'base-user@example.test',
            'password' => 'Fixture-only-passphrase-2026!', 'role_id' => Role::where('code', 'admin')->sole()->id, 'active' => true,
        ])->save();

        return $user;
    }

    private function assertPostgresRejects(callable $operation, string $sqlState): void
    {
        try {
            DB::transaction($operation);
            $this->fail('PostgreSQL debe rechazar la escritura inválida.');
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, $exception->errorInfo[0] ?? (string) $exception->getCode());
        }
    }
}
