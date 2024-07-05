<?php

namespace Turso\Driver\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Turso\Driver\Laravel\Database\LibSQLDatabase;

/**
 * @see \Turso\Driver\Laravel\LibSQLDriver
 *
 * @mixin \Turso\Driver\Laravel\Database\LibSQLDatabase
 */
class LibSQLPHP extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LibSQLDatabase::class;
    }
}
