<?php

namespace Tests;

use App\Support\TestDatabaseGuard;
use Database\Seeders\SystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

abstract class DatabaseTestCase extends TestCase
{
    use RefreshDatabase;

    public function refreshDatabase(): void
    {
        // This override runs before RefreshDatabase can execute migrate:fresh.
        app(TestDatabaseGuard::class)->assertSafe(DB::connection());
        $this->beforeRefreshingDatabase();
        if ($this->usingInMemoryDatabases()) {
            $this->restoreInMemoryDatabase();
        }
        $this->refreshTestDatabase();
        $this->afterRefreshingDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(SystemSeeder::class);
    }
}
