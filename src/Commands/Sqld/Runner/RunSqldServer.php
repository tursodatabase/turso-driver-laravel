<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Commands\Sqld\Runner;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Turso\Driver\Laravel\Concerns\HandlesTursoInstallerCommands;

class RunSqldServer extends Command
{
    use HandlesTursoInstallerCommands;

    protected $signature = 'turso-php:open-db {env-id-or-name} {db-name}';

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

        $process = Process::run($this->callTursoCommand(
            command: 'sqld:open-db',
            arguments: $arguments
        ), function ($type, $line) {
            $this->output->write($line);
        });

        if ($process->failed()) {
            $this->error('Failed to open database.');

            return;
        }
    }
}
