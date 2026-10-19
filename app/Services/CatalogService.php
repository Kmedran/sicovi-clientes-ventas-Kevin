<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Subcategory;
use App\Models\Unit;
use App\Models\User;
use App\Support\Catalogs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatalogService
{
    public function __construct(private AuditService $audit, private DecimalService $decimals, private OrganizationContext $organization) {}

    public function saveCatalog(string $key, Model $model, array $data, User $actor): Model
    {
        return DB::transaction(function () use ($key, $model, $data, $actor) {
            DB::select('SELECT pg_advisory_xact_lock(74126002)');
            if ($model->exists) {
                $model = $model->newQuery()->lockForUpdate()->findOrFail($model->id);
            }
            $before = $model->getAttributes();
            $duplicate = $model->newQuery()->whereRaw("lower(regexp_replace(btrim(name), '\\s+', ' ', 'g')) = ?", [mb_strtolower(Catalogs::normalize($data['name']))])->when($model->exists, fn ($q) => $q->where('id', '<>', $model->id));
            if ($key === 'subcategories') {
                $duplicate->where('category_id', $data['category_id']);
            }
            if ($duplicate->exists()) {
                $this->invalid('name', 'Ya existe un registro con este nombre.');
            }
            if ($key === 'subcategories') {
                $parent = Category::findOrFail($data['category_id']);
                if (! $parent->active && (! $model->exists || $model->category_id != $parent->id || (! $model->active && $data['active']))) {
                    $this->invalid('category_id', 'Seleccione una categoría activa.');
                }
                if ($model->exists && $model->category_id != $parent->id && Product::where('subcategory_id', $model->id)->exists()) {
                    $this->invalid('category_id', 'La subcategoría tiene productos vinculados. Conserve su categoría.');
                }
            }
            if ($key === 'units' && $model->exists && $model->decimal_places != $data['decimal_places'] && Product::where('unit_id', $model->id)->exists()) {
                $this->invalid('decimal_places', 'La precisión de una unidad utilizada no se puede modificar.');
            }
            if (! $data['active'] && $model->exists) {
                $column = match ($key) {
                    'categories' => 'category_id', 'subcategories' => 'subcategory_id', 'brands' => 'brand_id', 'units' => 'unit_id'
                };
                if (Product::where($column, $model->id)->where('active', true)->exists()) {
                    $this->invalid('active', 'Hay productos activos vinculados. Reasígnelos o desactívelos antes.');
                }
                if ($key === 'categories' && Subcategory::where('category_id', $model->id)->where('active', true)->exists()) {
                    $this->invalid('active', 'Desactive primero las subcategorías activas.');
                }
            }
            $model->fill($data)->save();
            $this->audit->record($actor, $before ? 'updated' : 'created', $model, $before, $model->getAttributes());

            return $model;
        });
    }

    public function saveProduct(Product $product, array $data, User $actor): Product
    {
        return DB::transaction(function () use ($product, $data, $actor) {
            DB::select('SELECT pg_advisory_xact_lock(74126002)');
            $new = ! $product->exists;
            if (! $new) {
                $product = Product::lockForUpdate()->findOrFail($product->id);
            }
            $before = $product->getAttributes();
            $this->validateRelations($product, $data);
            $unit = Unit::findOrFail($data['unit_id']);
            foreach (['sale_price', 'reference_cost', 'minimum_stock'] as $field) {
                if (! array_key_exists($field, $data)) {
                    continue;
                }
                try {
                    $data[$field] = $field === 'minimum_stock' ? $this->decimals->quantity($data[$field], $unit->decimal_places) : $this->decimals->money($data[$field]);
                } catch (\InvalidArgumentException $e) {
                    $this->invalid($field, $e->getMessage());
                }
            }
            if (! $new && $product->unit_id != $unit->id) {
                foreach ($product->inventoryBalances as $balance) {
                    try {
                        $this->decimals->quantity($balance->quantity, $unit->decimal_places);
                    } catch (\InvalidArgumentException) {
                        $this->invalid('unit_id', 'El saldo actual no es compatible con esta unidad.');
                    }
                }
            }
            if ($new) {
                $data['active'] = true;
                $data['reference_cost'] ??= '0.00';
            }
            $product->fill($data)->save();
            if ($new) {
                InventoryBalance::create(['product_id' => $product->id, 'warehouse_id' => $this->organization->warehouse()->id, 'quantity' => '0.000']);
            }
            $this->audit->record($actor, $new ? 'created' : 'updated', $product, $before, $product->getAttributes());

            return $product;
        });
    }

    public function toggleProduct(Product $product, User $actor): void
    {
        DB::transaction(function () use ($product, $actor) {
            DB::select('SELECT pg_advisory_xact_lock(74126002)');
            $product = Product::lockForUpdate()->findOrFail($product->id);
            $before = $product->getAttributes();
            if (! $product->active) {
                $this->validateRelations($product, $before, true);
            }
            $product->active = ! $product->active;
            $product->save();
            $this->audit->record($actor, $product->active ? 'activated' : 'deactivated', $product, $before, $product->getAttributes());
        });
    }

    private function validateRelations(Product $product, array $data, bool $reactivating = false): void
    {
        foreach (['category_id' => Category::class, 'subcategory_id' => Subcategory::class, 'brand_id' => Brand::class, 'unit_id' => Unit::class] as $field => $class) {
            if (empty($data[$field])) {
                continue;
            }
            $relation = $class::findOrFail($data[$field]);
            if (! $relation->active && (! $product->exists || $relation->id != $product->$field || $reactivating)) {
                $this->invalid($field, 'Seleccione un registro activo.');
            }
        }
        if (! empty($data['subcategory_id']) && Subcategory::findOrFail($data['subcategory_id'])->category_id != $data['category_id']) {
            $this->invalid('subcategory_id', 'La subcategoría no pertenece a la categoría seleccionada.');
        }
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
