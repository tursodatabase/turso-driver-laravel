<?php

namespace Turso\Driver\Laravel\Commands\Token;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Turso\Driver\Laravel\Traits\CommandTrait;

class ShowToken extends Command
{
    use CommandTrait;

    protected $signature = 'turso-php:token-show {db-name} 
        {--fat : Display only full access token}
        {--roa : Display only read-only access token}
        {--pkp : Display only public key pem}
        {--pkb : Display only public key base64}
    ';

    protected $description = 'Display all generated database tokens';

    public function handle(): void
    {
        $arguments = [];
        if ($dbName = $this->argument('db-name')) {
            $arguments[] = $dbName;
        }

        $options = [];
        if ($this->option('fat')) {
            $options[] = '--fat';
        }
        if ($this->option('roa')) {
            $options[] = '--roa';
        }
        if ($this->option('pkp')) {
            $options[] = '--pkp';
        }
        if ($this->option('pkb')) {
            $options[] = '--pkb';
        }

        $process = Process::run($this->callTursoCommand(
            command: 'token:show',
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
