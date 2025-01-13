<?php
declare(strict_types=1);

namespace Turso\Driver\Laravel\Commands\Server\Certificate;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Turso\Driver\Laravel\Traits\CommandTrait;

class DeleteCaCert extends Command
{
    use CommandTrait;

    protected $signature = 'turso-php:ca-cert-delete {name=ca}
        {--all : Delete all CA certificates from global store location}
    ';

    protected $description = 'Delete a CA certificate from the global store location';

    public function handle(): void
    {
        $arguments = [];
        if ($name = $this->argument('name')) {
            $arguments[] = $name;
        }

        $options = [];
        if ($this->option('all')) {
            $options[] = '--all';
        }

        $process = Process::run($this->callTursoCommand(
            command: 'server:ca-cert-delete',
            options: $options,
            arguments: $arguments
        ), function ($type, $line) {
            $this->output->write($line);
        });

        if ($process->failed()) {
            $this->error('Failed to delete CA certificate.');

            return;
        }
    }
}
