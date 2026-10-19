<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Subcategory;
use App\Models\Unit;
use App\Services\CatalogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('products.view');
        $request->validate(['q' => ['nullable', 'string', 'max:150'], 'active' => ['nullable', 'in:0,1'], 'category_id' => ['nullable', 'integer']]);
        $products = Product::with(['category', 'unit', 'brand'])->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'ilike', '%'.mb_substr($request->string('q'), 0, 150).'%')->orWhere('sku', 'ilike', '%'.mb_substr($request->string('q'), 0, 50).'%')->orWhere('barcode', (string) $request->string('q'))))
            ->when(in_array($request->query('active'), ['0', '1'], true), fn ($q) => $q->where('active', $request->query('active')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', (int) $request->query('category_id')))
            ->orderBy('name')->paginate(15)->withQueryString();

        return view('products.index', ['products' => $products, 'categories' => Category::orderBy('name')->get()]);
    }

    public function create()
    {
        Gate::authorize('create', Product::class);

        return $this->form(new Product(['minimum_stock' => '0', 'sale_price' => '0.00', 'reference_cost' => '0.00']));
    }

    public function edit(Product $product)
    {
        Gate::authorize('update', $product);

        return $this->form($product);
    }

    private function form(Product $product)
    {
        return view('products.form', ['product' => $product, 'categories' => Category::orderBy('name')->get(), 'subcategories' => Subcategory::with('category')->orderBy('name')->get(), 'brands' => Brand::orderBy('name')->get(), 'units' => Unit::orderBy('name')->get()]);
    }

    public function show(Product $product)
    {
        Gate::authorize('view', $product);
        $product->load(['category', 'subcategory', 'brand', 'unit', 'inventoryBalances.warehouse']);

        return view('products.show', compact('product'));
    }

    public function store(ProductRequest $request, CatalogService $service)
    {
        $product = $service->saveProduct(new Product, $request->validated(), $request->user());

        return to_route('products.show', $product)->with('status', 'Producto creado correctamente.');
    }

    public function update(ProductRequest $request, Product $product, CatalogService $service)
    {
        $service->saveProduct($product, $request->validated(), $request->user());

        return to_route('products.show', $product)->with('status', 'Producto actualizado correctamente.');
    }

    public function deactivate(Request $request, Product $product, CatalogService $service)
    {
        Gate::authorize('deactivate', $product);
        $service->toggleProduct($product, $request->user());

        return back()->with('status', 'Estado del producto actualizado.');
    }
}
