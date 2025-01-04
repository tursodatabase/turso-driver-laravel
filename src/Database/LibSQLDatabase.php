<?php

namespace Turso\Driver\Laravel\Database;

use LibSQL;
use LibSQLTransaction;

class LibSQLDatabase
{
    protected LibSQL $db;

    protected array $config;

    protected string $connection_mode;

    protected array $lastInsertIds = [];

    protected LibSQLTransaction $tx;

    protected bool $in_transaction = false;

    public function __construct(array $config = [])
    {
        $config = $this->createConfig($config);

        if ($config['url'] !== ':memory:') {
            $url = str_replace('file:', '', $config['url']);
            $config['url'] = $this->checkPathOrFilename($config['url']) === 'filename' ? "file:$url" : '';
        }

        $this->setConnectionMode($config);

        $this->in_transaction = false;

        $this->db = new LibSQL($config);
    }

    private function createConfig(array $config): array
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

    private function setConnectionMode(array $config): void
    {
        $url = $config['url'] ?? '';
        $authToken = $config['authToken'] ?? '';
        $syncUrl = $config['syncUrl'] ?? '';
        $remoteOnly = $config['remoteOnly'] ?? false;

        $isValidFilename = function (string $url): bool {
            $filename = basename($url);

            return str_ends_with($filename, '.db') !== false || str_ends_with($filename, '.sqlite') !== false;
        };

        $mode = match (true) {
            // Check for remote_replica
            $isValidFilename($url) &&
            ! empty($authToken) &&
            ! empty($syncUrl) &&
            ! $remoteOnly => 'remote_replica',

            // Check for remote
            $isValidFilename($url) &&
            ! empty($authToken) &&
            ! empty($syncUrl) &&
            $remoteOnly => 'remote',

            // Check for local
            $isValidFilename($url) &&
            empty($authToken) &&
            empty($syncUrl) => 'local',

            // Default to memory
            default => 'memory',
        };

        $this->connection_mode = $mode;
    }

    private function checkPathOrFilename(string $string): string|false
    {
        $filename = basename($string);

        if (strpos($filename, '.db') !== false || strpos($filename, '.sqlite') !== false) {
            return 'filename';
        }

        if (filter_var($string, FILTER_VALIDATE_URL)) {
            return 'url';
        }

        if (! pathinfo($filename, PATHINFO_EXTENSION)) {
            return 'directory';
        }

        return 'unknown';
    }

    public function version(): string
    {
        return $this->getDb()->version();
    }

    public function beginTransaction(): bool
    {
        if ($this->inTransaction()) {
            throw new \PDOException('Already in a transaction');
        }

        $this->in_transaction = true;
        $this->tx = $this->db->transaction();

        return true;
    }

    public function commit(): bool
    {
        if (! $this->inTransaction()) {
            throw new \PDOException('No active transaction');
        }

        $this->tx->commit();
        $this->in_transaction = false;

        return true;
    }

    public function rollback(): bool
    {
        if (! $this->inTransaction()) {
            throw new \PDOException('No active transaction');
        }

        $this->tx->rollback();
        $this->in_transaction = false;

        return true;
    }

    public function prepare(string $sql): LibSQLPDOStatement
    {
        return new LibSQLPDOStatement(
            ($this->inTransaction() ? $this->tx : $this->db)->prepare($sql),
            $sql
        );
    }

    public function exec(string $queryStatement): int
    {
        $statement = $this->prepare($queryStatement);
        $statement->execute();

        return $statement->rowCount();
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

    public function inTransaction(): bool
    {
        return $this->in_transaction;
    }

    public function sync(): void
    {
        if ($this->connection_mode !== 'remote_replica') {
            throw new \Exception("[LibSQL:{$this->connection_mode}] Sync is only available for Remote Replica Connection.", 1);
        }
        $this->db->sync();
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
