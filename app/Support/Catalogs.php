<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Unit;

class Catalogs
{
    public static function get(string $key): array
    {
        return match ($key) {
            'categories' => [Category::class, 'Categorías'],
            'subcategories' => [Subcategory::class, 'Subcategorías'],
            'brands' => [Brand::class, 'Marcas'],
            'units' => [Unit::class, 'Unidades'],
            default => abort(404),
        };
    }

    public static function normalize(string $value): string
    {
        return preg_replace('/\s+/u', ' ', trim($value));
    }
}
