<?php

namespace Turso\Driver\Laravel\Database;

use Exception;
use Illuminate\Database\Connection;
use LibSQL;

class LibSQLConnection extends Connection
{
    protected LibSQLDatabase $db;

    /**
     * The active PDO connection used for reads.
     *
     * @var LibSQLDatabase|\Closure
     */
    protected $readPdo;

    protected array $bindings = [];

    public function __construct(LibSQLDatabase $db, string $database = ':memory:', string $tablePrefix = '', array $config = [])
    {
        $libsqlDb = function () use ($db) {
            return $db;
        };
        parent::__construct($libsqlDb, $database, $tablePrefix, $config);

        $this->db = $db;
        $this->schemaGrammar = $this->getDefaultSchemaGrammar();
    }

    public function sync(): void
    {
        $this->db->sync();
    }

    public function getConnectionMode(): string
    {
        return $this->db->getConnectionMode();
    }

    public function statement($query, $bindings = []): bool
    {
        $res = $this->select($query, $bindings);

        return ! empty($res);
    }

    public function getRawPdo(): LibSQL
    {
        return $this->db->getDb();
    }

    public function getPdo(): LibSQLDatabase
    {
        return $this->db;
    }

    public function getReadPdo(): LibSQLDatabase
    {
        return $this->getPdo();
    }

    public function getRawReadPdo(): LibSQL
    {
        return $this->getRawPdo();
    }

    /**
     * Set the LibSQLDatabase connection.
     *
     * @param  LibSQLDatabase|\Closure|null  $pdo
     * @return $this
     */
    public function setPdo($pdo)
    {
        $this->transactions = 0;

        $this->pdo = $pdo;

        return $this;
    }

    /**
     * Set the LibSQLDatabase connection used for reading.
     *
     * @param  LibSQLDatabase|\Closure|null  $pdo
     * @return $this
     */
    public function setReadPdo($pdo)
    {
        $this->readPdo = $pdo;

        return $this;
    }

    public function select($query, $bindings = [], $useReadPdo = true)
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return [];
            }

            $statement = $this->getRawPdo()->prepare($query);

            $results = $statement->query($bindings)->fetchArray(LibSQL::LIBSQL_ASSOC);

            return array_map(fn ($result) => $result, $results);
        });
    }

    public function selectResultSets($query, $bindings = [], $useReadPdo = true)
    {
        return $this->select($query, $bindings, $useReadPdo);
    }

    /**
     * Run a select statement against the database and returns a generator.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return \Generator
     */
    public function cursor($query, $bindings = [], $useReadPdo = true)
    {
        if ($this->pretending()) {
            return [];
        }

        $preparedQuery = $this->getRawPdo()->prepare($query);

        if (! $preparedQuery) {
            throw new Exception('Failed to prepare statement.');
        }

        $statement = $preparedQuery->query($bindings)->fetchArray(LibSQL::LIBSQL_ASSOC);

        foreach ($statement as $record) {
            yield $record;
        }
    }

    public function insert($query, $bindings = [])
    {
        return $this->statement($query, $bindings);
    }

    /**
     * Run an update statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return int
     */
    public function update($query, $bindings = [])
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Run a delete statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return int
     */
    public function delete($query, $bindings = [])
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Run an SQL statement and get the number of rows affected.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return int
     */
    public function affectingStatement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return 0;
            }

            $statement = $this->getRawPdo()->prepare($query);

            $rowCount = $statement->execute($bindings);

            $this->recordsHaveBeenModified(
                ($count = $rowCount) > 0
            );

            return $count;
        });
    }

    /**
     * Run a raw, unprepared query against the libSQL connection.
     *
     * @param  string  $query
     * @return bool
     */
    public function unprepared($query)
    {
        return $this->run($query, [], function ($query) {
            if ($this->pretending()) {
                return true;
            }

            // Assuming $this->libSQL is an instance of LibSQL
            $result = $this->getRawPdo()->execute($query);

            $this->recordsHaveBeenModified($change = $result !== false);

            return $change;
        });
    }

    public function getServerVersion(): string
    {
        return $this->getRawPdo()->version();
    }

    protected function getDefaultQueryGrammar()
    {
        ($grammar = new LibSQLQueryGrammar())->setConnection($this);

        return $grammar;
    }

    public function useDefaultQueryGrammar()
    {
        $this->queryGrammar = $this->getDefaultQueryGrammar();
    }

    /**
     * Get the default schema grammar instance.
     *
     * @return \Illuminate\Database\Schema\Grammars\Grammar
     */
    protected function getDefaultSchemaGrammar()
    {
        ($grammar = new LibSQLSchemaGrammar)->setConnection($this);

        return $this->withTablePrefix($grammar);
    }

    public function useDefaultSchemaGrammar()
    {
        if (is_null($this->schemaGrammar)) {
            $this->schemaGrammar = $this->getDefaultSchemaGrammar();
        }
    }

    public function createReadPdo(array $config): ?LibSQLDatabase
    {
        $db = function () use ($config) {
            return new LibSQLDatabase($config);
        };
        $this->setReadPdo($db);

        return $db();
    }

    protected function escapeBinary(mixed $value): string
    {
        $hex = bin2hex($value);

        return "x'{$hex}'";
    }

    protected function getDefaultPostProcessor(): LibSQLQueryProcessor
    {
        return new LibSQLQueryProcessor();
    }

    public function useDefaultPostProcessor()
    {
        $this->postProcessor = $this->getDefaultPostProcessor();
    }

    public function getSchemaBuilder(): LibSQLSchemaBuilder
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new LibSQLSchemaBuilder($this->db, $this);
    }

    protected function isUniqueConstraintError(Exception $exception): bool
    {
        return boolval(preg_match('#(column(s)? .* (is|are) not unique|UNIQUE constraint failed: .*)#i', $exception->getMessage()));
    }

    public function escapeString($value)
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
        return $this->escapeString($value);
    }

    private function isArrayAssoc(array $data)
    {
        if (empty($data) || ! is_array($data)) {
            return false;
        }

        if (array_keys($data) !== range(0, count($data) - 1)) {
            return true;
        }

        return false;
    }

    private function intoParams($stmt, $named_params)
    {
        foreach ($named_params as $key => $value) {
            if (is_string($value) || is_resource($value)) {
                $value = "'".$value."'";
            }
            $placeholders = [":$key", "@$key", "$$key"];
            $stmt = str_replace($placeholders, $value, $stmt);
        }

        return $stmt;
    }
}
