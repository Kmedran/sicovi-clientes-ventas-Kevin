<?php

namespace App\Models;

use App\Models\Concerns\NormalizesCatalogValues;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use NormalizesCatalogValues;

    protected $fillable = ['company_id', 'code', 'name', 'address', 'phone', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }
}
