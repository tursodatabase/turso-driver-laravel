<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Commands\Sqld\Environment;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Turso\Driver\Laravel\Concerns\HandlesTursoInstallerCommands;

final class EditEnvironment extends Command
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

        $command = $this->callTursoCommand(
            command: 'sqld:env-edit',
            arguments: $arguments,
        );

        $process = Process::forever();
        if (\Symfony\Component\Process\Process::isTtySupported()) {
            $process = $process->tty();
        }

        $process = $process->run($command, function ($type, $line): void {
            $this->output->write($line);
        });

        if ($process->failed()) {
            $this->error('Failed to edit environment.');

            return;
        }
    }
}
