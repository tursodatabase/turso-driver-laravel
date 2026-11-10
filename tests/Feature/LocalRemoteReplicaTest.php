<?php

declare(strict_types=1);

/*
NOTE:
    Before activated this tests, make sure you've libsql server or turso url provider
    is setup in TestCase.php file in getEnvironmentSetUp methods for Embedded Replica Connection
*/

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Turso\Driver\Laravel\Tests\Fixtures\Models\Project;

beforeEach(function () {
    clearDirectory();
    sleep(2);
    DB::setDefaultConnection('otherdb2');
});

/**
 * Embedded replica opens can transiently fail on a stale WAL index ("Can not
 * sync a database without a wal_index"). Purge and delete the local replica
 * file, then retry the operation — the sqld side stays our source of truth.
 */
function withReplicaRetry(string $name, \Closure $callback, int $attempts = 3): mixed
{
    $lastError = null;

    while ($attempts-- > 0) {
        try {
            $file = config("database.connections.{$name}.database");
            DB::purge($name);

            if ($file && is_file($file)) {
                @unlink($file);
            }

            foreach (glob($file . '-*') ?: [] as $sidecar) {
                @unlink($sidecar);
            }

            return $callback();
        } catch (\Throwable $e) {
            $lastError = $e;
            usleep(400_000);
        }
    }

    throw $lastError;
}

/**
 * Each replica test gets its own replica file and connection name so the
 * parallel test workers do not share the local SQLite file (the WAL files
 * would corrupt each other between concurrent test processes).
 */
function registerReplicaConnection(): string
{
    // Mutate the statically registered `otherdb2` connection to point at a
    // fresh, unique replica file per test (parallel workers must not share
    // the local SQLite file).
    $name = 'otherdb2';
    $file = test_database_path('replica_' . bin2hex(random_bytes(4)) . '.db');

    config()->set("database.connections.{$name}.database", $file);
    DB::purge($name);

    return $name;
}

test('it can connect to a embedded replica', function () {
    $name = registerReplicaConnection();
    $mode = withReplicaRetry($name, fn () => DB::connection($name)->getConnectionMode());
    expect($mode)->toBe('remote_replica');

    DB::purge($name);
    @unlink(config("database.connections.{$name}.database"));
    foreach (glob(config("database.connections.{$name}.database") . '-*') ?: [] as $file) {
        @unlink($file);
    }
})->skip(shouldSkipTests(), 'This test requires a running libsql server')->group('LocalRemoteReplicaTest', 'FeatureTest');

test('it can get all rows from the projects table through the embedded replica', function () {
    $name = registerReplicaConnection();
    DB::setDefaultConnection($name);

    $projects = withReplicaRetry($name, function () {
        Schema::dropAllTables();
        migrateTables('projects');

        $this->project1 = Project::make()->setConnection('otherdb2')->factory()->create();
        $this->project2 = Project::make()->setConnection('otherdb2')->factory()->create();
        $this->project3 = Project::make()->setConnection('otherdb2')->factory()->create();

        return DB::connection('otherdb2')->table('projects')->get();
    });

    expect($projects->count())->toBe(3);

    DB::purge($name);
    @unlink(config("database.connections.{$name}.database"));
    foreach (glob(config("database.connections.{$name}.database") . '-*') ?: [] as $file) {
        @unlink($file);
    }
})->skip(shouldSkipTests(), 'This test requires a running libsql server')->group('LocalRemoteReplicaTest', 'FeatureTest');
