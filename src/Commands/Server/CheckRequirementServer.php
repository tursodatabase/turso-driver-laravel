<?php

namespace Turso\Driver\Laravel\Commands\Server;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Turso\Driver\Laravel\Traits\CommandTrait;

class CheckRequirementServer extends Command
{
    use CommandTrait;

    protected $signature = 'turso-php:server-check';

    protected $description = 'Check libSQL Server requirements';

    public function handle(): void
    {
        $process = Process::run($this->callTursoCommand('server:check'), function ($type, $line) {
            $this->output->write($line);
        });

        if ($process->failed()) {
            $this->error('Failed to check libSQL Server requirements.');

            return;
        }
    }
}