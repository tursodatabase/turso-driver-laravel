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
        $config = config('database.connections.libsql');
        $libsql = $this->checkConnectionMode($config['url'], $config['syncUrl'], $config['authToken'], $config['remoteOnly']);

        if ($this->connection_mode === 'local') {

            $url = \str_replace('file:', '', database_path($config['url']));
            $this->db = new LibSQL("file:$url", LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE, $config['encryptionKey']);

        } elseif ($this->connection_mode === 'memory') {

            $this->db = new LibSQL($libsql['uri']);

        } elseif ($this->connection_mode === 'remote' && $config['remoteOnly'] === true) {

            $this->db = new LibSQL("libsql:dbname={$config['syncUrl']};authToken={$config['authToken']}");

        } elseif ($this->connection_mode === 'remote_replica') {

            $config['url'] = 'file:'.str_replace('file:', '', database_path($config['url']));
            $removeKeys = ['driver', 'name', 'prefix', 'name', 'database', 'remoteOnly'];
            foreach ($removeKeys as $key) {
                unset($config[$key]);
            }
            $this->db = new LibSQL($config);

        } else {

            throw new ConfigurationIsNotFound('Connection not found!');
        }
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
            ["\\", "\x00", "\n", "\r", "\x1a", "'", '"'],
            ["\\\\", "\\0", "\\n", "\\r", "\\Z", "\\'", '\\"'],
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
        if ((strpos($path, 'file:') !== false || $path !== 'file:') && ! empty($url) && ! empty($token) && $remoteOnly === false) {
            $this->connection_mode = 'remote_replica';
            $path = [
                'mode' => $this->connection_mode,
                'uri' => $path,
                'url' => $url,
                'token' => $token,
            ];
        } elseif (strpos($path, 'file:') !== false && ! empty($url) && ! empty($token) && $remoteOnly === true) {
            $this->connection_mode = 'remote';
            $path = [
                'mode' => $this->connection_mode,
                'uri' => $path,
                'url' => $url,
                'token' => $token,
            ];
        } elseif (strpos($path, 'file:') !== false) {
            $this->connection_mode = 'local';
            $path = [
                'mode' => $this->connection_mode,
                'uri' => str_replace('file:', '', $path),
            ];
        } elseif ($path === ':memory:') {
            $this->connection_mode = 'memory';
            $path = [
                'mode' => $this->connection_mode,
                'uri' => $path,
            ];
        } else {
            $path = false;
        }

        return $path;
    }

    public function __destruct()
    {
        $this->db->close();
    }
}
