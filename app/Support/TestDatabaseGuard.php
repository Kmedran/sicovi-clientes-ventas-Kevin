<?php

namespace App\Support;

use Illuminate\Database\Connection;
use RuntimeException;

class TestDatabaseGuard
{
    public function assertSafe(Connection $connection): void
    {
        if ($connection->getDriverName() !== 'pgsql') {
            throw new RuntimeException('Pruebas bloqueadas: se requiere PostgreSQL y sicovi_test.');
        }
        $identity = $connection->selectOne('SELECT current_database() AS database, current_schema() AS schema');
        if ($identity->database !== 'sicovi_test' || $identity->schema !== 'sicovi') {
            throw new RuntimeException('Pruebas bloqueadas: la conexión efectiva no es sicovi_test / sicovi.');
        }
    }
}
