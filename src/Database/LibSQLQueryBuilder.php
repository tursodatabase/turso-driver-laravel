<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Database;

use Illuminate\Database\Query\Builder;

class LibSQLQueryBuilder extends Builder
{
    public function exists()
    {
        $this->applyBeforeQueryCallbacks();

        $results = $this->connection->select(
            $this->grammar->compileExists($this),
            $this->getBindings(),
            ! $this->useWritePdo
        );

        $results = (array) $results;
        if (isset($results[0])) {
            $results = (array) $results[0];

            return (bool) ($results['exists'] ?? false);
        }

        return false;
    }
}
