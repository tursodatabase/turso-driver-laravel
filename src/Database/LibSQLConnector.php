<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Database;

class LibSQLConnector
{
    /**
     * Establish a database connection.
     */
    public function connect(array $config): LibSQLDatabase
    {
        return new LibSQLDatabase($config);
    }
}
