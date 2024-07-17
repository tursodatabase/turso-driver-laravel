<?php

namespace Turso\Driver\Laravel\Tests\Feature;

use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    public function getPackageProviders($app)
    {
        return [
            \Turso\Driver\Laravel\LibSQLDriverServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        // Perform any environment setup
    }
}
