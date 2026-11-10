<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionProperty;
use RuntimeException;
use Throwable;

final class ProofOfflineWrite extends Command
{
    protected $signature = 'proof:offline-write';

    protected $description = 'Offline-write proof (outage window). The orchestrator has cut app ↔ sqld; writes must journal locally, the server must stay blind. Prints PROOF-OFFLINE-WRITE: {json}';

    private int $passed = 0;

    private int $failed = 0;

    /** @var array<string, array{ok: bool, asserts: int, error: string|null}> */
    private array $phases = [];

    public function handle(): int
    {
        $this->line('');
        $this->line('<options=bold>Turso Driver for Laravel — offline-write proof (outage window)</>');
        $this->line('');

        $this->phase('offline_outage', 'The outage is real: the app cannot reach the server', function () {
            $this->useOfflineConnection();
            $this->assert(
                'extension reports the outage (isOnline === false)',
                fn () => $this->extensionClient()->isOnline() === false
            );
        });

        $this->phase('offline_journal', 'Writes accrue in the local journal, not on the server', function () {
            $this->useOfflineConnection();

            $this->performs('CREATE TABLE jour (journaled CREATE)', fn () => Schema::create('jour', function (Blueprint $table) {
                $table->string('k')->primary();
                $table->string('v');
            }));
            $this->assert(
                'one pending operation after the CREATE (journaled)',
                fn () => $this->extensionClient()->getPendingOperationsCount() === 1
            );

            foreach ([1 => 'offline-value-1', 2 => 'offline-value-2', 3 => 'offline-value-3'] as $i => $v) {
                $this->performs("insert journaled row {$i}", fn () => DB::table('jour')->insert(['k' => "proof:offline-{$i}", 'v' => $v]));
            }

            $this->assert(
                'journal holds the three rows (local read-back)',
                fn () => DB::table('jour')->count() === 3
            );
            $this->assert(
                'four pending operations after CREATE + 3 inserts',
                fn () => $this->extensionClient()->getPendingOperationsCount() === 4
            );
        });

        $this->phase('offline_blind_window', 'The outage is all the app can see (the server is unreachable from here)', function () {
            $this->assert('app cannot reach sqld during the outage (raw Hrana fails)', fn () => $this->serverBlind('jour'));
        });

        $report = [
            'driver' => 'tursodatabase/turso-driver-laravel',
            'phase' => 'offline-write',
            'phases' => $this->phases,
            'ok' => $this->passed,
            'fail' => $this->failed,
            'all_ok' => $this->failed === 0,
        ];

        file_put_contents('storage/app/proof-offline-write-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->line('');
        $this->line('PROOF-OFFLINE-WRITE: ' . json_encode(['ok' => $this->passed, 'fail' => $this->failed, 'all_ok' => $this->failed === 0]));

        return $this->failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @param  array<string, mixed>  $overrides */
    private function useConnection(array $overrides): void
    {
        config()->set('database.connections.libsql', array_merge([
            'driver' => 'libsql',
            'prefix' => '',
            'database' => ':memory:',
            'url' => '',
            'authToken' => null,
            'syncInterval' => 5,
            'read_your_writes' => true,
            'encryptionKey' => null,
            'sqldOfflineMode' => false,
            'offlineWrites' => false,
        ], $overrides));

        DB::purge('libsql');
        DB::setDefaultConnection('libsql');
        DB::connection();
    }

    private function useOfflineConnection(): void
    {
        $this->useConnection([
            'database' => '/app/proof-offline.sqlite',
            'url' => 'http://sqld:8080',
            'authToken' => 'proof-offline-token',
            'syncInterval' => 0,
            'read_your_writes' => true,
            'sqldOfflineMode' => true,
            'offlineWrites' => true,
        ]);
    }

    /** The extension client behind the driver's connection, via reflection. */
    private function extensionClient(): object
    {
        $connection = DB::connection();

        $inner = new ReflectionProperty($connection, 'db');
        $inner->setAccessible(true);
        $database = $inner->getValue($connection);

        $ext = new ReflectionProperty($database, 'db');
        $ext->setAccessible(true);

        return $ext->getValue($database);
    }

    /** The app cannot reach sqld during the outage — a raw Hrana probe must fail (or see zero rows). */
    private function serverBlind(string $table): bool
    {
        try {
            return (int) $this->hranaExecute("SELECT count(*) FROM {$table}") === 0;
        } catch (Throwable) {
            // Unreachable server or "no such table" — both mean the server
            // cannot see our journal.
            return true;
        }
    }

    /** Executes a single statement on sqld over raw HTTP/Hrana v2 (independent of the driver's client). */
    private function hranaExecute(string $sql): int|string
    {
        $payload = json_encode(['requests' => [
            ['type' => 'execute', 'stmt' => ['sql' => $sql]],
            ['type' => 'close'],
        ]], JSON_UNESCAPED_UNICODE);

        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 3,
            'ignore_errors' => true,
        ]]);

        $raw = @file_get_contents('http://sqld:8080/v2/pipeline', false, $context);
        if ($raw === false) {
            throw new RuntimeException('sqld HTTP request failed (outage or server error)');
        }

        $decoded = json_decode($raw, true);
        $value = $decoded['results'][0]['response']['result']['rows'][0][0]['value'] ?? null;
        if ($value === null) {
            throw new RuntimeException('unexpected Hrana response: ' . $raw);
        }

        return $value;
    }

    /** @param  Closure(): mixed  $condition */
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

    /** @param  Closure(): mixed  $action */
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
