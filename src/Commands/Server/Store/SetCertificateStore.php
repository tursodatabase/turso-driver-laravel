<?php

namespace Turso\Driver\Laravel\Commands\Server\Store;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Turso\Driver\Laravel\Traits\CommandTrait;

class SetCertificateStore extends Command
{
    use CommandTrait;

    protected $signature = 'turso-php:cert-store-set {path?}';

    protected $description = 'Set/overwrite global certificate store, to use by the server later. Default is same as {installation_dir}/certs';

    public function handle(): void
    {
        $arguments = [];
        if ($path = $this->argument('path')) {
            $arguments[] = $path;
        }

        $process = Process::run($this->callTursoCommand(
            command: 'server:cert-store-get',
            arguments: $arguments
        ), function ($type, $line) {
            $this->output->write($line);
        });

        if ($process->failed()) {
            $this->error('Failed to set certificate store.');

            return;
        }
    }
}