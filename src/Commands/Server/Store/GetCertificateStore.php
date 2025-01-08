<?php

namespace Turso\Driver\Laravel\Commands\Server\Store;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Turso\Driver\Laravel\Traits\CommandTrait;

class GetCertificateStore extends Command
{
    use CommandTrait;

    protected $signature = 'turso-php:cert-store-get';

    protected $description = 'Get certificate store';

    public function handle(): void
    {
        $process = Process::run($this->callTursoCommand('server:cert-store-get'), function ($type, $line) {
            $this->output->write($line);
        });

        if ($process->failed()) {
            $this->error('Failed to get certificate store.');
            return;
        }
    }
}
