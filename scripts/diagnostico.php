<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = DB::connection();
$identity = $db->selectOne('SELECT current_database() AS database, current_schema() AS schema, version() AS server');
$counts = [];
foreach (['companies', 'branches', 'warehouses', 'roles', 'permissions', 'users', 'categories', 'subcategories', 'brands', 'units', 'products', 'inventory_balances', 'audit_events'] as $table) {
    $counts[$table] = $db->table($table)->count();
}
echo json_encode(['database' => $identity->database, 'schema' => $identity->schema, 'server' => $identity->server, 'counts' => $counts, 'stock_sum' => (string) $db->table('inventory_balances')->sum('quantity'), 'negative_balances' => $db->table('inventory_balances')->where('quantity', '<', 0)->count()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
