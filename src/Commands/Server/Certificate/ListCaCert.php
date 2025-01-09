<?php

namespace Turso\Driver\Laravel\Commands\Server\Certificate;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Turso\Driver\Laravel\Traits\CommandTrait;

class ListCaCert extends Command
{
    use CommandTrait;

    protected $signature = 'turso-php:ca-cert-list';

    protected $description = 'List the CA certificate';

    public function handle(): void
    {
        $process = Process::run($this->callTursoCommand(command: 'server:ca-cert-list'), function ($type, $line) {
            $this->output->write($line);
        });

        if ($process->failed()) {
            $this->error('Failed to list CA certificate.');

            return;
        }
    }
}