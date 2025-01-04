<?php

namespace Turso\Driver\Laravel\Database;

use Exception;
use Illuminate\Database\Connection;
use Illuminate\Filesystem\Filesystem;
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

    protected array $lastInsertIds = [];

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

    public function getPdo(): LibSQLDatabase
    {
        return $this->db;

    }

    public function getReadPdo(): LibSQLDatabase
    {
        return $this->getPdo();
    }

    public function getRawPdo(): LibSQLDatabase
    {
        return $this->getPdo();
    }

    public function getRawReadPdo(): LibSQLDatabase
    {
        return $this->getRawPdo();
    }

    /**
     * Set the LibSQL connection.
     *
     * @param  LibSQL|\Closure|null  $pdo
     * @return $this
     */
    public function setPdo($pdo)
    {
        $this->transactions = 0;

        $this->pdo = $pdo;

        return $this;
    }

    /**
     * Set the LibSQL connection used for reading.
     *
     * @param  LibSQL|\Closure|null  $pdo
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

            $results = $statement->query($bindings);

            return array_map(fn ($result) => $result, $results['rows']);
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

        $statement = $preparedQuery->query($bindings);

        foreach ($statement as $record) {
            yield $record;
        }
    }

    public function insert($query, $bindings = [])
    {
        return $this->affectingStatement($query, $bindings);
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

            $result = $this->getPdo()->prepare($query)->execute($bindings);

            $this->recordsHaveBeenModified(
                ($count = (int) $result) > 0
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

            $result = $this->getRawPdo()->exec($query);

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
        ($grammar = new LibSQLQueryGrammar)->setConnection($this);
        $this->withTablePrefix($grammar);

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
        $this->withTablePrefix($grammar);

        return $grammar;
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
        return new LibSQLQueryProcessor;
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

    public function getSchemaState(?Filesystem $files = null, ?callable $processFactory = null): LibSQLSchemaState
    {
        return new LibSQLSchemaState($this, $files, $processFactory);
    }

    protected function isUniqueConstraintError(Exception $exception): bool
    {
        return (bool) preg_match('#(column(s)? .* (is|are) not unique|UNIQUE constraint failed: .*)#i', $exception->getMessage());
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
}
