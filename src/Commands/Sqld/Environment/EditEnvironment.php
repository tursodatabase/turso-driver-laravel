<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Commands\Sqld\Environment;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Turso\Driver\Laravel\Concerns\HandlesTursoInstallerCommands;

class EditEnvironment extends Command
{
    use HandlesTursoInstallerCommands;

    protected $signature = 'turso-php:env-edit {name-or-id}';

    protected $description = 'Edit an existing environment by ID or name';

    public function handle(): void
    {
        $arguments = [];
        if ($nameOrId = $this->argument('name-or-id')) {
            $arguments[] = $nameOrId;
        }

        $process = Process::run($this->callTursoCommand(
            command: 'sqld:env-edit',
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
