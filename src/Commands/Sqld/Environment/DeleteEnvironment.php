<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Commands\Sqld\Environment;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Turso\Driver\Laravel\Concerns\HandlesTursoInstallerCommands;

class DeleteEnvironment extends Command
{
    use HandlesTursoInstallerCommands;

    protected $signature = 'turso-php:env-delete {name-or-id}';

    protected $description = 'Delete an environment by name or ID';

    public function handle(): void
    {
        $arguments = [];
        if ($nameOrId = $this->argument('name-or-id')) {
            $arguments[] = $nameOrId;
        }

        $process = Process::run($this->callTursoCommand(
            command: 'sqld:env-delete',
            arguments: $arguments
        ), function ($type, $line) {
            $this->output->write($line);
        });

        if ($process->failed()) {
            $this->error('Failed to list environments.');

            return;
        }
    }
}
