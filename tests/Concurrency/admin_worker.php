<?php

/**
 * M-07 worker. Execute only through run_admin_race.php in the testing database.
 * Arguments contain a fixture ID and private synchronization path, never a password.
 */

use App\Models\User;
use App\Services\AccessService;
use App\Services\AuditService;
use App\Support\TestDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

if (PHP_SAPI !== 'cli' || realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__ || getenv('APP_ENV') !== 'testing') {
    fwrite(STDERR, "M-07 blocked: direct CLI execution with APP_ENV=testing is required.\n");
    exit(2);
}

require dirname(__DIR__, 2).'/vendor/autoload.php';

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

    [$script, $targetId, $runDirectory, $nonce] = array_pad($argv, 4, null);
    if (count($argv) !== 4 || ! is_string($targetId) || ! ctype_digit($targetId) || ! is_string($nonce) || ! preg_match('/\A[a-f0-9]{32}\z/', $nonce)) {
        throw new RuntimeException('invalid_worker_arguments');
    }
    $expectedDirectory = $app->storagePath('runtime/admin-race-'.$nonce);
    if (! is_string($runDirectory) || realpath($runDirectory) !== realpath($expectedDirectory) || ! is_dir($expectedDirectory)) {
        throw new RuntimeException('invalid_barrier_directory');
    }
    $manifest = json_decode(file_get_contents($runDirectory.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
    if (($manifest['nonce'] ?? null) !== $nonce || ! in_array((int) $targetId, $manifest['user_ids'] ?? [], true) || ($manifest['deadline'] ?? 0) < time()) {
        throw new RuntimeException('invalid_barrier_manifest');
    }
    if (User::count() !== 2) {
        throw new RuntimeException('unexpected_users_in_test_database');
    }
    $user = User::findOrFail((int) $targetId);
    if (! $user->active || $user->role->code !== 'admin' || ! str_starts_with($user->email, 'race-'.$nonce.'-')) {
        throw new RuntimeException('invalid_fixture_identity');
    }

    DB::select("select set_config('application_name', ?, false)", ['sicovi_admin_race_'.$nonce.'_'.$targetId]);
    DB::statement("SET lock_timeout = '10s'");
    DB::statement("SET statement_timeout = '15s'");
    $backendPid = (int) DB::selectOne('select pg_backend_pid() AS pid')->pid;
    $readyFile = $runDirectory.'/worker-'.$targetId.'.ready';
    file_put_contents($readyFile.'.tmp', json_encode(['backend_pid' => $backendPid], JSON_THROW_ON_ERROR), LOCK_EX);
    rename($readyFile.'.tmp', $readyFile);
    while (! is_file($runDirectory.'/start')) {
        if (time() >= $manifest['deadline']) {
            throw new RuntimeException('barrier_timeout');
        }
        usleep(10000);
    }

    // A controlled delay inside the test audit keeps the real administration lock
    // held long enough to observe the second PostgreSQL connection waiting for it.
    $audit = new class extends AuditService
    {
        public function record(?User $actor, string $action, Model $entity, array $before = [], array $after = [], ?string $reason = null): void
        {
            if ($action === 'users.deactivated') {
                DB::select('select pg_sleep(1)');
            }
            parent::record($actor, $action, $entity, $before, $after, $reason);
        }
    };
    (new AccessService($audit))->setUserActive($user, $user, false);
    echo json_encode(['worker_id' => (int) $targetId, 'result' => 'deactivated'], JSON_THROW_ON_ERROR).PHP_EOL;
    exit(0);
} catch (ValidationException $exception) {
    if (array_keys($exception->errors()) === ['administration']) {
        echo json_encode(['worker_id' => isset($targetId) ? (int) $targetId : null, 'result' => 'last_administrator_protected'], JSON_THROW_ON_ERROR).PHP_EOL;
        exit(10);
    }
    fwrite(STDERR, json_encode(['result' => 'unexpected_validation_error', 'fields' => array_keys($exception->errors())], JSON_THROW_ON_ERROR).PHP_EOL);
    exit(1);
} catch (Throwable $exception) {
    // Never print exception messages, SQL bindings, environment values or credentials.
    fwrite(STDERR, json_encode(['result' => 'worker_blocked_or_failed', 'error_type' => $exception::class], JSON_THROW_ON_ERROR).PHP_EOL);
    exit(1);
}
