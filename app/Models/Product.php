<?php

namespace App\Models;

use App\Models\Concerns\NormalizesCatalogValues;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use NormalizesCatalogValues;

    protected $fillable = ['sku', 'barcode', 'name', 'description', 'category_id', 'subcategory_id', 'brand_id', 'unit_id', 'presentation', 'sale_price', 'reference_cost', 'minimum_stock', 'location', 'active'];

    protected $hidden = ['reference_cost'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'sale_price' => 'decimal:2', 'reference_cost' => 'decimal:2', 'minimum_stock' => 'decimal:3'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function inventoryBalances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class);
    }
}
