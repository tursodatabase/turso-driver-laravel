<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Commands\Sqld\Environment;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Turso\Driver\Laravel\Concerns\HandlesTursoInstallerCommands;

final class ListEnvironment extends Command
{
    use HandlesTursoInstallerCommands;

    protected $signature = 'turso-php:env-list';

    protected $description = 'Display all created environments';

    public function handle(): void
    {
        $process = Process::run($this->callTursoCommand(
            command: 'sqld:env-list',
        ), function ($type, $line): void {
            $this->output->write($line);
        });

        if ($process->failed()) {
            $this->error('Failed to list environments.');

            return;
        }
    }
}
