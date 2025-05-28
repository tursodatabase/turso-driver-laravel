<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Database;

use Exception;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\SQLiteBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Turso\Driver\Laravel\Exceptions\FeatureNotSupportedException;

class LibSQLSchemaBuilder extends SQLiteBuilder
{
    public function __construct(protected LibSQLDatabase $db, Connection $connection)
    {
        parent::__construct($connection);
    }

    public function createDatabase($name)
    {
        throw new FeatureNotSupportedException('Creating database is not supported in LibSQL database.');
    }

    public function dropDatabaseIfExists($name)
    {
        throw new FeatureNotSupportedException('Dropping database is not supported in LibSQL database.');
    }

    protected function dropAllIndexes(): void
    {
        $statement = $this->db->prepare($this->grammar()->compileDropAllIndexes());
        $results = $statement->query();

        collect($results->rows)->each(function (array $query) {
            $query = array_values($query)[0];
            $this->db->query($query);
        });
    }

    public function dropAllTables(): void
    {
        $this->dropAllTriggers();
        $this->dropAllIndexes();

        $this->db->exec($this->grammar()->compileDisableForeignKeyConstraints());

        $statement = $this->db->prepare($this->grammar()->compileDropAllTables());
        $results = $statement->query();

        collect($results->rows)->each(function (array $query) {
            $query = array_values($query)[0];
            $this->db->query($query);
        });

        $this->db->exec($this->grammar()->compileEnableForeignKeyConstraints());
    }

    protected function dropAllTriggers(): void
    {
        $statement = $this->db->prepare($this->grammar()->compileDropAllTriggers());
        $results = $statement->query();

        collect($results->rows)->each(function (array $query) {
            $query = array_values($query)[0];
            $this->db->query($query);
        });
    }

    public function dropAllViews()
    {
        foreach ($this->getCurrentSchemaListing() as $schema) {
            $this->pragma('writable_schema', 1);

            $this->connection->statement($this->grammar()->compileDropAllViews($schema));

            $this->pragma('writable_schema', 0);

            $this->connection->statement($this->grammar->compileRebuild($schema));
        }
    }

    /**
     * Get the views that belong to the database.
     *
     * @return array
     */
    public function getViews($schema = null)
    {
        $schema ??= array_column($this->getSchemas(), 'name');

        $views = [];

        foreach (Arr::wrap($schema) as $name) {
            $views = array_merge($views, $this->connection->selectFromWriteConnection(
                $this->grammar->compileViews($name)
            ));
        }

        return $this->connection->getPostProcessor()->processViews($views);
    }

    public function getColumns($table)
    {
        $table = $this->connection->getTablePrefix() . $table;

        $exists = $this->connection->selectOne("SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'");
        if (!$exists) {
            throw new Exception("Table '{$table}' does not exist in the database.");
        }

        $data = $this->connection->select("PRAGMA table_xinfo('{$table}')");
        $columns = $this->connection->selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name='{$table}'");

        if (!$columns) {
            return [];
        }

        $pattern = '/(?:\(|,)\s*[\'"`]?([a-zA-Z_][a-zA-Z0-9_]*)[\'"`]?\s+[a-zA-Z]+/i';
        preg_match_all($pattern, $columns->sql, $matches);
        $columnMatches = $matches[1] ?? [];

        $delctypes = stdClassToArray($data);
        foreach ($delctypes as $key => $value) {
            // Check if the column name exists in the matches
            if (isset($delctypes[$key]['name'], $columnMatches[$key])) {
                $delctypes[$key]['name'] = $columnMatches[$key];
            }

            if (isset($delctypes[$key]['type'])) {
                $type = strtolower($delctypes[$key]['type']);
                $delctypes[$key]['type'] = $type;
                $delctypes[$key]['type_name'] = $type;
            }

            if (isset($delctypes[$key]['notnull'])) {
                $delctypes[$key]['nullable'] = $delctypes[$key]['notnull'] === 1 ? false : true;
            }

            if (isset($delctypes[$key]['dflt_value'])) {
                $delctypes[$key]['default'] = $delctypes[$key]['dflt_value'] === 'NULL'
                    ? null
                    : new Expression(Str::wrap($delctypes[$key]['dflt_value'], '(', ')'));
            }

            if (isset($delctypes[$key]['pk'])) {
                $delctypes[$key]['auto_increment'] = $delctypes[$key]['pk'] === 1 ? true : false;
            }

            $delctypes[$key]['collation'] = null;
            $delctypes[$key]['comment'] = null;
            $delctypes[$key]['generation'] = null;
        }

        $keyOrder = ['name', 'type_name', 'type', 'collation', 'nullable', 'default', 'auto_increment', 'comment', 'generation', 'pk', 'notnull', 'dflt_value', 'cid', 'hidden'];
        $delctypes = reorderArrayKeys($delctypes, $keyOrder);

        return $delctypes;
    }

    protected function grammar(): LibSQLSchemaGrammar
    {
        $grammar = new LibSQLSchemaGrammar($this->connection);

        return $grammar;
    }
}
