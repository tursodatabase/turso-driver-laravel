<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Commands\Sqld\Runner;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Turso\Driver\Laravel\Traits\CommandTrait;

final class RunSqldServer extends Command
{
    use CommandTrait;

    protected $signature = 'turso-php:server-run {env-id-or-name} {db-name}
        {--d|daemon : Run sqld in daemon mode}
    ';

    protected $description = 'Open database based environment name/ID and database name in Turso Shell';

    public function handle(): void
    {
        $arguments = [];
        if ($nameOrId = $this->argument('env-id-or-name')) {
            $arguments[] = $nameOrId;
        }

        if ($dbName = $this->argument('db-name')) {
            $arguments[] = $dbName;
        }

        if ($this->option('daemon')) {
            $arguments[] = '--daemon';
        }

        $process = Process::run($this->callTursoCommand(
            command: 'sqld:server-run',
            arguments: $arguments,
        ), function ($type, $line): void {
            $this->output->write($line);
        });

        if ($process->failed()) {
            $this->error('Failed to open database.');

            return;
        }
    }
}
