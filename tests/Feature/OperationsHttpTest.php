<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\DatabaseTestCase;

class OperationsHttpTest extends DatabaseTestCase
{
    private User $admin;

    private int $warehouseId;

    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->actor('admin');
        $this->warehouseId = (int) DB::table('warehouses')->value('id');
        $categoryId = DB::table('categories')->insertGetId(['code' => 'HTTP-OPS', 'name' => 'Operaciones HTTP']);
        $unitId = (int) DB::table('units')->where('code', 'UND')->value('id');
        $this->productId = DB::table('products')->insertGetId([
            'sku' => 'HTTP-OPS-1', 'name' => 'Producto HTTP', 'category_id' => $categoryId, 'unit_id' => $unitId,
            'sale_price' => '10.00', 'reference_cost' => '5.00', 'minimum_stock' => '0.000', 'active' => true,
        ]);
        DB::table('inventory_balances')->insert(['product_id' => $this->productId, 'warehouse_id' => $this->warehouseId, 'quantity' => '0.000']);
    }

    public function test_temporary_product_route_is_not_available(): void
    {
        $this->get('/producto-seguro')->assertNotFound();
    }

    public function test_supplier_routes_validate_permissions_and_record_audit_events(): void
    {
        $this->actingAs($this->admin);
        $this->from(route('suppliers.create'))
            ->post(route('suppliers.store'), ['name' => ''])
            ->assertRedirect(route('suppliers.create'))
            ->assertSessionHasErrors('name');

        $response = $this->post(route('suppliers.store'), ['name' => 'Proveedor HTTP', 'tax_id' => 'HTTP-1']);
        $supplierId = (int) DB::table('suppliers')->where('name', 'Proveedor HTTP')->value('id');
        $response->assertRedirect(route('suppliers.show', $supplierId));

        $this->put(route('suppliers.update', $supplierId), ['name' => 'Proveedor HTTP Editado', 'tax_id' => 'HTTP-1']);
        $this->patch(route('suppliers.deactivate', $supplierId));

        $this->assertDatabaseHas('audit_events', ['action' => 'suppliers.created']);
        $this->assertDatabaseHas('audit_events', ['action' => 'suppliers.updated']);
        $this->assertDatabaseHas('audit_events', ['action' => 'suppliers.deactivated']);
        $this->assertFalse((bool) DB::table('suppliers')->where('id', $supplierId)->value('active'));
    }

    public function test_purchase_routes_create_confirm_and_void_with_inventory_changes(): void
    {
        $this->actingAs($this->admin);
        $supplierId = DB::table('suppliers')->insertGetId(['name' => 'Proveedor de compras', 'tax_id' => 'HTTP-2']);

        $response = $this->post(route('purchases.store'), [
            'supplier_id' => $supplierId, 'document_date' => '2026-10-27',
            'lines' => [['product_id' => $this->productId, 'quantity' => '2', 'unit_cost' => '5.00']],
        ]);
        $purchaseId = (int) DB::table('purchases')->latest('id')->value('id');
        $response->assertRedirect(route('purchases.show', $purchaseId));
        $itemId = (int) DB::table('purchase_items')->where('purchase_id', $purchaseId)->value('id');

        $this->post(route('purchases.confirm', $purchaseId), [
            'idempotency_key' => 'http-confirm-1',
            'lines' => [['product_id' => $this->productId, 'quantity' => '2', 'unit_cost' => '5.00']],
        ])->assertRedirect(route('purchases.show', $purchaseId));
        $this->assertDatabaseHas('audit_events', ['action' => 'purchases.created']);
        $this->assertSame('2.000', (string) DB::table('inventory_balances')->where('product_id', $this->productId)->value('quantity'));

        $this->post(route('purchases.store'), [
            'supplier_id' => $supplierId, 'document_date' => '2026-10-27',
            'lines' => [['product_id' => $this->productId, 'quantity' => '2', 'unit_cost' => '5.00']],
        ]);
        $secondPurchaseId = (int) DB::table('purchases')->latest('id')->value('id');
        $this->from(route('purchases.show', $secondPurchaseId))
            ->post(route('purchases.confirm', $secondPurchaseId), [
                'idempotency_key' => 'http-confirm-1',
                'lines' => [['product_id' => $this->productId, 'quantity' => '2', 'unit_cost' => '5.00']],
            ])
            ->assertRedirect(route('purchases.show', $secondPurchaseId))
            ->assertSessionHasErrors('idempotency_key');

        $this->post(route('purchases.void', $purchaseId), ['idempotency_key' => 'http-void-1', 'reason' => 'Prueba HTTP'])
            ->assertRedirect(route('purchases.show', $purchaseId));
        $this->assertSame('0.000', (string) DB::table('inventory_balances')->where('product_id', $this->productId)->value('quantity'));
        $this->assertSame(1, DB::table('inventory_movements')->where('purchase_item_id', $itemId)->where('movement_type', 'reversal')->count());
    }

    private function actor(string $role): User
    {
        $user = new User;
        $user->forceFill([
            'name' => 'HTTP '.$role, 'email' => 'http-'.$role.'@example.test',
            'password' => 'Fixture-only-passphrase-2026!', 'role_id' => Role::where('code', $role)->sole()->id, 'active' => true,
        ])->save();

        return $user;
    }
}
