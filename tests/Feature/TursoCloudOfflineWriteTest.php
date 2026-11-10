<?php

declare(strict_types=1);

/*
NOTE:
    This test exercises the Turso Cloud-only offline-writes flavor
    (`DB_OFFLINE_WRITES=true` / driver `offlineWrites: true` WITHOUT
    `sqldOfflineMode: true`). Unlike the sqld self-hosted flavor, it needs
    a real Turso Cloud database — there is no way to emulate it locally.

    Set these environment variables to enable the test (skipped by default):

      TURSO_CLOUD_DB_URL=libsql://<db>-<org>.turso.io
      TURSO_CLOUD_DB_TOKEN=<token>

    The test creates and drops its own table (proof_cloud_ow_*); it always
    attempts to clean up afterwards, even on failure.
*/

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Should the Turso Cloud test be skipped? Skips when credentials are unset.
 */
function shouldSkipCloudTests(): bool
{
    return ! (getenv('TURSO_CLOUD_DB_URL') && getenv('TURSO_CLOUD_DB_TOKEN'));
}

test('turso cloud offline-write flavor: journaled writes push to remote via sync()', function () {
    if (shouldSkipCloudTests()) {
        $this->markTestSkipped('TURSO_CLOUD_DB_URL / TURSO_CLOUD_DB_TOKEN not set — needs a real Turso Cloud database');
    }

    $url = getenv('TURSO_CLOUD_DB_URL');
    $token = getenv('TURSO_CLOUD_DB_TOKEN');

    $orig = config('database.connections.libsql');
    $table = 'proof_cloud_ow_' . bin2hex(random_bytes(6));
    $replicaFile = test_database_path('turso_cloud_offline_' . bin2hex(random_bytes(6)) . '.db');

    $cloudConfig = array_merge($orig, [
        'database' => null,
        'url' => $url,
        'authToken' => $token,
    ]);

    try {
        // 1) Connect the offline-writes replica against Turso Cloud
        config()->set('database.connections.libsql', array_merge($orig, [
            'database' => $replicaFile,
            'url' => $url,
            'authToken' => $token,
            'syncInterval' => 0,
            'read_your_writes' => true,
            'sqldOfflineMode' => false,
            'offlineWrites' => true,
        ]));
        DB::purge('libsql');
        DB::setDefaultConnection('libsql');

        $mode = DB::connection()->getConnectionMode();
        expect($mode)->toBe('remote_replica');

        // 2) Bootstrap: pull remote state into the local replica BEFORE writing
        DB::connection('libsql')->sync();

        // 3) Local writes go to the replica file (Turso Cloud flavor has no
        //    pending-operations journal API like sqld_offline_mode does)
        Schema::create($table, function ($table) {
            $table->id();
            $table->string('name');
        });

        DB::table($table)->insert([
            ['name' => 'cloud-offline-write-a'],
            ['name' => 'cloud-offline-write-b'],
        ]);

        $localCount = DB::table($table)->count();
        expect($localCount)->toBe(2);

        // 4) sync() pushes the journaled frames to Turso Cloud
        DB::connection('libsql')->sync();

        // 5) Verify from the remote side (plain remote connection, not the replica)
        config()->set('database.connections.libsql', $cloudConfig);
        DB::purge('libsql');
        DB::setDefaultConnection('libsql');
        expect(DB::table($table)->count())->toBe(2);
    } finally {
        // 6) Cleanup: drop through the offline-writes path and push,
        //    so the cloud database is left untouched.
        config()->set('database.connections.libsql', array_merge($orig, [
            'database' => $replicaFile,
            'url' => $url,
            'authToken' => $token,
            'syncInterval' => 0,
            'read_your_writes' => true,
            'sqldOfflineMode' => false,
            'offlineWrites' => true,
        ]));
        DB::purge('libsql');
        DB::setDefaultConnection('libsql');

        try {
            DB::statement("DROP TABLE IF EXISTS {$table}");
            DB::connection('libsql')->sync();
        } catch (\Throwable $e) {
            // best-effort cleanup — table may not exist on remote
            error_log('cloud offline-write test cleanup failed: ' . $e->getMessage());
        } finally {
            // belt-and-braces: plain remote DROP, in case the push was partial
            config()->set('database.connections.libsql', $cloudConfig);
            DB::purge('libsql');
            DB::setDefaultConnection('libsql');

            try {
                DB::statement("DROP TABLE IF EXISTS {$table}");
            } catch (\Throwable $e) {
                error_log('cloud offline-write test final remote cleanup failed: ' . $e->getMessage());
            } finally {
                DB::purge('libsql');
                config()->set('database.connections.libsql', $orig);
                DB::setDefaultConnection('libsql');
                DB::purge('libsql');

                foreach (glob($replicaFile . '*') ?: [] as $f) {
                    @unlink($f);
                }
            }
        }
    }
})->group('TursoCloudOfflineWriteTest', 'FeatureTest');
