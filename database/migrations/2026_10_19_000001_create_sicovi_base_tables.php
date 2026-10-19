<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('SICOVI requiere PostgreSQL y el esquema sicovi previamente preparado.');
        }

        if (DB::selectOne('select current_schema() as name')->name !== 'sicovi') {
            throw new RuntimeException('El esquema activo debe ser sicovi. Revise DB_SCHEMA y la preparación de PostgreSQL.');
        }

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('trade_name', 150);
            $table->string('legal_name', 200)->nullable();
            $table->string('tax_id', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 254)->nullable();
            $table->char('currency', 3)->default('GTQ');
            $table->string('display_timezone', 64)->default('America/Guatemala');
            $table->timestampsTz();
        });
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name', 150);
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('active')->default(true);
            $table->timestampsTz();
        });
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name', 150);
            $table->string('location', 255)->nullable();
            $table->boolean('active')->default(true);
            $table->timestampsTz();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40);
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestampsTz();
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 150);
            $table->string('group', 50);
            $table->timestampsTz();
        });
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->foreignId('permission_id')->constrained()->restrictOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('email', 254);
            $table->string('password');
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->boolean('active')->default(true);
            $table->timestampTz('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestampsTz();
        });
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('payload');
            $table->integer('last_activity')->index();
        });
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestampsTz();
        });
        Schema::create('subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->unique(['id', 'category_id']);
        });
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestampsTz();
        });
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30);
            $table->string('name', 100);
            $table->string('abbreviation', 15);
            $table->unsignedSmallInteger('decimal_places')->default(0);
            $table->boolean('active')->default(true);
            $table->timestampsTz();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 50);
            $table->string('barcode', 80)->nullable();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('subcategory_id')->nullable();
            $table->foreignId('brand_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('presentation', 180)->nullable();
            $table->decimal('sale_price', 14, 2)->default('0');
            $table->decimal('reference_cost', 14, 2)->default('0');
            $table->decimal('minimum_stock', 14, 3)->default('0');
            $table->string('location', 255)->nullable();
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->foreign(['subcategory_id', 'category_id'])->references(['id', 'category_id'])->on('subcategories')->restrictOnDelete();
            $table->index(['category_id', 'active']);
            $table->index('subcategory_id');
            $table->index('name');
        });
        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3)->default('0');
            $table->timestampsTz();
            $table->unique(['product_id', 'warehouse_id']);
        });
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 100);
            $table->string('entity_type', 100);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->jsonb('before_values')->default('{}');
            $table->jsonb('after_values')->default('{}');
            $table->text('reason')->nullable();
            $table->timestampTz('occurred_at')->useCurrent();
            $table->index(['actor_id', 'occurred_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['action', 'occurred_at']);
        });

        // V1 has exactly one operational organization; IDs remain generated.
        foreach (['companies', 'branches', 'warehouses'] as $table) {
            DB::statement("CREATE UNIQUE INDEX {$table}_singleton ON {$table} ((true))");
        }
        DB::statement("ALTER TABLE companies ADD CONSTRAINT companies_currency_check CHECK (currency = 'GTQ')");
        DB::statement('ALTER TABLE branches ADD CONSTRAINT branches_operational_check CHECK (active = true)');
        DB::statement('ALTER TABLE warehouses ADD CONSTRAINT warehouses_operational_check CHECK (active = true)');
        DB::statement('ALTER TABLE units ADD CONSTRAINT units_decimal_places_check CHECK (decimal_places BETWEEN 0 AND 3)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_nonnegative_check CHECK (sale_price >= 0 AND reference_cost >= 0 AND minimum_stock >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_barcode_check CHECK (barcode IS NULL OR length(btrim(barcode)) > 0)');
        DB::statement('ALTER TABLE inventory_balances ADD CONSTRAINT inventory_balances_nonnegative_check CHECK (quantity >= 0)');

        foreach (['branches' => ['code', 'name'], 'warehouses' => ['code', 'name'], 'roles' => ['code', 'name'], 'categories' => ['code', 'name'], 'brands' => ['name'], 'units' => ['code', 'name']] as $table => $columns) {
            foreach ($columns as $column) {
                $this->normalizedUnique($table, $column);
            }
        }
        $this->normalizedUnique('users', 'email');
        $this->normalizedUnique('products', 'sku');
        DB::statement('CREATE UNIQUE INDEX products_barcode_normalized_unique ON products (lower(btrim(barcode))) WHERE barcode IS NOT NULL');
        DB::statement("CREATE UNIQUE INDEX subcategories_category_name_normalized_unique ON subcategories (category_id, lower(regexp_replace(btrim(name), '\\s+', ' ', 'g')))");

        foreach (['companies' => ['trade_name'], 'branches' => ['code', 'name'], 'warehouses' => ['code', 'name'], 'roles' => ['code', 'name'], 'permissions' => ['code', 'name'], 'users' => ['name', 'email'], 'categories' => ['code', 'name'], 'subcategories' => ['name'], 'brands' => ['name'], 'units' => ['code', 'name', 'abbreviation'], 'products' => ['sku', 'name']] as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_{$column}_not_blank CHECK (length(btrim({$column})) > 0)");
            }
        }
    }

    private function normalizedUnique(string $table, string $column): void
    {
        DB::statement("CREATE UNIQUE INDEX {$table}_{$column}_normalized_unique ON {$table} (lower(regexp_replace(btrim({$column}), '\\s+', ' ', 'g')))");
    }

    public function down(): void
    {
        foreach (['audit_events', 'inventory_balances', 'products', 'units', 'brands', 'subcategories', 'categories', 'sessions', 'users', 'role_permissions', 'permissions', 'roles', 'warehouses', 'branches', 'companies'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
