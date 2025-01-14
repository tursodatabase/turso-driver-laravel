<?php

namespace Turso\Driver\Laravel\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Turso\Driver\Laravel\LibSQLDriverServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn(string $modelName) => 'Turso\\Driver\\Laravel\\Tests\\Fixtures\\Factories\\' . class_basename($modelName) . 'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            LibSQLDriverServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.connections', [
            // In-Memory Connection
            'libsql' => [
                'driver' => 'libsql',
                'syncInterval' => 5,
                'read_your_writes' => true,
                'encryptionKey' => '',
                'database' => ':memory:',
                'prefix' => '',
                'url' => '',
                'authToken' => '',
            ],
            // Remote Connection
            'otherdb' => [
                'driver' => 'libsql',
                'syncInterval' => 5,
                'read_your_writes' => true,
                'encryptionKey' => '',
                'database' => null,
                'prefix' => '',
                'url' => 'http://127.0.0.1:8081',
                'authToken' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJFZERTQSJ9.eyJpYXQiOjE3MzY2MzU1MTUsIm5iZiI6MTczNjYzNTUxNSwiZXhwIjoxNzM3MjQwMzE1LCJqdGkiOiJkYjEifQ.5sm4FN4PosAJ5h9wLay6q3ryAxbGRGuETU1A3F_Tr3WXpAEnr98tmAa92qcpZz_YZN0T_h4RqjGlEMgrSwIJAQ',
            ],
            // Embedded Replica
            'otherdb2' => [
                'driver' => 'libsql',
                'syncInterval' => 5,
                'read_your_writes' => true,
                'encryptionKey' => '',
                'database' => test_database_path('otherdb2.db'),
                'prefix' => '',
                'url' => 'http://127.0.0.1:8081',
                'authToken' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJFZERTQSJ9.eyJpYXQiOjE3MzY2MzU1MTUsIm5iZiI6MTczNjYzNTUxNSwiZXhwIjoxNzM3MjQwMzE1LCJqdGkiOiJkYjEifQ.5sm4FN4PosAJ5h9wLay6q3ryAxbGRGuETU1A3F_Tr3WXpAEnr98tmAa92qcpZz_YZN0T_h4RqjGlEMgrSwIJAQ',
            ]
        ]);
        config()->set('database.default', 'libsql');
        config()->set('queue.default', 'sync');
    }
}
