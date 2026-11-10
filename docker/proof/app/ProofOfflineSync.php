<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ReflectionProperty;
use RuntimeException;
use Throwable;

final class ProofOfflineSync extends Command
{
    protected $signature = 'proof:offline-sync';

    protected $description = 'Offline-sync proof (after the outage). The orchestrator has reconnected app ↔ sqld; sync() must drain the journal. Prints PROOF-OFFLINE-SYNC: {json}';

    private int $passed = 0;

    private int $failed = 0;

    /** @var array<string, array{ok: bool, asserts: int, error: string|null}> */
    private array $phases = [];

    public function handle(): int
    {
        $this->line('');
        $this->line('<options=bold>Turso Driver for Laravel — offline-sync proof (after the outage)</>');
        $this->line('');

        $this->phase('offline_reconnected', 'The outage is over: the app can reach the server', function () {
            $this->useOfflineConnection();
            $this->assert(
                'extension reports the connection is back online (isOnline === true)',
                fn () => $this->extensionClient()->isOnline() === true
            );
        });

        $this->phase('offline_drain', 'sync() pushes the journaled writes to the server', function () {
            DB::connection()->sync();

            $this->assert(
                'journal drained (pending operations == 0)',
                fn () => $this->extensionClient()->getPendingOperationsCount() === 0
            );
            $this->assert(
                'driver reads the three rows back',
                fn () => DB::table('jour')->count() === 3
            );
        });

        $this->phase('offline_server_drained', 'The second, independent Hrana client sees the journaled rows', function () {
            $rows = null;

            try {
                $rows = $this->hranaRows('SELECT k, v FROM jour ORDER BY k');
            } catch (Throwable $e) {
                $this->recordAssert(false, 'second client reads jour — ' . $e->getMessage());
            }

            $expected = [
                ['k' => 'proof:offline-1', 'v' => 'offline-value-1'],
                ['k' => 'proof:offline-2', 'v' => 'offline-value-2'],
                ['k' => 'proof:offline-3', 'v' => 'offline-value-3'],
            ];

            $this->assert('server has exactly three journaled rows', fn () => $rows !== null && count($rows) === 3);
            $this->assert('rows arrive key+value exact, in order, nothing duplicated', fn () => $rows === $expected);
        });

        $report = [
            'driver' => 'tursodatabase/turso-driver-laravel',
            'phase' => 'offline-sync',
            'phases' => $this->phases,
            'ok' => $this->passed,
            'fail' => $this->failed,
            'all_ok' => $this->failed === 0,
        ];

        file_put_contents('storage/app/proof-offline-sync-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->line('');
        $this->line('PROOF-OFFLINE-SYNC: ' . json_encode(['ok' => $this->passed, 'fail' => $this->failed, 'all_ok' => $this->failed === 0]));

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

    /** @return array<int, array{k: string, v: string}> */
    private function hranaRows(string $sql): array
    {
        $payload = json_encode(['requests' => [
            ['type' => 'execute', 'stmt' => ['sql' => $sql]],
            ['type' => 'close'],
        ]], JSON_UNESCAPED_UNICODE);

        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 5,
            'ignore_errors' => true,
        ]]);

        $raw = @file_get_contents('http://sqld:8080/v2/pipeline', false, $context);
        if ($raw === false) {
            throw new RuntimeException('sqld HTTP request failed');
        }

        $decoded = json_decode($raw, true);
        $rows = $decoded['results'][0]['response']['result']['rows'] ?? null;
        if (! is_array($rows)) {
            throw new RuntimeException('unexpected Hrana response: ' . $raw);
        }

        return array_map(static fn (array $row): array => [
            'k' => (string) ($row[0]['value'] ?? ''),
            'v' => (string) ($row[1]['value'] ?? ''),
        ], $rows);
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
