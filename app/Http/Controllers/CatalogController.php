<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogRequest;
use App\Models\Category;
use App\Services\CatalogService;
use App\Support\Catalogs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CatalogController extends Controller
{
    public function index(Request $request, string $catalog)
    {
        Gate::authorize($catalog.'.view');
        $request->validate(['q' => ['nullable', 'string', 'max:150'], 'active' => ['nullable', 'in:0,1']]);
        [$class, $title] = Catalogs::get($catalog);
        $records = $class::query()->when($catalog === 'subcategories', fn ($q) => $q->with('category'))
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'ilike', '%'.mb_substr($request->string('q'), 0, 150).'%'))
            ->when(in_array($request->query('active'), ['0', '1'], true), fn ($q) => $q->where('active', $request->query('active')))
            ->orderBy('name')->paginate(15)->withQueryString();

        return view('catalogs.index', compact('records', 'catalog', 'title'));
    }

    public function create(string $catalog)
    {
        return $this->form($catalog);
    }

    public function edit(string $catalog, int $id)
    {
        return $this->form($catalog, $id);
    }

    private function form(string $catalog, ?int $id = null)
    {
        Gate::authorize($catalog.'.manage');
        [$class, $title] = Catalogs::get($catalog);
        $record = $id ? $class::findOrFail($id) : new $class(['active' => true]);
        $categories = Category::orderBy('name')->get();

        return view('catalogs.form', compact('catalog', 'title', 'record', 'categories'));
    }

    public function store(CatalogRequest $request, string $catalog, CatalogService $service)
    {
        return $this->persist($request, $catalog, $service);
    }

    public function update(CatalogRequest $request, string $catalog, int $id, CatalogService $service)
    {
        return $this->persist($request, $catalog, $service, $id);
    }

    private function persist(CatalogRequest $request, string $catalog, CatalogService $service, ?int $id = null)
    {
        [$class] = Catalogs::get($catalog);
        $service->saveCatalog($catalog, $id ? $class::findOrFail($id) : new $class, $request->validated(), $request->user());

        return to_route('catalogs.index', $catalog)->with('status', 'Catálogo guardado correctamente.');
    }
}
