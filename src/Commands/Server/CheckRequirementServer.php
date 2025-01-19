<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Commands\Server;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Turso\Driver\Laravel\Concerns\HandlesTursoInstallerCommands;

final class CheckRequirementServer extends Command
{
    use HandlesTursoInstallerCommands;

    protected $signature = 'turso-php:server-check';

    protected $description = 'Check libSQL Server requirements';

    public function handle(): void
    {
        $process = Process::run($this->callTursoCommand('server:check'), function ($type, $line): void {
            $this->output->write($line);
        });

        if ($process->failed()) {
            $this->error('Failed to check libSQL Server requirements.');

            return;
        }
    }
}
