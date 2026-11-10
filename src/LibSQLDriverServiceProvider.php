<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel;

use Illuminate\Database\DatabaseManager;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Turso\Driver\Laravel\Database\LibSQLConnection;
use Turso\Driver\Laravel\Database\LibSQLConnectionFactory;
use Turso\Driver\Laravel\Database\LibSQLConnector;
use Turso\Driver\Laravel\Factories\CommandFactory;
use Turso\Driver\Laravel\Macros\VectorMacro;

class LibSQLDriverServiceProvider extends PackageServiceProvider
{
    public function boot(): void
    {
        parent::boot();
        if (config('database.default') !== 'libsql') {
            return;
        }
    }

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('turso-driver-laravel')
            ->hasCommands(CommandFactory::collect());
    }

    public function register(): void
    {
        parent::register();

        VectorMacro::register();

        $this->app->singleton('db.factory', function ($app) {
            return new LibSQLConnectionFactory($app);
        });

        $this->app->scoped(LibSQLManager::class, function () {
            return new LibSQLManager(config('database.connections.libsql'));
        });

        // Register a facade extension for EVERY statically configured
        // connection whose driver is libsql — not just the default one.
        // The extension reads each connection's OWN config (bypassing
        // ConfigurationUrlParser, which rewrites `url` into the driver
        // scheme and would eat the sync `url` the extension needs).
        $this->app->resolving('db', function (DatabaseManager $db) {
            foreach (config('database.connections', []) as $connectionName => $definition) {
                if (($definition['driver'] ?? null) !== 'libsql') {
                    continue;
                }

                $db->extend($connectionName, function ($ignoredConfig, $name) {
                    $raw = config("database.connections.{$name}", []);

                    if (empty($raw['driver'])) {
                        $raw['driver'] = 'libsql';
                    }

                    $raw['name'] = $name;

                    $connector = new LibSQLConnector();
                    $db = $connector->connect($raw);

                    $connection = new LibSQLConnection($db, $raw['database'] ?? ':memory:', $raw['prefix'] ?? '', $raw);
                    app()->instance(LibSQLConnection::class, $connection);

                    $connection->createReadPdo($raw);

                    return $connection;
                });
            }
        });
    }
}
