<?php

namespace Turso\Driver\Laravel\Commands\Installer;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Turso\Driver\Laravel\Traits\CommandTrait;

class InstallExtension extends Command
{
    use CommandTrait;

    protected $signature = 'turso-php:install
        {--unstable : Install the unstable version from development repository} 
        {--thread-safe : Install the Thread Safe (TS) version}
        {--php-ini= : Specify the php.ini file}
        {--php-version= : Specify the PHP version}
        {--extension-dir= : Specify the PHP extension directory}
    ';

    protected $description = 'Install LibSQL Extension for PHP';

    public function handle(): void
    {
        if ($this->checkIfLibsqlAlreadyInstalled()) {
            $this->info('LibSQL Extension for PHP is already installed');
            return;
        }

        $this->info('Installing LibSQL Extension for PHP');

        $options = [];
        if ($phpIni = $this->option('php-ini')) {
            $options[] = "--php-ini={$phpIni}";
        }
        if ($phpVersion = $this->option('php-version')) {
            $options[] = "--php-version={$phpVersion}";
        }
        if ($extensionDir = $this->option('extension-dir')) {
            $options[] = "--extension-dir={$extensionDir}";
        }
        if ($this->option('unstable')) {
            $options[] = '--unstable';
        }
        if ($this->option('thread-safe')) {
            $options[] = '--thread-safe';
        }

        $process = Process::run($this->callTursoCommand(command: 'install', options: $options), function ($type, $line) {
            $this->output->write($line);
        });

        if ($process->failed()) {
            $this->error('Failed to install LibSQL Extension for PHP.');
            return;
        }

        $this->info('LibSQL Extension for PHP installed successfully.');
    }
}
