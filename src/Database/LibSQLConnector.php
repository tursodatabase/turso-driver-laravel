<?php

namespace Turso\Driver\Laravel\Database;

use Illuminate\Database\Connectors\ConnectorInterface;

class LibSQLConnector implements ConnectorInterface
{
    /**
     * Establish a database connection.
     */
    public function connect(array $config): LibSQLDatabase
    {
        $this->checkConfig($config);
        $db = new LibSQLDatabase($config);
        $db->init();

        return $db;
    }

    protected function checkConfig(array $config): void
    {
        if (empty($config['driver']) || $config['driver'] !== 'libsql') {
            throw new \InvalidArgumentException("Got driver - " . $config['driver'] . ", please check your URL and driver config");
        }
        if (empty($config['url']) && empty($config['database'])) {
            throw new \InvalidArgumentException("URL and database not set, please check your configuration");
        }
    }
}
