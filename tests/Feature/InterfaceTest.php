<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Support\Catalogs;
use Database\Seeders\DemoSeeder;
use Tests\DatabaseTestCase;

class InterfaceTest extends DatabaseTestCase
{
    public function test_all_administration_screens_render_with_real_catalogs(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = new User;
        $admin->forceFill(['name' => 'Revisión de interfaz', 'email' => 'interfaz@example.test', 'password' => 'SoloPruebas!2026', 'role_id' => Role::where('code', 'admin')->sole()->id, 'active' => true])->save();
        $this->actingAs($admin);
        $product = Product::firstOrFail();
        $routes = ['/', '/account', '/products', '/products/create', '/products/'.$product->id, '/products/'.$product->id.'/edit', '/users', '/users/create', '/users/'.$admin->id.'/edit', '/roles', '/roles/'.$admin->role_id.'/edit', '/settings'];
        foreach (['categories', 'subcategories', 'brands', 'units'] as $catalog) {
            $class = Catalogs::get($catalog)[0];
            $routes[] = '/catalogs/'.$catalog;
            $routes[] = '/catalogs/'.$catalog.'/create';
            $routes[] = '/catalogs/'.$catalog.'/'.$class::firstOrFail()->id.'/edit';
        }
        foreach ($routes as $route) {
            $this->get($route)->assertOk()->assertSee('SICOVI')->assertDontSee('Stack trace');
        }
    }

    public function test_catalog_product_and_filter_arrays_are_rejected_as_validation_errors(): void
    {
        $admin = new User;
        $admin->forceFill(['name' => 'Validación', 'email' => 'validacion@example.test', 'password' => 'SoloPruebas!2026', 'role_id' => Role::where('code', 'admin')->sole()->id, 'active' => true])->save();
        $this->actingAs($admin)->postJson('/catalogs/brands', ['name' => ['invalido'], 'active' => '1'])->assertUnprocessable();
        $this->postJson('/products', ['name' => ['invalido'], 'sku' => ['invalido'], 'barcode' => ['invalido']])->assertUnprocessable();
        $this->getJson('/products?q[]=invalido')->assertUnprocessable();
        $this->getJson('/catalogs/brands?q[]=invalido')->assertUnprocessable();
    }
}
