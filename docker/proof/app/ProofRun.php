<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LibSQL;
use RuntimeException;
use Throwable;
use Turso\Driver\Laravel\LibSQLDriverServiceProvider;

final class ProofRun extends Command
{
    protected $signature = 'proof:run {--report=storage/app/proof-report.json : Where to write the JSON report}';

    protected $description = 'Isolated proof of usage for the Turso Driver for Laravel. Runs assert phases and prints a PROOF: {json} line';

    private int $passed = 0;

    private int $failed = 0;

    /** @var array<string, array{ok: bool, asserts: int, error: string|null}> */
    private array $phases = [];

    public function handle(): int
    {
        $this->line('');
        $this->line('<options=bold>Turso Driver for Laravel — proof of usage</>');
        $this->line('');

        $this->phase('health', 'Application boots with the driver installed', function () {
            $this->assert('libsql extension is loaded (php -m)', fn () => extension_loaded('libsql_php') || extension_loaded('libsql'));
            $this->assert('LibSQL class is available', fn () => class_exists(LibSQL::class));
            $this->assert('driver service provider is registered', fn () => $this->getLaravel()->getProvider(LibSQLDriverServiceProvider::class) !== null);
        });

        $this->phase('memory_local', 'In-memory and local file connections round-trip data', function () {
            $this->useConnection(['database' => ':memory:']);
            $this->createNotesTable();
            $this->crudCycle('memory');

            @unlink(database_path('proof-local.sqlite'));
            $this->useConnection(['database' => 'proof-local.sqlite']);
            $this->createNotesTable();
            $this->crudCycle('local file');
        });

        $this->phase('remote', 'Remote connection against a live libSQL server', function () {
            $this->useConnection([
                'database' => null,
                'url' => 'http://sqld:8080',
                'authToken' => 'proof-token',
            ]);
            $this->createNotesTable(dropIfExists: true);

            $rows = [];
            for ($i = 1; $i <= 50; $i++) {
                $rows[] = ['name' => "note-{$i}", 'email' => "note-{$i}@example.com"];
            }

            $this->assert('inserts 50 rows over Hrana', fn () => DB::table('proof_notes')->insert($rows) === true);
            $this->assert('row count is 50 on the server', fn () => DB::table('proof_notes')->count() === 50);
            $this->assert('update reaches the server', fn () => DB::table('proof_notes')->where('email', 'note-1@example.com')->update(['name' => 'renamed']) === 1);
            $this->assert('read sees the update', fn () => DB::table('proof_notes')->where('email', 'note-1@example.com')->value('name') === 'renamed');
            $this->assert('delete reaches the server', fn () => DB::table('proof_notes')->where('email', 'note-50@example.com')->delete() === 1);
            $this->assert('row count is 49 after the delete', fn () => DB::table('proof_notes')->count() === 49);
        });

        $this->phase('replica', 'Embedded replica: sync() pulls down, sync() pushes up', function () {
            @unlink(database_path('proof-replica.sqlite'));
            $this->useConnection([
                'database' => 'proof-replica.sqlite',
                'url' => 'http://sqld:8080',
                'authToken' => 'proof-token',
                'syncInterval' => 0,
                'read_your_writes' => true,
            ]);

            $this->performs('DB::sync() pulls server state into the replica', fn () => DB::connection()->sync());
            $this->assert('replica sees the 49 server rows', fn () => DB::table('proof_notes')->count() === 49);

            $this->performs('insert a row through the replica', fn () => DB::table('proof_notes')->insert(['name' => 'via-replica', 'email' => 'via-replica@example.com']));
            $this->performs('DB::sync() pushes the replica write upstream', fn () => DB::connection()->sync());
        });

        $this->phase('transaction', 'Transactions commit and roll back as expected', function () {
            $this->useConnection(['database' => ':memory:']);
            $this->createNotesTable();

            $this->performs('open a transaction', fn () => DB::beginTransaction());
            $this->performs('insert inside the transaction', fn () => DB::table('proof_notes')->insert(['name' => 'rolled-back', 'email' => 'rolled-back@example.com']));
            $this->performs('roll back', fn () => DB::rollBack());
            $this->assert('rolled back row is gone', fn () => DB::table('proof_notes')->count() === 0);

            $this->performs('open a transaction', fn () => DB::beginTransaction());
            $this->performs('insert inside the transaction', fn () => DB::table('proof_notes')->insert(['name' => 'kept', 'email' => 'kept@example.com']));
            $this->performs('commit', fn () => DB::commit());
            $this->assert('committed row is present', fn () => DB::table('proof_notes')->count() === 1);
        });

        $this->phase('second_client', 'A second, independent Hrana client (raw HTTP) sees the data', function () {
            $this->assert('second client reads 50 rows from sqld', fn () => (int) $this->hranaExecute('SELECT count(*) FROM proof_notes') === 50);
            $this->assert(
                'second client sees the replica-written row',
                fn () => (int) $this->hranaExecute("SELECT count(*) FROM proof_notes WHERE email = 'via-replica@example.com'") === 1
            );
        });

        $this->phase('encryption', 'DB_ENCRYPTION_KEY encrypts at rest (driver >= 1.4.0)', function () {
            $key = 'proof-secret-' . Str::random(24);
            $marker = 'encrypted-proof-marker-7f3a9c';
            $file = database_path('proof-encrypted.sqlite');

            @unlink($file);
            $this->useConnection(['database' => 'proof-encrypted.sqlite', 'encryptionKey' => $key]);
            $this->createNotesTable();
            $this->assert('inserts a marker row', fn () => DB::table('proof_notes')->insert(['name' => $marker, 'email' => 'marker@example.com']) === true);
            $this->performs('closes the connection (purge)', fn () => DB::purge('libsql'));

            $raw = (string) file_get_contents($file);
            $this->assert('on-disk file contains no plaintext marker', fn () => ! str_contains($raw, $marker));

            $this->useConnection(['database' => 'proof-encrypted.sqlite', 'encryptionKey' => $key]);
            $this->assert('re-opening with the same key decrypts the row', fn () => DB::table('proof_notes')->where('email', 'marker@example.com')->value('name') === $marker);

            @unlink($file);
        });

        $this->phase('write_failures', 'Failed writes throw a QueryException (driver >= 1.4.0)', function () {
            $this->useConnection(['database' => ':memory:']);
            $this->createNotesTable();

            $this->assert('inserts the seed row', fn () => DB::table('proof_notes')->insert(['name' => 'a', 'email' => 'duplicate@example.com']) === true);

            $thrown = null;
            try {
                DB::table('proof_notes')->insert(['name' => 'b', 'email' => 'duplicate@example.com']);
            } catch (QueryException $e) {
                $thrown = $e;
            }

            $this->assert('duplicate unique email throws QueryException', fn () => $thrown instanceof QueryException);
            $this->assert('message reports the unique constraint', fn () => $thrown instanceof QueryException && stripos($thrown->getMessage(), 'unique') !== false);
        });

        $report = [
            'driver' => 'tursodatabase/turso-driver-laravel',
            'phases' => $this->phases,
            'ok' => $this->passed,
            'fail' => $this->failed,
            'all_ok' => $this->failed === 0,
        ];

        file_put_contents($this->option('report'), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->line('');
        $this->line('PROOF: ' . json_encode(['ok' => $this->passed, 'fail' => $this->failed, 'all_ok' => $this->failed === 0]));

        return $this->failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Reconfigure the default libsql connection in-memory (the driver always
     * reads `database.connections.libsql`), then resolve it fresh.
     *
     * @param  array<string, mixed>  $overrides
     */
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

    private function createNotesTable(bool $dropIfExists = true): void
    {
        if ($dropIfExists && Schema::hasTable('proof_notes')) {
            Schema::drop('proof_notes');
        }

        Schema::create('proof_notes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });
    }

    private function crudCycle(string $label): void
    {
        $this->assert("[{$label}] inserts 3 rows", fn () => DB::table('proof_notes')->insert([
            ['name' => 'alpha', 'email' => 'alpha@example.com'],
            ['name' => 'beta', 'email' => 'beta@example.com'],
            ['name' => 'gamma', 'email' => 'gamma@example.com'],
        ]) === true);
        $this->assert("[{$label}] counts 3 rows", fn () => DB::table('proof_notes')->count() === 3);
        $this->assert("[{$label}] updates one row", fn () => DB::table('proof_notes')->where('email', 'alpha@example.com')->update(['name' => 'updated']) === 1);
        $this->assert("[{$label}] reads back the update", fn () => DB::table('proof_notes')->where('email', 'alpha@example.com')->value('name') === 'updated');
        $this->assert("[{$label}] deletes one row", fn () => DB::table('proof_notes')->where('email', 'gamma@example.com')->delete() === 1);
        $this->assert("[{$label}] counts 2 rows after delete", fn () => DB::table('proof_notes')->count() === 2);
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
            'timeout' => 10,
            'ignore_errors' => true,
        ]]);

        $raw = @file_get_contents('http://sqld:8080/v2/pipeline', false, $context);
        if ($raw === false) {
            throw new RuntimeException('sqld HTTP request failed');
        }

        $decoded = json_decode($raw, true);
        $value = $decoded['results'][0]['response']['result']['rows'][0][0]['value'] ?? null;
        if ($value === null) {
            throw new RuntimeException('unexpected Hrana response: ' . $raw);
        }

        return $value;
    }
}
