<?php

namespace App\Models\Concerns;

trait NormalizesCatalogValues
{
    protected static function bootNormalizesCatalogValues(): void
    {
        static::saving(function ($model): void {
            foreach (['name', 'trade_name', 'legal_name', 'code', 'sku', 'barcode', 'abbreviation', 'email', 'tax_id', 'phone', 'presentation', 'location'] as $field) {
                if (! array_key_exists($field, $model->getAttributes()) || ! is_string($model->getAttribute($field))) {
                    continue;
                }

                $value = preg_replace('/\s+/u', ' ', trim($model->getAttribute($field)));
                if (in_array($field, ['code', 'sku'], true)) {
                    $value = mb_strtoupper($value, 'UTF-8');
                }
                if ($field === 'email') {
                    $value = mb_strtolower($value, 'UTF-8');
                }
                $model->setAttribute($field, $value === '' && in_array($field, ['barcode', 'legal_name', 'email', 'tax_id', 'phone', 'presentation', 'location'], true) ? null : $value);
            }
        });
    }
}
