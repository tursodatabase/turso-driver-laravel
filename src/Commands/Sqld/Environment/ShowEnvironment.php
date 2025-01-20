<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Commands\Sqld\Environment;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Turso\Driver\Laravel\Concerns\HandlesTursoInstallerCommands;

final class ShowEnvironment extends Command
{
    use HandlesTursoInstallerCommands;

    protected $signature = 'turso-php:env-show {name-or-id}';

    protected $description = 'Show detail of an environment';

    public function handle(): void
    {
        $arguments = [];
        if ($nameOrId = $this->argument('name-or-id')) {
            $arguments[] = $nameOrId;
        }

        $process = Process::run($this->callTursoCommand(
            command: 'sqld:env-show',
            arguments: $arguments,
        ), function ($type, $line): void {
            $this->output->write($line);
        });

        if ($process->failed()) {
            $this->error('Failed to list environments.');

            return;
        }
    }
}
