<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Database;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\Connectors\MariaDbConnector;
use Illuminate\Database\Connectors\MySqlConnector;
use Illuminate\Database\Connectors\PostgresConnector;
use Illuminate\Database\Connectors\SQLiteConnector;
use Illuminate\Database\Connectors\SqlServerConnector;
use Illuminate\Database\MariaDbConnection;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Database\SqlServerConnection;
use InvalidArgumentException;
use PDO;

class LibSQLConnectionFactory extends ConnectionFactory
{
    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    /**
     * Create a new connection instance.
     *
     * @param  string  $driver
     * @param  PDO|Closure|LibSQLDatabase  $connection
     * @param  string  $database
     * @param  string  $prefix
     * @param  array  $config
     * @return Connection
     *
     * @throws InvalidArgumentException
     */
    protected function createConnection($driver, $connection, $database, $prefix = '', array $config = [])
    {
        if ($resolver = Connection::getResolver($driver)) {
            return $resolver($connection, $database, $prefix, $config);
        }

        if ($driver === 'libsql') {
            $port = isset($config['port']) ? ":{$config['port']}" : '';
            $config['url'] = !empty($config['host']) ? "{$config['driver']}://{$config['host']}{$port}" : $database;
            $config['driver'] = $driver;
            $connection = new LibSQLDatabase($config);
        }

        return match ($driver) {
            'mysql' => new MySqlConnection($connection, $database, $prefix, $config),
            'mariadb' => new MariaDbConnection($connection, $database, $prefix, $config),
            'pgsql' => new PostgresConnection($connection, $database, $prefix, $config),
            'sqlite' => new SQLiteConnection($connection, $database, $prefix, $config),
            'sqlsrv' => new SqlServerConnection($connection, $database, $prefix, $config),
            'libsql' => new LibSQLConnection($connection, $database, $prefix, $config),
            default => throw new InvalidArgumentException("Unsupported driver [{$driver}]."),
        };
    }

    public function createConnector(array $config)
    {
        if (!isset($config['driver'])) {
            throw new InvalidArgumentException('A driver must be specified.');
        }

        if ($this->container->bound($key = "db.connector.{$config['driver']}")) {
            return $this->container->make($key);
        }

        return match ($config['driver']) {
            'mysql' => new MySqlConnector(),
            'mariadb' => new MariaDbConnector(),
            'pgsql' => new PostgresConnector(),
            'sqlite' => new SQLiteConnector(),
            'sqlsrv' => new SqlServerConnector(),
            'libsql' => new LibSQLConnector(),
            default => throw new InvalidArgumentException("Unsupported driver [{$config['driver']}]."),
        };
    }
}
