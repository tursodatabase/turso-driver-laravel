<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Commands\Server\Certificate;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Turso\Driver\Laravel\Concerns\HandlesTursoInstallerCommands;

class CreateCaPeerCert extends Command
{
    use HandlesTursoInstallerCommands;

    protected $signature = 'turso-php:ca-peer-cert-create {name=ca}
        {--expiry=30 : Expiry in days, default is 30 days}
    ';

    protected $description = 'Create a peer certificate';

    public function handle(): void
    {
        $arguments = [];
        if ($name = $this->argument('name')) {
            $arguments[] = $name;
        }

        $options = [];
        if ($expiry = $this->option('expiry')) {
            $options[] = "--expiry={$expiry}";
        }

        $process = Process::run($this->callTursoCommand(
            command: 'server:ca-peer-cert-create',
            options: $options,
            arguments: $arguments
        ), function ($type, $line) {
            $this->output->write($line);
        });

        if ($process->failed()) {
            $this->error('Failed to create peer CA certificate.');

            return;
        }
    }
}
