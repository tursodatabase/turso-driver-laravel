<?php

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
    $files = File::allFiles($path);

    // Delete all files
    foreach ($files as $file) {
        File::delete($file);
    }
}
