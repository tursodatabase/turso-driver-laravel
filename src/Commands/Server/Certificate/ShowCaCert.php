<?php

namespace Turso\Driver\Laravel\Commands\Server\Certificate;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Turso\Driver\Laravel\Traits\CommandTrait;

class ShowCaCert extends Command
{
    use CommandTrait;

    protected $signature = 'turso-php:ca-cert-show
        {--raw : Show raw CA certificate and private key}
    ';

    protected $description = 'Show raw CA certificate and private key';

    public function handle(): void
    {
        $options = [];
        if ($this->option('raw')) {
            $options[] = '--raw';
        }

        $process = Process::run($this->callTursoCommand(command: 'server:ca-cert-show', options: $options), function ($type, $line) {
            $this->output->write($line);
        });

        if ($process->failed()) {
            $this->error('Failed to show CA certificate.');
            return;
        }
    }
}
