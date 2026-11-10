<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Turso\Driver\Laravel\Tests\TestCase;

uses(
    TestCase::class,
)->in(__DIR__);

function migrateTables(...$tableNames): void
{
    collect($tableNames)
        ->each(function (string $tableName) {
            $migration = include __DIR__ . '/Fixtures/Migrations/create_' . Str::snake(Str::plural($tableName)) . '_table.php';
            $migration->up();
        });
}

function test_database_path(string $path): string
{
    return __DIR__ . DS . 'database' . DS . $path;
}

function clearDirectory(): void
{
    $path = __DIR__ . DS . 'database';

    // Remove the replica's SQLite file and its WAL/SHM companions too —
    // stale WAL files from a previous run make libsql refuse to open the
    // database ("Can not sync a database without a wal_index").
    foreach (glob($path . '/*.db*') ?: [] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

function shouldSkipTests()
{
    return filter_var(getenv('TURSO_SQLD_AVAILABLE'), FILTER_VALIDATE_BOOLEAN) !== true;
}
