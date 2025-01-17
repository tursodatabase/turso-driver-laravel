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
        if (config('database.default') !== 'libsql' || config('database.connections.libsql.driver') === 'libsql') {
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

        $this->app->resolving('db', function (DatabaseManager $db) {
            $db->extend('libsql', function ($config, $name) {
                $config = config('database.connections.libsql');
                $config['name'] = $name;
                if (! isset($config['driver'])) {
                    $config['driver'] = 'libsql';
                }

                $connector = new LibSQLConnector();
                $db = $connector->connect($config);

                $connection = new LibSQLConnection($db, $config['database'] ?? ':memory:', $config['prefix'], $config);
                app()->instance(LibSQLConnection::class, $connection);

                $connection->createReadPdo($config);

                return $connection;
            });
        });
    }
}
