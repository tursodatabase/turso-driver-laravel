<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Commands\Installer;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Turso\Driver\Laravel\Concerns\HandlesTursoInstallerCommands;

final class UninstallExtension extends Command
{
    use HandlesTursoInstallerCommands;

    protected $signature = 'turso-php:uninstall';

    protected $description = 'Uninstall LibSQL Extension for PHP';

    public function handle(): void
    {
        if (! $this->checkIfLibsqlAlreadyInstalled()) {
            $this->info('LibSQL Extension for PHP is not installed.');

            return;
        }

        $this->info('Uninstalling LibSQL Extension for PHP');

        $process = Process::run($this->callTursoCommand('uninstall'), function ($type, $line): void {
            $this->output->write($line);
        });

        if ($process->failed()) {
            $this->error('Failed to uninstall LibSQL Extension for PHP.');

            return;
        }

        $this->info('LibSQL Extension for PHP uninstalled successfully.');
    }
}
