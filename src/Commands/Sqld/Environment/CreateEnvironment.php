<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Commands\Sqld\Environment;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Turso\Driver\Laravel\Concerns\HandlesTursoInstallerCommands;

final class CreateEnvironment extends Command
{
    use HandlesTursoInstallerCommands;

    protected $signature = 'turso-php:env-create {name : The name of the environment}
        {--variables= : The variables of the environment in JSON/DSN format}
        {--force : Overwrite the environment if it already exists}
    ';

    protected $description = 'Create new sqld environment, save for future use.';

    public function handle(): void
    {
        $arguments = [];
        if ($this->argument('name')) {
            $arguments[] = $this->argument('name');
        }

        $options = [];
        if ($variables = $this->option('variables')) {
            $options[] = "--variables={$variables}";
        }

        if ($this->option('force')) {
            $options[] = '--force';
        }

        $process = Process::forever()->tty()->run($this->callTursoCommand(
            command: 'sqld:env-new',
            options: $options,
            arguments: $arguments,
        ), function ($type, $line): void {
            $this->output->write($line);
        });

        if ($process->failed()) {
            $this->error('Failed to create environment.');

            return;
        }
    }
}
