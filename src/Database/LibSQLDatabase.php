<?php
declare(strict_types=1);

namespace Turso\Driver\Laravel\Database;

use LibSQL;
use LibSQLTransaction;

class LibSQLDatabase
{
    protected LibSQL $db;

    protected array $config;

    protected int $mode = \PDO::FETCH_OBJ;

    protected string $connection_mode;

    protected array $lastInsertIds = [];

    protected LibSQLTransaction $tx;

    protected bool $in_transaction = false;

    public function __construct(array $config = [])
    {
        $config = $this->createConfig($config);

        $this->setConnectionMode($config);

        $config = $this->buildConfig($this->connection_mode, $config);

        $this->db = new LibSQL($config);

        $this->in_transaction = false;

    }

    private function createConfig(array $config): array
    {
        return [
            'database' => $config['database'] ?? null,
            'url' => $config['url'] ?? null,
            'authToken' => $config['authToken'] ?? null,
            'encryptionKey' => $config['encryptionKey'] ?? null,
            'syncInterval' => $config['syncInterval'] ?? 5,
            'read_your_writes' => $config['read_your_writes'] ?? true,
        ];
    }

    private function buildConfig(string $mode, $config): array|string
    {
        if ($mode === 'local' || $mode === 'memory') {
            return $mode === 'local' ? "file:{$config['database']}" : $config['database'];
        }

        if ($mode === 'remote') {
            return [
                'url' => $config['url'],
                'authToken' => $config['authToken'],
            ];
        }

        return [
            "url" => "file:{$config['database']}",
            "authToken" => $config['authToken'],
            "syncUrl" => $config['url'],
            "syncInterval" => $config['syncInterval'],
            "read_your_writes" => $config['read_your_writes'],
            "encryptionKey" => $config['encryptionKey']
        ];
    }

    private function setConnectionMode(array $config): void
    {
        $database = $config['database'];
        $url = $config['url'];
        $authToken = $config['authToken'];

        $mode = 'unknown';

        if ($database === ':memory:') {
            $mode = 'memory';
        }

        if (empty($database) && !empty($url) && !empty($authToken)) {
            $mode = 'remote';
        }

        if (!empty($database) && $database !== ':memory:' && empty($url) && empty($authToken) && empty($url)) {
            $mode = 'local';
        }

        if (!empty($database) && $database !== ':memory:' && !empty($authToken) && !empty($url)) {
            $mode = 'remote_replica';
        }

        $this->connection_mode = $mode;
    }

    public function setFetchMode(int $mode, mixed ...$args): bool
    {
        $this->mode = $mode;

        return true;
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
        if (!$this->inTransaction()) {
            throw new \PDOException('No active transaction');
        }

        $this->tx->commit();
        $this->in_transaction = false;

        return true;
    }

    public function rollBack(): bool
    {
        if (!$this->inTransaction()) {
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
        $result = $this->db->query($sql, $params)->fetchArray();

        $rows = array_map(function ($row) {
            return array_map(function ($value) {
                return is_string($value) && base64_encode(base64_decode($value, true)) === $value
                    ? base64_decode($value)
                    : $value;
            }, $row);
        }, $result);

        return match ($this->mode) {
            \PDO::FETCH_ASSOC => collect($rows),
            \PDO::FETCH_OBJ => (object) $rows,
            \PDO::FETCH_NUM => array_values($rows),
            default => collect($rows)
        };
    }

    public function setLastInsertId(?string $name = null, ?int $value = null): void
    {
        if ($name === null) {
            $name = 'id';
        }

        $this->lastInsertIds[$name] = $value;
    }

    public function lastInsertId(?string $name = null): int|string
    {
        if ($name === null) {
            $name = 'id';
        }

        return isset($this->lastInsertIds[$name])
            ? (string) $this->lastInsertIds[$name]
            : $this->db->lastInsertedId();
        ;
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

        return "'" . $this->escapeString($input) . "'";
    }

    public function __destruct()
    {
        if (isset($this->db)) {
            $this->db->close();
        }
    }
}
