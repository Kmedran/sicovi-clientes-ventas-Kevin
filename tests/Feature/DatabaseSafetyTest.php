<?php

namespace Tests\Feature;

use App\Support\TestDatabaseGuard;
use Illuminate\Database\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** These tests use connection doubles only; they never open or clean a database. */
class DatabaseSafetyTest extends TestCase
{
    public function test_guard_accepts_only_the_effective_postgresql_test_database_and_schema(): void
    {
        $connection = $this->connection('pgsql', 'sicovi_test', 'sicovi');

        (new TestDatabaseGuard)->assertSafe($connection);

        $this->addToAssertionCount(1);
    }

    #[DataProvider('unsafeDrivers')]
    public function test_guard_rejects_other_engines_without_querying_them(string $driver): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('getDriverName')->willReturn($driver);
        $connection->expects($this->never())->method('selectOne');
        $connection->expects($this->never())->method('statement');
        $connection->expects($this->never())->method('unprepared');

        $this->expectException(RuntimeException::class);
        (new TestDatabaseGuard)->assertSafe($connection);
    }

    public static function unsafeDrivers(): array
    {
        return ['SQLite' => ['sqlite'], 'MySQL' => ['mysql'], 'SQL Server' => ['sqlsrv']];
    }

    #[DataProvider('unsafeIdentities')]
    public function test_guard_rejects_a_wrong_effective_database_or_schema(string $database, ?string $schema): void
    {
        $connection = $this->connection('pgsql', $database, $schema);

        $this->expectException(RuntimeException::class);
        (new TestDatabaseGuard)->assertSafe($connection);
    }

    public static function unsafeIdentities(): array
    {
        return [
            'development database' => ['sicovi_local', 'sicovi'],
            'verification database' => ['sicovi_verificacion', 'sicovi'],
            'similar database name' => ['sicovi_test_copy', 'sicovi'],
            'public schema' => ['sicovi_test', 'public'],
            'no effective schema' => ['sicovi_test', null],
        ];
    }

    private function connection(string $driver, string $database, ?string $schema): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('getDriverName')->willReturn($driver);
        // Never trust a configured name in place of PostgreSQL's actual identity.
        $connection->expects($this->never())->method('getDatabaseName');
        $connection->expects($this->once())->method('selectOne')
            ->with($this->callback(fn (string $sql): bool => str_starts_with(strtolower(ltrim($sql)), 'select ')
                && str_contains(strtolower($sql), 'current_database()')
                && str_contains(strtolower($sql), 'current_schema()')
            ))
            ->willReturn((object) compact('database', 'schema'));
        $connection->expects($this->never())->method('statement');
        $connection->expects($this->never())->method('unprepared');

        return $connection;
    }
}
