<?php
declare(strict_types=1);

namespace Turso\Driver\Laravel\Commands\Token;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Turso\Driver\Laravel\Traits\CommandTrait;

class CreateToken extends Command
{
    use CommandTrait;

    protected $signature = 'turso-php:token-create {db-name}
        {--expire=7 : The number of days until the token expires, default is 7 days}
    ';

    protected $description = 'Create libSQL Server Database token for Local Development';

    public function handle(): void
    {
        $arguments = [];
        if ($dbName = $this->argument('db-name')) {
            $arguments[] = $dbName;
        }

        $options = [];
        if ($expire = $this->option('expire')) {
            $options[] = "--expire={$expire}";
        }

        $process = Process::run($this->callTursoCommand(
            command: 'token:create',
            options: $options,
            arguments: $arguments
        ), function ($type, $line) {
            $this->output->write($line);
        });

        if ($process->failed()) {
            $this->error('Failed to create token.');

            return;
        }
    }
}
