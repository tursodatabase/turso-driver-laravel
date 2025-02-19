<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Database;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Query\Processors\SQLiteProcessor;
use stdClass;

class LibSQLQueryProcessor extends SQLiteProcessor
{
    /**
     * Process the list of tables.
     *
     * @param  mixed  $results
     */
    public function processTables($results): array
    {
        $results = $results instanceof stdClass ? stdClassToArray($results) : $results;

        return array_map(function ($result) {
            $result = (object) $result;

            return [
                'name' => $result->name,
                'schema' => $result->schema ?? null, // PostgreSQL and SQL Server
                'size' => isset($result->size) ? (int) $result->size : null,
                'comment' => $result->comment ?? null, // MySQL and PostgreSQL
                'collation' => $result->collation ?? null, // MySQL only
                'engine' => $result->engine ?? null, // MySQL only
            ];
        }, $results);
    }

    public function processViews($results)
    {
        $results = $results instanceof stdClass ? stdClassToArray($results) : $results;

        return array_map(function ($result) {
            $result = (object) $result;

            return [
                'name' => $result->name,
                'schema' => $result->schema ?? null, // PostgreSQL and SQL Server
                'definition' => $result->definition,
            ];
        }, $results);
    }

    public function processSelect(Builder $query, $results)
    {
        return $results;
    }
}
