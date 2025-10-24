<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Database;

use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Support\Fluent;
use Override;
use RuntimeException;

class LibSQLSchemaGrammar extends SQLiteGrammar
{
    public function compileDropAllIndexes(): string
    {
        return "SELECT 'DROP INDEX IF EXISTS \"' || name || '\";' FROM sqlite_schema WHERE type = 'index' AND name NOT LIKE 'sqlite_%'";
    }

    public function compileDropAllTables($schema = null): string
    {
        return "SELECT 'DROP TABLE IF EXISTS \"' || name || '\";' FROM sqlite_schema WHERE type = 'table' AND name NOT LIKE 'sqlite_%'";
    }

    public function compileDropAllTriggers(): string
    {
        return "SELECT 'DROP TRIGGER IF EXISTS \"' || name || '\";' FROM sqlite_schema WHERE type = 'trigger' AND name NOT LIKE 'sqlite_%'";
    }

    #[Override]
    public function wrap($value, $prefixAlias = false): string
    {
        return str_replace('"', '\'', parent::wrap($value));
    }

    public function typeVector(Fluent $column): string
    {
        if (!empty($column->dimensions)) {
            return "F32_BLOB({$column->dimensions})";
        }

        throw new RuntimeException('Dimension must be set for vector embedding');
    }
}
