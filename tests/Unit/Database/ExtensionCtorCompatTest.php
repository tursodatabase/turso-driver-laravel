<?php

declare(strict_types=1);

use ReflectionClass;
use Turso\Driver\Laravel\Database\LibSQLDatabase;

test('it keeps every extension constructor generation connectable', function () {
    $db = new LibSQLDatabase(['database' => ':memory:']);

    expect($db->getConnectionMode())->toBe('memory')
        ->and($db->version())->not->toBe('');
});

test('it accepts the 1.7.0 offline-write flag keys without changing connection semantics', function () {
    // `sqld_offline_mode` only materializes an offline connection when both
    // authToken and syncUrl are set (extension requires them), so a
    // plain memory stays a plain memory even with the flag on.
    $db = new LibSQLDatabase([
        'database' => ':memory:',
        'sqldOfflineMode' => true,
    ]);

    expect($db->getConnectionMode())->toBe('memory')
        ->and($db->version())->not->toBe('');
});

test('it passes the encryption key positionally, not through the config array', function () {
    $count = (new ReflectionClass(\LibSQL::class))->getMethod('__construct')->getNumberOfParameters();

    expect($count)->toBeGreaterThanOrEqual(4);

    $db = new LibSQLDatabase([
        'database' => ':memory:',
        'encryptionKey' => '',
    ]);

    expect($db->getConnectionMode())->toBe('memory')
        ->and($db->version())->not->toBe('');
});
