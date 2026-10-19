<?php

namespace App\Models;

use App\Models\Concerns\NormalizesCatalogValues;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use NormalizesCatalogValues;

    protected $fillable = ['trade_name', 'legal_name', 'tax_id', 'address', 'phone', 'email', 'currency', 'display_timezone'];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }
}
