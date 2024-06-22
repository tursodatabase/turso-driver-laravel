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
        $statement->execute();

        collect($statement->fetchAll(\PDO::FETCH_NUM))->each(function (array $query) {
            $this->db->query($query[0]);
        });
    }

    public function dropAllTables(): void
    {
        $this->dropAllTriggers();
        $this->dropAllIndexes();

        $this->db->query($this->grammar()->compileDisableForeignKeyConstraints());

        $statement = $this->db->prepare($this->grammar()->compileDropAllTables());
        $statement->execute();

        collect($statement->fetchAll(\PDO::FETCH_NUM))->each(function (array $query) {
            $this->db->query($query[0]);
        });

        $this->db->query($this->grammar()->compileEnableForeignKeyConstraints());
    }

    protected function dropAllTriggers(): void
    {
        $statement = $this->db->prepare($this->grammar()->compileDropAllTriggers());
        $statement->execute();

        collect($statement->fetchAll(\PDO::FETCH_NUM))->each(function (array $query) {
            $this->db->query($query[0]);
        });
    }

    public function dropAllViews(): void
    {
        $statement = $this->db->prepare($this->grammar()->compileDropAllViews());
        $statement->execute();

        collect($statement->fetchAll(\PDO::FETCH_NUM))->each(function (array $query) {
            $this->db->query($query[0]);
        });
    }

    protected function grammar(): LibSQLSchemaGrammar
    {
        $grammar = new LibSQLSchemaGrammar();

        return $grammar;
    }
}
