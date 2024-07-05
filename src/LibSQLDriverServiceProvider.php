<?php

namespace Turso\Driver\Laravel;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Arr;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Turso\Driver\Laravel\Database\LibSQLConnection;
use Turso\Driver\Laravel\Database\LibSQLConnector;

class LibSQLDriverServiceProvider extends PackageServiceProvider
{
    public function boot(): void
    {
        parent::boot();
    }

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('turso-driver-laravel');
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(LibSQLConnector::class, function ($app) {
            return new LibSQLConnector();
        });

        $this->app->resolving('db', function (DatabaseManager $db) {
            $db->extend('libsql', function ($config, $name) {
                Arr::add($config, 'prefix', '');
                Arr::add($config, 'name', $name);
                $pdoResolver = app()->get(LibSQLConnector::class)->connect($config);

                $connection = new LibSQLConnection($pdoResolver, $config['database'] ?? '', $config['prefix'], $config);
                app()->instance(LibSQLConnection::class, $connection);

                return $connection;
            });
        });
    }
}
