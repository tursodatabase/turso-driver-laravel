<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Database;

use Illuminate\Database\Schema\SQLiteBuilder;
use Turso\Driver\Laravel\Exceptions\FeatureNotSupportedException;

class LibSQLSchemaBuilder extends SQLiteBuilder
{
    public function __construct(protected LibSQLDatabase $db, \Illuminate\Database\Connection $connection)
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

        collect($results['rows'])->each(function (array $query) {
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

        collect($results['rows'])->each(function (array $query) {
            $query = array_values($query)[0];
            $this->db->query($query);
        });

        $this->db->exec($this->grammar()->compileEnableForeignKeyConstraints());
    }

    protected function dropAllTriggers(): void
    {
        $statement = $this->db->prepare($this->grammar()->compileDropAllTriggers());
        $results = $statement->query();

        collect($results['rows'])->each(function (array $query) {
            $query = array_values($query)[0];
            $this->db->query($query);
        });
    }

    public function dropAllViews(): void
    {
        $statement = $this->db->prepare($this->grammar()->compileDropAllViews());
        $results = $statement->query();

        collect($results['rows'])->each(function (array $query) {
            $query = array_values($query)[0];
            $this->db->query($query);
        });
    }

    protected function grammar(): LibSQLSchemaGrammar
    {
        $grammar = new LibSQLSchemaGrammar();

        return $grammar;
    }
}
