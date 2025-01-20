<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Commands\Sqld\Runner;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Turso\Driver\Laravel\Concerns\HandlesTursoInstallerCommands;

final class OpenDatabaseCli extends Command
{
    use HandlesTursoInstallerCommands;

    protected $signature = 'turso-php:open-db {env-id-or-name} {db-name}';

    protected $description = 'Open a database in the CLI';

    public function handle(): void
    {
        $arguments = [];
        if ($nameOrId = $this->argument('env-id-or-name')) {
            $arguments[] = $nameOrId;
        }

        if ($dbName = $this->argument('db-name')) {
            $arguments[] = $dbName;
        }

        $process = Process::forever()->tty()->run($this->callTursoCommand(
            command: 'sqld:open-db',
            arguments: $arguments,
        ));

        if ($process->failed()) {
            $this->error('Failed to list running daemons.');

            return;
        }
    }
}
