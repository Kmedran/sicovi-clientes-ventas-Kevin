<?php

namespace App\Models;

use App\Models\Concerns\NormalizesCatalogValues;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use NormalizesCatalogValues;

    protected $fillable = ['code', 'name', 'abbreviation', 'decimal_places', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'decimal_places' => 'integer'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
