<?php

it('uses the default connection factory for non-libsql drivers', function () {
    $dbManager = $this->app->make(Illuminate\Database\DatabaseManager::class);

    // Set the default connection to mariadb
    config(['database.default' => 'mariadb']);

    // Add mariadb connection configuration
    config([
        'database.connections.mariadb' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
        ]
    ]);

    $connection = $dbManager->connection('mariadb');

    expect($connection->getConfig('driver'))->toBe('mysql');
    expect($connection)->toBeInstanceOf(\Illuminate\Database\MySqlConnection::class);
})->group('ConnectDifferentDatabaseDriver', 'FeatureTest');

it('uses the libsql connection factory for libsql driver', function () {
    $dbManager = $this->app->make(Illuminate\Database\DatabaseManager::class);

    // Set the default connection to libsql
    config(['database.default' => 'libsql']);

    // Add libsql connection configuration
    config([
        'database.connections.libsql' => [
            'driver' => 'libsql',
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'url' => env('DB_SYNC_URL', ''),
            'authToken' => env('DB_AUTH_TOKEN', ''),
            'syncInterval' => env('DB_SYNC_INTERVAL', 5),
            'read_your_writes' => env('DB_READ_YOUR_WRITES', true),
            'encryptionKey' => env('DB_ENCRYPTION_KEY', ''),
        ]
    ]);

    $connection = $dbManager->connection('libsql');

    expect($connection->getConfig('driver'))->toBe('libsql');
    expect($connection)->toBeInstanceOf(Turso\Driver\Laravel\Database\LibSQLConnection::class);
})->group('ConnectDifferentDatabaseDriver', 'FeatureTest');
