<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Database;

use Exception;
use LibSQL;
use LibSQLTransaction;
use PDO;
use PDOException;
use ReflectionClass;
use SQLite3;

class LibSQLDatabase
{
    protected LibSQL $db;

    protected array $config;

    protected int $mode = PDO::FETCH_OBJ;

    protected string $connection_mode;

    protected array $lastInsertIds = [];

    protected LibSQLTransaction $tx;

    protected bool $in_transaction = false;

    protected bool $offlineWriteMode = false;

    public function __construct(array $config = [])
    {
        $config = $this->createConfig($config);

        // The extension refuses prepare() in offline_write mode (prepared
        // statements would bypass the write journal) — route statements
        // through its execute()/query() instead.
        $this->offlineWriteMode = (bool) ($config['sqldOfflineMode'] ?? false) && (bool) ($config['offlineWrites'] ?? false);

        $this->setConnectionMode($config);

        $dbConfig = $this->buildConfig($this->connection_mode, $config);

        $this->db = $this->connectLibSQL(
            config: $dbConfig,
            sqldOfflineMode: (bool) ($config['sqldOfflineMode'] ?? false),
            offlineWrites: (bool) ($config['offlineWrites'] ?? false),
            encryptionKey: $config['encryptionKey'] !== null ? (string) $config['encryptionKey'] : '',
        );

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
            'sqldOfflineMode' => $config['sqldOfflineMode'] ?? false,
            'offlineWrites' => $config['offlineWrites'] ?? false,
        ];
    }

    private function buildConfig(string $mode, $config): array|string
    {
        if ($mode === 'local' || $mode === 'memory') {
            // Don't ask about this line of code! It's a (stupid) hack
            $database = !file_exists($config['database']) && $mode === 'local' ? database_path(str_replace('database/', '', $config['database'])) : $config['database'];
            return $mode === 'local' ? "file:{$database}" : $database;
        }

        if ($mode === 'remote') {
            return [
                'url' => $config['url'],
                'authToken' => $config['authToken'],
            ];
        }

        // The encryption key, the offline-write flags and the connection flags
        // are handed over positionally in connectLibSQL() — since extension
        // 1.7.0 the config array is only read for url/authToken/syncUrl/
        // syncInterval/read_your_writes.
        return [
            'url' => "file:{$config['database']}",
            'authToken' => $config['authToken'],
            'syncUrl' => $config['url'],
            'syncInterval' => $config['syncInterval'],
            'read_your_writes' => $config['read_your_writes'],
        ];
    }

    private static ?int $libsqlConstructorParamCount = null;

    /**
     * `libsql_php_extension/v1.6.x` constructs as
     * (config, flags?, encryption_key?, offline_writes?) — 4 params.
     * `tursodatabase/turso-client-php v1.7.0` added `sqld_offline_mode` as
     * the second positional argument and moved nothing else — 5 params.
     * Feature-detect so both extension generations keep working.
     */
    private function connectLibSQL(string|array $config, bool $sqldOfflineMode, bool $offlineWrites, string $encryptionKey): LibSQL
    {
        $paramCount = self::$libsqlConstructorParamCount;

        if ($paramCount === null) {
            $paramCount = self::$libsqlConstructorParamCount = (new ReflectionClass(LibSQL::class))
                ->getMethod('__construct')
                ->getNumberOfParameters();
        }

        if ($paramCount >= 5) {
            return new LibSQL($config, $sqldOfflineMode, 6, $encryptionKey, $offlineWrites);
        }

        if ($paramCount === 4) {
            return new LibSQL($config, 6, $encryptionKey, $offlineWrites);
        }

        return new LibSQL($config);
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
            throw new PDOException('Already in a transaction');
        }

        $this->in_transaction = true;
        $this->tx = $this->db->transaction();

        return true;
    }

    public function commit(): bool
    {
        if (!$this->inTransaction()) {
            throw new PDOException('No active transaction');
        }

        $this->tx->commit();
        $this->in_transaction = false;

        return true;
    }

    public function rollBack(): bool
    {
        if (!$this->inTransaction()) {
            throw new PDOException('No active transaction');
        }

        $this->tx->rollback();
        $this->in_transaction = false;

        return true;
    }

    public function prepare(string $sql): LibSQLPDOStatement
    {
        if ($this->offlineWriteMode) {
            return LibSQLPDOStatement::direct($this->db, $sql);
        }

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
            PDO::FETCH_ASSOC => collect($rows),
            PDO::FETCH_OBJ => (object) $rows,
            PDO::FETCH_NUM => array_values($rows),
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

    }

    public function inTransaction(): bool
    {
        return $this->in_transaction;
    }

    public function sync(): void
    {
        if ($this->connection_mode !== 'remote_replica') {
            throw new Exception("[LibSQL:{$this->connection_mode}] Sync is only available for Remote Replica Connection.", 1);
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

    public function getAttribute(int $attribute): bool
    {
        return true;
    }

    public function escapeString($input)
    {
        if ($input === null) {
            return 'NULL';
        }

        return SQLite3::escapeString($input);
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
