<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Concerns;

use Illuminate\Support\Facades\Process;

trait HandlesTursoInstallerCommands
{
    protected function checkIfLibsqlAlreadyInstalled(): bool
    {
        $command = PHP_OS_FAMILY === 'Windows' ? 'php -m | findstr libsql' : 'php -m | grep libsql';
        $output = Process::run($command);

        return $output->output() !== '';
    }

    protected function callTursoCommand(string $command, array $options = [], array $arguments = []): string
    {
        $parts = array_merge([$command], $options, $arguments);
        $escaped = array_map(static fn (string $part): string => escapeshellarg($part), $parts);

        return $this->getTursoPhpInstallerBinary() . ' ' . implode(' ', $escaped);
    }

    protected function getTursoPhpInstallerBinary(): string
    {
        $vendor_name = 'vendor/darkterminal/turso-php-installer/builds/turso-php-installer';
        if (file_exists(base_path($vendor_name))) {
            return base_path($vendor_name);
        }

        $vendor_name = 'tursodatabase/turso-driver-laravel/vendor/darkterminal/turso-php-installer';

        return base_path("vendor/{$vendor_name}/builds/turso-php-installer");
    }
}
