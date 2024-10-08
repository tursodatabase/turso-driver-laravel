<?php

namespace Turso\Driver\Laravel\Database;

use LibSQL;
use Turso\Driver\Laravel\Exceptions\ConfigurationIsNotFound;

class LibSQLDatabase
{
    protected LibSQL $db;

    protected array $config;

    protected string $connection_mode;

    protected array $lastInsertIds = [];

    protected bool $inTransaction = false;

    public function __construct(array $config = [])
    {
        $config = $this->createConfig($config);

        if ($config['url'] !== ':memory:') {
            $url = str_replace('file:', '', $config['url']);
            $config['url'] = match ($this->checkPathOrFilename($config['url'])) {
                'filename' => 'file:'.database_path($url),
                default => $config['url'],
            };
        }

        $this->setConnectionMode($config['url'], $config['syncUrl'], $config['authToken'], $config['remoteOnly']);

        $this->db = match ($this->connection_mode) {
            'local' => $this->createLibSQL(
                $config['url'],
                LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE,
                $config['encryptionKey']
            ),
            'memory' => $this->createLibSQL(':memory:'),
            'remote' => $config['remoteOnly'] === true
            ? $this->createLibSQL("libsql:dbname={$config['syncUrl']};authToken={$config['authToken']}")
            : throw new ConfigurationIsNotFound('Connection not found!'),
            'remote_replica' => $this->createLibSQL(
                array_diff_key($config, array_flip(['driver', 'name', 'prefix', 'database', 'remoteOnly']))
            ),
            default => throw new ConfigurationIsNotFound('Connection not found!'),
        };
    }

    protected function createConfig(array $config): array
    {
        return [
            'url' => $config['url'],
            'authToken' => $config['authToken'] ?? '',
            'syncUrl' => $config['syncUrl'] ?? '',
            'syncInterval' => $config['syncInterval'] ?? 5,
            'read_your_writes' => $config['read_your_writes'] ?? true,
            'encryptionKey' => $config['encryptionKey'] ?? '',
            'remoteOnly' => $config['remoteOnly'] ?? false,
        ];
    }

    protected function createLibSQL(string|array $config, ?int $flag = 6, ?string $encryptionKey = ''): LibSQL
    {
        return new LibSQL($config, $flag, $encryptionKey);
    }

    public function version(): string
    {
        return $this->getDb()->version();
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
        return new LibSQLPDOStatement($this, $sql);
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

        return isset($this->lastInsertIds[$name])
            ? (string) $this->lastInsertIds[$name]
            : false;
    }

    public function rollBack(): bool
    {
        $result = $this->prepare('ROLLBACK')->execute();

        $this->inTransaction = false;

        return $result;
    }

    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }

    public function sync(): void
    {
        if ($this->connection_mode !== 'remote_replica') {
            throw new \Exception("[LibSQL:{$this->connection_mode}] Sync is only available for Remote Replica Connection.", 1);
        }
        $this->db->sync();
    }

    private function setConnectionMode(string $path, string $url = '', string $token = '', bool $remoteOnly = false): void
    {
        if ((str_starts_with($path, 'file:') !== false || $path !== 'file:') && ! empty($url) && ! empty($token) && $remoteOnly === false) {
            $this->connection_mode = 'remote_replica';
        } elseif (strpos($path, 'file:') !== false && ! empty($url) && ! empty($token) && $remoteOnly === true) {
            $this->connection_mode = 'remote';
        } elseif (strpos($path, 'file:') !== false) {
            $this->connection_mode = 'local';
        } elseif ($path === ':memory:') {
            $this->connection_mode = 'memory';
        } else {
            $this->connection_mode = false;
        }
    }

    private function checkPathOrFilename(string $string): string
    {
        if (strpos($string, DIRECTORY_SEPARATOR) !== false || strpos($string, '/') !== false || strpos($string, '\\') !== false) {
            return 'path';
        } else {
            return 'filename';
        }
    }

    public function getDb(): LibSQL
    {
        return $this->db;
    }

    public function getConnectionMode(): string
    {
        return $this->connection_mode;
    }

    public function escapeString($input)
    {
        if ($input === null) {
            return 'NULL';
        }

        return \SQLite3::escapeString($input);
    }

    public function quote($input)
    {
        if ($input === null) {
            return 'NULL';
        }

        return "'".$this->escapeString($input)."'";
    }

    public function __destruct()
    {
        if (isset($this->db)) {
            $this->db->close();
        }
    }
}
