<?php

/**
 * M-07 real PostgreSQL race: two administrators try to deactivate themselves.
 * Requires APP_ENV=testing, the isolated sicovi_test/sicovi database and zero users.
 * It never deletes data: fixtures and sanitized runtime evidence remain afterwards.
 */

use App\Models\AuditEvent;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessService;
use App\Support\TestDatabaseGuard;
use Database\Seeders\SystemSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

if (PHP_SAPI !== 'cli' || realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__ || getenv('APP_ENV') !== 'testing') {
    fwrite(STDERR, "M-07 blocked: direct CLI execution with APP_ENV=testing is required.\n");
    exit(2);
}

require dirname(__DIR__, 2).'/vendor/autoload.php';

$workers = [];
$stage = 'bootstrap';
try {
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    if ($app->configurationIsCached()) {
        throw new RuntimeException('cached_configuration');
    }
    $app->loadEnvironmentFrom('.env.testing');
    $app->make(Kernel::class)->bootstrap();
    if (! $app->environment('testing') || config('database.default') !== 'pgsql') {
        throw new RuntimeException('invalid_testing_environment');
    }
    app(TestDatabaseGuard::class)->assertSafe(DB::connection());
    $stage = 'empty_users_guard';
    if (User::count() !== 0) {
        throw new RuntimeException('users_table_must_be_empty');
    }

    $stage = 'system_roles_guard';
    if (Role::count() === 0) {
        app(SystemSeeder::class)->run();
    }
    $role = Role::where('code', 'admin')->where('active', true)->first();
    if (! $role || count(array_diff(AccessService::RECOVERY_PERMISSIONS, $role->permissions()->pluck('code')->all())) !== 0) {
        throw new RuntimeException('administrator_role_requires_existing_recovery_permissions');
    }

    $stage = 'php_runtime_probe';
    // Preserve the verified CLI configuration/PHPRC, adding PDO PostgreSQL only
    // when it was supplied to the parent by -d and is absent from that ini file.
    $phpCommand = [PHP_BINARY];
    if (is_string($loadedIni = php_ini_loaded_file())) {
        array_push($phpCommand, '-c', $loadedIni);
    }
    $probe = new Process([...$phpCommand, '-r', 'exit(extension_loaded("pdo_pgsql") ? 0 : 3);'], dirname(__DIR__, 2));
    $probe->setTimeout(10)->run();
    if ($probe->getExitCode() === 3) {
        array_push($phpCommand, '-d', 'extension=pdo_pgsql');
    } elseif ($probe->getExitCode() !== 0) {
        throw new RuntimeException('worker_php_runtime_unavailable');
    }

    $stage = 'fixture_creation';
    $nonce = bin2hex(random_bytes(16));
    $password = bin2hex(random_bytes(24)).'aA1!';
    $access = app(AccessService::class);
    $users = [];
    foreach ([1, 2] as $number) {
        $users[] = $access->createAdministrator([
            'name' => 'Administrador temporal M07 '.$number,
            'email' => 'race-'.$nonce.'-'.$number.'@example.test',
            'password' => $password,
        ]);
    }
    unset($password);
    $userIds = array_map(fn (User $user) => (int) $user->id, $users);
    $runDirectory = $app->storagePath('runtime/admin-race-'.$nonce);
    if (! mkdir($runDirectory, 0700, true) && ! is_dir($runDirectory)) {
        throw new RuntimeException('private_runtime_directory_unavailable');
    }
    $deadline = time() + 30;
    file_put_contents($runDirectory.'/manifest.json', json_encode(['nonce' => $nonce, 'user_ids' => $userIds, 'deadline' => $deadline], JSON_THROW_ON_ERROR), LOCK_EX);

    $stage = 'worker_startup';
    foreach ($userIds as $id) {
        $worker = new Process([...$phpCommand, __DIR__.'/admin_worker.php', (string) $id, $runDirectory, $nonce], dirname(__DIR__, 2), ['APP_ENV' => 'testing']);
        $worker->setTimeout(30);
        $worker->start();
        $workers[$id] = $worker;
    }
    $backendPids = [];
    while (count($backendPids) !== 2) {
        foreach ($workers as $id => $worker) {
            $worker->checkTimeout();
            $readyFile = $runDirectory.'/worker-'.$id.'.ready';
            if (is_file($readyFile)) {
                $ready = json_decode(file_get_contents($readyFile), true, flags: JSON_THROW_ON_ERROR);
                $backendPids[$id] = (int) ($ready['backend_pid'] ?? 0);
            } elseif (! $worker->isRunning()) {
                throw new RuntimeException('worker_failed_before_barrier');
            }
        }
        if (time() >= $deadline) {
            throw new RuntimeException('worker_readiness_timeout');
        }
        usleep(10000);
    }
    if (in_array(0, $backendPids, true) || count(array_unique($backendPids)) !== 2) {
        throw new RuntimeException('workers_require_distinct_postgresql_connections');
    }

    $stage = 'concurrent_administration_changes';
    file_put_contents($runDirectory.'/start', 'start', LOCK_EX);
    $lockWaitObserved = false;
    do {
        $running = false;
        foreach ($workers as $worker) {
            $worker->checkTimeout();
            $running = $worker->isRunning() || $running;
        }
        $locks = DB::table('pg_catalog.pg_locks')->where('locktype', 'advisory')->whereIn('pid', array_values($backendPids))->get(['pid', 'granted']);
        if ($locks->contains(fn ($lock) => $lock->granted === true) && $locks->contains(fn ($lock) => $lock->granted === false)) {
            $lockWaitObserved = true;
        }
        if (time() >= $deadline) {
            throw new RuntimeException('administration_race_timeout');
        }
        if ($running) {
            usleep(20000);
        }
    } while ($running);

    $stage = 'final_database_assertions';
    $outcomes = [];
    foreach ($workers as $id => $worker) {
        $output = json_decode(trim($worker->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        $outcomes[] = ['worker_id' => $id, 'exit_code' => $worker->getExitCode(), 'result' => $output['result'] ?? 'missing_result'];
    }
    $exitCodes = array_column($outcomes, 'exit_code');
    sort($exitCodes);
    $activeAdmins = User::whereIn('id', $userIds)->where('active', true)->whereHas('role', fn ($query) => $query->where('code', 'admin')->where('active', true))->count();
    $inactiveUsers = User::whereIn('id', $userIds)->where('active', false)->count();
    $auditSuccesses = AuditEvent::where('entity_type', (new User)->getMorphClass())->whereIn('entity_id', $userIds)->where('action', 'users.deactivated')->count();
    if ($exitCodes !== [0, 10] || $activeAdmins !== 1 || $inactiveUsers !== 1 || $auditSuccesses !== 1 || User::count() !== 2 || ! $lockWaitObserved) {
        throw new RuntimeException('administration_race_invariant_failed');
    }
    $summary = [
        'case' => 'M-07', 'result' => 'passed', 'database' => 'sicovi_test', 'schema' => 'sicovi',
        'distinct_postgresql_connections' => count($backendPids), 'lock_wait_observed' => $lockWaitObserved,
        'active_administrators' => $activeAdmins, 'inactive_users' => $inactiveUsers, 'audit_successes' => $auditSuccesses,
        'workers' => $outcomes, 'fixtures_retained' => true,
        'controlled_test_delay_seconds' => 1,
    ];
    file_put_contents($runDirectory.'/result.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    foreach ($workers as $worker) {
        if ($worker->isRunning()) {
            $worker->stop(1);
        }
    }
    // Sanitized diagnostics deliberately exclude messages, commands, SQL and env.
    fwrite(STDERR, json_encode(['case' => 'M-07', 'result' => 'blocked_or_failed', 'stage' => $stage, 'error_type' => $exception::class, 'fixtures_retained' => isset($users) && count($users) > 0], JSON_THROW_ON_ERROR).PHP_EOL);
    exit(1);
}
