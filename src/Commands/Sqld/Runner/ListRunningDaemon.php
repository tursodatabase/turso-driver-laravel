<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Commands\Sqld\Runner;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Turso\Driver\Laravel\Concerns\HandlesTursoInstallerCommands;

final class ListRunningDaemon extends Command
{
    use HandlesTursoInstallerCommands;

    protected $signature = 'turso-php:daemon-list';

    protected $description = 'List all running daemon processes';

    public function handle(): void
    {
        $process = Process::run($this->callTursoCommand(
            command: 'sqld:daemon-list',
        ), function ($type, $line): void {
            $this->output->write($line);
        });

        if ($process->failed()) {
            $this->error('Failed to list running daemons.');

            return;
        }
    }
}
