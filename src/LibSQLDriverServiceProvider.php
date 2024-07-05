<?php

namespace Turso\Driver\Laravel;

use Illuminate\Database\DatabaseManager;
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
                $config['prefix'] = $config['prefix'] ?: '';
                $config['name'] = $name;
                $pdoResolver = app()->get(LibSQLConnector::class)->connect($config);

                return new LibSQLConnection($pdoResolver, $config['database'] ?? '', $config['prefix'], $config);
            });
        });
    }
}
