<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Commands\Sqld\Runner;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Turso\Driver\Laravel\Traits\CommandTrait;

final class KillRunningDaemon extends Command
{
    use CommandTrait;

    protected $signature = 'turso-php:daemon-kill {daemon-pid}';

    protected $description = 'Kill a running daemon';

    public function handle(): void
    {
        $arguments = [];
        if ($daemonPid = $this->argument('daemon-pid')) {
            $arguments[] = $daemonPid;
        }

        $process = Process::run($this->callTursoCommand(
            command: 'sqld:daemon-kill',
            arguments: $arguments,
        ), function ($type, $line): void {
            $this->output->write($line);
        });

        if ($process->failed()) {
            $this->error('Failed to list running daemons.');

            return;
        }
    }
}
