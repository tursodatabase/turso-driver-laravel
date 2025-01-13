<?php
declare(strict_types=1);

namespace Turso\Driver\Laravel\Commands\Token;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Turso\Driver\Laravel\Traits\CommandTrait;

class DeleteToken extends Command
{
    use CommandTrait;

    protected $signature = 'turso-php:token-delete {db-name?}
        {--all : Delete all database tokens}
        {--f|force : Force the command to run without confirmation}
    ';

    protected $description = 'Delete a database token';

    public function handle(): void
    {
        $arguments = [];
        if ($dbName = $this->argument('db-name')) {
            $arguments[] = $dbName;
        }

        $options = [];
        if ($this->option('all')) {
            $options[] = '--all';
        }

        if ($this->option('force')) {
            $options[] = '--force';
        }

        $process = Process::run($this->callTursoCommand(
            command: 'token:delete',
            options: $options,
            arguments: $arguments
        ), function ($type, $line) {
            $this->output->write($line);
        });

        if ($process->failed()) {
            $this->error('Failed to list token.');

            return;
        }
    }
}
