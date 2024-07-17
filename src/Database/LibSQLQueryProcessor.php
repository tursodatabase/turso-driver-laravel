<?php

namespace Turso\Driver\Laravel\Database;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Query\Processors\SQLiteProcessor;

class LibSQLQueryProcessor extends SQLiteProcessor
{
    /**
     * Process the list of tables.
     *
     * @param  mixed  $results
     */
    public function processTables($results): array
    {
        $results = (array) $results['rows'];

        return array_map(function ($result) {
            return [
                'name' => $result['name'],
            ];
        }, $results);
    }

    public function processSelect(Builder $query, $results)
    {
        return $results['rows'];
    }
}
