<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class DemoAccess
{
    public function allowed(): bool
    {
        $environment = app()->environment();
        if (! in_array($environment, ['local', 'testing'], true) || ! config('sicovi.demo_accounts', false)) {
            return false;
        }

        try {
            $connection = DB::connection();
            if ($connection->getDriverName() !== 'pgsql' || ! in_array($connection->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)) {
                return false;
            }
            $identity = $connection->selectOne('SELECT current_database() AS database, current_schema() AS schema');

            return $identity->database === ($environment === 'testing' ? 'sicovi_test' : 'sicovi_local') && $identity->schema === 'sicovi';
        } catch (Throwable) {
            return false;
        }
    }

    public function assertAllowed(): void
    {
        if (! $this->allowed()) {
            throw new RuntimeException('Las cuentas de demostración requieren SICOVI_DEMO_ACCOUNTS=true, entorno local/testing y la base PostgreSQL local correspondiente con esquema sicovi.');
        }
    }
}
