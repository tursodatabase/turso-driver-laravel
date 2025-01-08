<?php

namespace Turso\Driver\Laravel\Database;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Connectors\ConnectionFactory;

class LibSQLConnectionFactory extends ConnectionFactory
{
    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    protected function createConnection($driver, $connection, $database, $prefix = '', array $config = [])
    {
        $config['driver'] = 'libsql';
        $config['url'] = 'file:' . $config['database'];
        $connection = function () use ($config) {
            return new LibSQLDatabase($config);
        };

        return new LibSQLConnection($connection(), $config['url'], $prefix, $config);
    }

    public function createConnector(array $config)
    {
        $connector = new LibSQLConnector;
        $connector->connect(config('database.connections.libsql'));

        return new LibSQLConnector;
    }
}
