<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;
use Turso\Driver\Laravel\Database\LibSQLConnection;

final class ProofOfflineCloud extends Command
{
    protected $signature = 'proof:offline-cloud {--report=storage/app/proof-offline-cloud-report.json : Where to write the JSON report}';

    protected $description = 'Turso Cloud offline-writes flavor proof (DB_OFFLINE_WRITES + offlineWrites, no sqld_offline_mode). Requires a real Turso Cloud database — always runs against it, never a local server. Prints PROOF-OFFLINE-CLOUD: {json}';

    private int $passed = 0;

    private int $failed = 0;

    /** @var array<string, array{ok: bool, asserts: int, error: string|null}> */
    private array $phases = [];

    public function handle(): int
    {
        $this->line('');
        $this->line('<options=bold>Turso Driver for Laravel — Turso Cloud offline-writes proof</>');
        $this->line('');

        $url = getenv('TURSO_CLOUD_DB_URL');
        $token = getenv('TURSO_CLOUD_DB_TOKEN');

        if (! $url || ! $token) {
            $this->line('<fg=yellow>⚠</> TURSO_CLOUD_DB_URL / TURSO_CLOUD_DB_TOKEN not set — proof requires a real Turso Cloud database.');
            $this->line('Set them and rerun. This phase cannot run against sqld or any local emulation.');

            $report = [
                'driver' => 'tursodatabase/turso-driver-laravel',
                'phase' => 'offline-cloud',
                'phases' => [],
                'ok' => 0,
                'fail' => 1,
                'all_ok' => false,
                'skipped' => true,
            ];
            file_put_contents($this->option('report'), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $this->line('PROOF-OFFLINE-CLOUD: ' . json_encode(['ok' => 0, 'fail' => 1, 'all_ok' => false, 'skipped' => true]));

            return self::FAILURE;
        }

        $orig = config('database.connections.libsql');
        $table = 'proof_cloud_ow_' . bin2hex(random_bytes(4));
        $replicaFile = '/tmp/turso-cloud-offline-proof.db';

        try {
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

            $this->phase('cloud_bootstrap', 'Fresh replica bootstraps from the remote before any write', function () {
                $this->assert('connection class is LibSQLConnection', fn () => DB::connection() instanceof LibSQLConnection);
                $this->assert('connection mode is remote_replica', fn () => DB::connection()->getConnectionMode() === 'remote_replica');
                $this->performs('initial sync (bootstrap remote -> local)', fn () => DB::connection()->sync());
            });

            $this->phase('cloud_journal', 'Writes land in the local replica', function () use ($table) {
                $this->performs('create proof table', fn () => Schema::create($table, function ($table) {
                    $table->id();
                    $table->string('name');
                }));
                $this->performs('insert two rows (journaled locally)', fn () => DB::table($table)->insert([
                    ['name' => 'cloud-proof-a'],
                    ['name' => 'cloud-proof-b'],
                ]));
                $this->assert('local journal holds the two rows', fn () => DB::table($table)->count() === 2);
            });

            $this->phase('cloud_push', 'sync() pushes the journal to Turso Cloud', function () use ($table) {
                $this->performs('sync (push local -> remote)', fn () => DB::connection()->sync());
            });

            $this->phase('cloud_remote_read', 'A plain remote connection reads the journaled rows', function () use ($orig, $url, $token, $table) {
                config()->set('database.connections.libsql', array_merge($orig, [
                    'database' => null,
                    'url' => $url,
                    'authToken' => $token,
                ]));
                DB::purge('libsql');
                DB::setDefaultConnection('libsql');

                $this->assert('remote-only connection is remote mode', fn () => DB::connection()->getConnectionMode() === 'remote');
                $this->assert('remote sees the two journaled rows', fn () => DB::table($table)->count() === 2);
            });

            $this->phase('cloud_cleanup', 'Cleanup pushes a drop; remote is left untouched', function () use ($orig, $url, $token, $table, $replicaFile) {
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
                $this->performs('drop proof table locally', fn () => DB::statement('DROP TABLE IF EXISTS ' . $table));
                $this->performs('sync (push drop)', fn () => DB::connection()->sync());

                config()->set('database.connections.libsql', array_merge($orig, [
                    'database' => null,
                    'url' => $url,
                    'authToken' => $token,
                ]));
                DB::purge('libsql');
                DB::setDefaultConnection('libsql');
                $this->assert('proof table is gone from Turso Cloud', function () use ($table) {
                    $remaining = DB::select("SELECT name FROM sqlite_master WHERE type='table'");

                    return ! in_array($table, array_column($remaining, 'name'), true);
                });
            });
        } catch (Throwable $e) {
            $this->failed++;
            $this->line("  <fg=red>✗</> phase aborted — {$e->getMessage()}");
            $this->phases['abort'] = ['ok' => false, 'asserts' => 0, 'error' => $e->getMessage()];
        } finally {
            DB::purge('libsql');
            config()->set('database.connections.libsql', $orig);
            DB::setDefaultConnection('libsql');

            foreach (glob($replicaFile . '*') ?: [] as $f) {
                @unlink($f);
            }
        }

        $report = [
            'driver' => 'tursodatabase/turso-driver-laravel',
            'phase' => 'offline-cloud',
            'phases' => $this->phases,
            'ok' => $this->passed,
            'fail' => $this->failed,
            'all_ok' => $this->failed === 0,
        ];
        file_put_contents($this->option('report'), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->line('');
        $this->line('PROOF-OFFLINE-CLOUD: ' . json_encode(['ok' => $this->passed, 'fail' => $this->failed, 'all_ok' => $this->failed === 0]));

        return $this->failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @param Closure(): mixed $condition */
    private function assert(string $label, Closure $condition): void
    {
        try {
            $result = $condition();
            if ($result === true || $result === 1) {
                $this->recordAssert(true, $label);

                return;
            }
            $this->recordAssert(false, "{$label} — got " . var_export($result, true));
        } catch (Throwable $e) {
            $this->recordAssert(false, "{$label} — " . $e->getMessage());
        }
    }

    /** @param Closure(): mixed $action */
    private function performs(string $label, Closure $action): void
    {
        try {
            $action();
            $this->recordAssert(true, $label);
        } catch (Throwable $e) {
            $this->recordAssert(false, "{$label} — " . $e->getMessage());
        }
    }

    private function recordAssert(bool $ok, string $label): void
    {
        if ($ok) {
            $this->passed++;
            $this->line("  <fg=green>✓</> {$label}");
        } else {
            $this->failed++;
            $this->line("  <fg=red>✗</> {$label}");
        }
    }

    private function phase(string $name, string $title, Closure $fn): void
    {
        $this->line("<options=bold>▸ {$name}</> — {$title}");

        $beforeFail = $this->failed;
        $beforeCount = $this->passed + $this->failed;
        $error = null;

        try {
            $fn();
        } catch (Throwable $e) {
            $error = $e->getMessage();
            $this->failed++;
            $this->line("  <fg=red>✗</> phase aborted — {$error}");
        }

        $count = ($this->passed + $this->failed) - $beforeCount;
        $this->phases[$name] = [
            'ok' => $this->failed === $beforeFail && $error === null,
            'asserts' => $count,
            'error' => $error,
        ];
    }
}
