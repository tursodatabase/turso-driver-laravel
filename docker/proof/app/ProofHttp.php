<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Closure;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

final class ProofHttp extends Command
{
    protected $signature = 'proof:http {--report=storage/app/proof-http-report.json : Where to write the JSON report}';

    protected $description = 'HTTP/fpm proof of usage. Drives real nginx → php-fpm → Laravel → driver requests against sqld and prints a PROOF-HTTP: {json} line';

    private int $passed = 0;

    private int $failed = 0;

    /** @var array<string, array{ok: bool, asserts: int, error: string|null}> */
    private array $phases = [];

    public function handle(): int
    {
        $this->line('');
        $this->line('<options=bold>Turso Driver for Laravel — HTTP/fpm proof</>');
        $this->line('');

        $this->phase('fpm_boot', 'nginx fronts a live php-fpm tier running the driver', function () {
            $health = $this->httpJson('GET', 'http://web/proof/health');

            $this->assert('SAPI is fpm-fcgi (not cli)', fn () => ($health['sapi'] ?? null) === 'fpm-fcgi');
            $this->assert('extension is loaded inside the fpm worker', fn () => ($health['extension'] ?? null) === true);
            $this->assert('default connection is the local libsql file', fn () => ($health['connection_mode'] ?? null) === 'local');
            $this->assert('proof_notes table is reachable over HTTP', fn () => isset($health['rows']) && is_int($health['rows']));
        });

        $this->phase('remote_over_http', 'fpm reaches the live libSQL server through the driver', function () {
            $remote = $this->httpJson('GET', 'http://web/proof/remote');

            $this->assert('fpm resolves the remote connection', fn () => ($remote['mode'] ?? null) === 'remote');
            $this->assert('50 server-side rows are visible through fpm', fn () => (int) ($remote['rows'] ?? -1) === 50);
        });

        $this->phase('throw_over_http', 'failed writes surface as HTTP 500 (unique-constraint failure)', function () {
            [$status, $body] = $this->httpRaw('GET', 'http://web/proof/throw');

            $this->assert('duplicate unique insert returns HTTP 500', fn () => $status === 500);
            $this->assert('debug page shows the unique-constraint failure', fn () => stripos($body, 'UNIQUE constraint failed') !== false);
        });

        $report = [
            'driver' => 'tursodatabase/turso-driver-laravel',
            'phase' => 'http',
            'phases' => $this->phases,
            'ok' => $this->passed,
            'fail' => $this->failed,
            'all_ok' => $this->failed === 0,
        ];

        file_put_contents($this->option('report'), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->line('');
        $this->line('PROOF-HTTP: ' . json_encode(['ok' => $this->passed, 'fail' => $this->failed, 'all_ok' => $this->failed === 0]));

        return $this->failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return array{0: int, 1: string} HTTP status code and body */
    private function httpRaw(string $method, string $url): array
    {
        $context = stream_context_create(['http' => [
            'method' => $method,
            'timeout' => 10,
            'ignore_errors' => true,
        ]]);

        $body = @file_get_contents($url, false, $context);
        $status = 0;

        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $matches)) {
                $status = (int) $matches[1];
            }
        }

        if ($body === false || $status === 0) {
            throw new RuntimeException("HTTP request to {$url} failed");
        }

        return [$status, $body];
    }

    /** @return array<string, mixed> */
    private function httpJson(string $method, string $url): array
    {
        [$status, $body] = $this->httpRaw($method, $url);

        if ($status !== 200) {
            throw new RuntimeException("HTTP {$status} from {$url}: {$body}");
        }

        $json = json_decode($body, true);
        if (! is_array($json)) {
            throw new RuntimeException("non-JSON response from {$url}: {$body}");
        }

        return $json;
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
