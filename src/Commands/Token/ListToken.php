<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Commands\Token;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Turso\Driver\Laravel\Concerns\HandlesTursoInstallerCommands;

final class ListToken extends Command
{
    use HandlesTursoInstallerCommands;

    protected $signature = 'turso-php:token-list';

    protected $description = 'Display all generated database tokens';

    public function handle(): void
    {
        $process = Process::run($this->callTursoCommand(
            command: 'token:list',
        ), function ($type, $line): void {
            $this->output->write($line);
        });

        if ($process->failed()) {
            $this->error('Failed to list token.');

            return;
        }
    }
}
