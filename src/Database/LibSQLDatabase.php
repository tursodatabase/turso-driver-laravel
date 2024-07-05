<?php

namespace Turso\Driver\Laravel\Database;

use LibSQL;
use Turso\Driver\Laravel\Exceptions\ConfigurationIsNotFound;

class LibSQLDatabase
{
    protected LibSQL $db;

    protected string $connection_mode;

    protected array $lastInsertIds = [];

    protected bool $inTransaction = false;

    public function __construct(protected array $config = [])
    {
    }

    public function init()
    {
        // actually path is database
        $path = empty($this->config['url']) ? $this->config['database'] : $this->config['url'];
        $libsql = $this->checkConnectionMode($path, $this->config['syncUrl'], $this->config['authToken'], $this->config['remoteOnly']);
        if ($this->connection_mode === 'local') {
            $this->db = $this->createLibSQL($path, LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE, $this->config['encryptionKey']);
        } elseif ($this->connection_mode === 'memory') {
            $this->db = $this->createLibSQL($libsql['uri']);
        } elseif ($this->connection_mode === 'remote' && $this->config['remoteOnly'] === true) {
            $this->db = $this->createLibSQL("libsql:dbname={$this->config['syncUrl']};authToken={$this->config['authToken']}");
        } elseif ($this->connection_mode === 'remote_replica') {
            $this->db = $this->createLibSQL([
                "url" => $path,
                "authToken" => $this->config['authToken'],
                'syncUrl' => $this->config['syncUrl'],
                'syncInterval' => $this->config['syncInterval'],
                'read_your_writes' => $this->config['readYourWrites'],
                'encryptionKey' => $this->config['encryptionKey'],
            ]);
        } else {
            throw new ConfigurationIsNotFound('Connection not found!');
        }
    }

    protected function createLibSQL(string|array $config, ?int $flags = 6, ?string $encryptionKey = ''): LibSQL
    {
        return new LibSQL($config, $flags, $encryptionKey);
    }

    public function beginTransaction(): bool
    {
        $this->inTransaction = $this->prepare('BEGIN')->execute();

        return $this->inTransaction;
    }

    public function commit(): bool
    {
        $result = $this->prepare('COMMIT')->execute();

        $this->inTransaction = false;

        return $result;
    }

    public function exec(string $queryStatement): int
    {
        $statement = $this->prepare($queryStatement);
        $statement->execute();

        return $statement->rowCount();
    }

    public function prepare(string $sql): LibSQLPDOStatement
    {
        return new LibSQLPDOStatement($this->db, $sql);
    }

    public function query(string $sql, array $params = [])
    {
        return $this->db->query($sql, $params)->fetchArray();
    }

    public function setLastInsertId(?string $name = null, ?int $value = null): void
    {
        if ($name === null) {
            $name = 'id';
        }

        $this->lastInsertIds[$name] = $value;
    }

    public function lastInsertId(?string $name = null): string|false
    {
        if ($name === null) {
            $name = 'id';
        }

        return (isset($this->lastInsertIds[$name]))
            ? (string) $this->lastInsertIds[$name]
            : false;
    }

    public function rollBack(): bool
    {
        $result = $this->prepare('ROLLBACK')->execute();

        $this->inTransaction = false;

        return $result;
    }

    public function sync(): void
    {
        if ($this->connection_mode !== 'remote_replica') {
            throw new \Exception("[LibSQL:{$this->connection_mode}] Sync is only available for Remote Replica Connection.", 1);
        }
        $this->db->sync();
    }

    public static function escapeString($value)
    {
        // DISCUSSION: Open PR if you have best approach
        $escaped_value = str_replace(
            ['\\', "\x00", "\n", "\r", "\x1a", "'", '"'],
            ['\\\\', '\\0', '\\n', '\\r', '\\Z', "\\'", '\\"'],
            $value
        );

        return $escaped_value;
    }

    public function quote(string $value): string
    {
        return self::escapeString($value);
    }

    /**
     * Check the connection mode based on the provided path.
     *
     * @param  string  $path  The database connection path.
     * @return array|false The connection mode details, or false if not applicable.
     */
    private function checkConnectionMode(string $path, string $url = '', string $token = '', bool $remoteOnly = false): array|false
    {
        if ((str_starts_with($path, 'file:') !== false || $path !== 'file:') && ! empty($url) && ! empty($token) && $remoteOnly === false) {
            $this->connection_mode = 'remote_replica';
            $connectionData = [
                'mode' => $this->connection_mode,
                'uri' => $path,
                'url' => $url,
                'token' => $token,
            ];
        } elseif (str_starts_with($path, 'file:') !== false && ! empty($url) && ! empty($token) && $remoteOnly === true) {
            $this->connection_mode = 'remote';
            $connectionData = [
                'mode' => $this->connection_mode,
                'uri' => $path,
                'url' => $url,
                'token' => $token,
            ];
        } elseif (str_starts_with($path, 'file:') !== false) {
            $this->connection_mode = 'local';
            $connectionData = [
                'mode' => $this->connection_mode,
                'uri' => str_replace('file:', '', $path),
            ];
        } elseif ($path === 'memory') {
            $this->connection_mode = 'memory';
            $connectionData = [
                'mode' => $this->connection_mode,
                'uri' => ':memory:',
            ];
        } else {
            $connectionData = false;
        }

        return $connectionData;
    }

    public function __destruct()
    {
        $this->db->close();
    }

    public function getDb(): LibSQL
    {
        return $this->db;
    }

    public function getConnectionMode(): string
    {
        return $this->connection_mode;
    }
}
