<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Subcategory;
use App\Models\Unit;
use App\Services\OrganizationContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DemoSeeder solo puede ejecutarse en entornos local o testing.');
        }

        $this->call(SystemSeeder::class);
        DB::transaction(function (): void {
            $warehouse = app(OrganizationContext::class)->warehouse();
            $general = Category::query()->firstOrCreate(['code' => 'GENERAL'], ['name' => 'Productos generales', 'description' => 'Catálogo ficticio para demostración.', 'active' => true]);
            $bulk = Category::query()->firstOrCreate(['code' => 'GRANEL'], ['name' => 'Productos a granel', 'active' => true]);
            $subcategory = Subcategory::query()->firstOrCreate(['category_id' => $general->getKey(), 'name' => 'Uso cotidiano'], ['active' => true]);
            $brand = Brand::query()->firstOrCreate(['name' => 'Marca ejemplo'], ['description' => 'Marca ficticia sin relación comercial real.', 'active' => true]);
            $unit = Unit::query()->where('code', 'UND')->sole();
            $kg = Unit::query()->where('code', 'KG')->sole();

            $products = [
                ['sku' => 'PRODUCTO-A', 'barcode' => '0000000000011', 'name' => 'Producto de referencia A', 'category_id' => $general->getKey(), 'subcategory_id' => $subcategory->getKey(), 'brand_id' => $brand->getKey(), 'unit_id' => $unit->getKey(), 'sale_price' => '25.00', 'reference_cost' => '15.00', 'minimum_stock' => '5.000', 'presentation' => 'Unidad individual', 'active' => true],
                ['sku' => 'PRODUCTO-B', 'barcode' => null, 'name' => 'Producto genérico sin marca', 'category_id' => $general->getKey(), 'subcategory_id' => null, 'brand_id' => null, 'unit_id' => $unit->getKey(), 'sale_price' => '10.00', 'reference_cost' => '6.50', 'minimum_stock' => '0.000', 'active' => true],
                ['sku' => 'PRODUCTO-KG', 'barcode' => '0000000000028', 'name' => 'Producto fraccionario por kilogramo', 'category_id' => $bulk->getKey(), 'subcategory_id' => null, 'brand_id' => null, 'unit_id' => $kg->getKey(), 'sale_price' => '10.00', 'reference_cost' => '7.25', 'minimum_stock' => '0.500', 'active' => true],
                ['sku' => 'PRODUCTO-INACTIVO', 'barcode' => null, 'name' => 'Producto inactivo de demostración', 'category_id' => $general->getKey(), 'subcategory_id' => null, 'brand_id' => $brand->getKey(), 'unit_id' => $unit->getKey(), 'sale_price' => '0.00', 'reference_cost' => '0.00', 'minimum_stock' => '0.000', 'active' => false],
                ['sku' => 'PRODUCTO-CONCURRENCIA', 'barcode' => null, 'name' => 'Producto reservado para pruebas futuras', 'category_id' => $general->getKey(), 'subcategory_id' => null, 'brand_id' => null, 'unit_id' => $unit->getKey(), 'sale_price' => '25.00', 'reference_cost' => '15.00', 'minimum_stock' => '0.000', 'active' => true],
            ];
            foreach ($products as $attributes) {
                // Both the product and its zero balance belong to this transaction.
                $product = Product::query()->firstOrCreate(['sku' => $attributes['sku']], $attributes);
                InventoryBalance::query()->firstOrCreate(['product_id' => $product->getKey(), 'warehouse_id' => $warehouse->getKey()], ['quantity' => '0.000']);
            }
        });
    }
}
