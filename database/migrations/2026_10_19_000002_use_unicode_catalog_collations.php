<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $columns = [
        'branches' => ['code' => 30, 'name' => 150], 'warehouses' => ['code' => 30, 'name' => 150],
        'roles' => ['code' => 40, 'name' => 100], 'users' => ['email' => 254],
        'categories' => ['code' => 30, 'name' => 120], 'subcategories' => ['name' => 120],
        'brands' => ['name' => 120], 'units' => ['code' => 30, 'name' => 100],
        'products' => ['sku' => 50, 'barcode' => 80, 'name' => 180],
    ];

    public function up(): void
    {
        // pg_c_utf8 is built into PostgreSQL 17 / UTF8 on Windows and Linux.
        // Refuse ambiguous existing records; never merge or delete business data.
        foreach ($this->columns as $table => $columns) {
            foreach ($columns as $column => $length) {
                if ($table === 'products' && $column === 'name') {
                    continue;
                }
                $scope = $table === 'subcategories' ? 'category_id, ' : '';
                $duplicates = DB::select("SELECT 1 FROM {$table} WHERE {$column} IS NOT NULL GROUP BY {$scope} lower(regexp_replace(btrim({$column}), '\\s+', ' ', 'g') COLLATE pg_catalog.pg_c_utf8) HAVING count(*) > 1 LIMIT 1");
                if ($duplicates) {
                    throw new RuntimeException("Existen duplicados equivalentes en {$table}.{$column}. Revise los registros antes de actualizar; no se modificaron filas.");
                }
            }
        }
        $this->apply('pg_catalog.pg_c_utf8');
    }

    public function down(): void
    {
        $this->apply('"default"');
    }

    private function apply(string $collation): void
    {
        foreach ($this->columns as $table => $columns) {
            foreach ($columns as $column => $length) {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE varchar({$length}) COLLATE {$collation}");
            }
        }
    }
};
