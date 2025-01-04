<?php

namespace Turso\Driver\Laravel\Database;

use LibSQL;
use PDO;

class LibSQLPDOStatement
{
    protected int $affectedRows = 0;

    protected int $mode = PDO::FETCH_ASSOC;

    protected array $bindings = [];

    protected array $response = [];

    protected array $lastInsertIds = [];

    public function __construct(
        private \LibSQLStatement $statement,
        protected string $query
    ) {}

    public function setFetchMode(int $mode, mixed ...$args): bool
    {
        $this->mode = $mode;

        return true;
    }

    public function bindValue($parameter, $value, $type = PDO::PARAM_STR)
    {
        if (is_int($parameter)) {
            $this->bindings[$parameter] = $value;
        } elseif (is_string($parameter)) {
            $this->bindings[$parameter] = $value;
        } else {
            throw new \InvalidArgumentException('Parameter must be an integer or string.');
        }

        return $this;
    }

    public function prepare(string $query)
    {
        return new self($this->statement, $query);
    }

    public function query(array $parameters = []): array
    {
        if (empty($parameters)) {
            $parameters = $this->bindings;
        }

        // Determine if parameters are named or positional
        if ($this->hasNamedParameters($parameters)) {
            $this->statement->bindNamed($parameters);
        } else {
            $this->statement->bindPositional(array_values($parameters));
        }

        return $this->statement->query()->fetchArray(LibSQL::LIBSQL_ALL);
    }

    public function execute(array $parameters = []): bool
    {
        if (empty($parameters)) {
            $parameters = $this->bindings;
        }

        try {
            // Determine if parameters are named or positional
            if ($this->hasNamedParameters($parameters)) {
                $this->statement->bindNamed($parameters);
            } else {
                $this->statement->bindPositional(array_values($parameters));
            }

            $this->affectedRows = $this->statement->execute($parameters);

            return true;
        } catch (\Exception $e) {
            // Handle exceptions as needed
            return false;
        }
    }

    #[\ReturnTypeWillChange]
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): array|false
    {
        if ($mode === PDO::FETCH_DEFAULT) {
            $mode = $this->mode;
        }

        $parameters = $this->bindings;
        if ($this->hasNamedParameters($parameters)) {
            $this->statement->bindNamed($parameters);
        } else {
            $this->statement->bindPositional(array_values($parameters));
        }
        $result = $this->statement->query();
        $rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);

        if (! $rows) {
            return false;
        }

        $row = $rows[$cursorOffset];
        $mode = $this->mode ?? $mode;

        return match ($mode) {
            PDO::FETCH_ASSOC => $row,
            PDO::FETCH_OBJ => (object) $row,
            PDO::FETCH_NUM => array_values($row),
            default => $row
        };
    }

    #[\ReturnTypeWillChange]
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, ...$args): array
    {
        if ($mode === PDO::FETCH_DEFAULT) {
            $mode = $this->mode;
        }

        $allRows = $this->response['rows'];
        $rowValues = \array_map('array_values', $allRows);

        $response = match ($mode) {
            PDO::FETCH_BOTH => array_merge($allRows, $rowValues),
            PDO::FETCH_ASSOC, PDO::FETCH_NAMED => $allRows,
            PDO::FETCH_NUM => $rowValues,
            PDO::FETCH_OBJ => $allRows,
            default => throw new \PDOException('Unsupported fetch mode.'),
        };

        return $response;
    }

    public function fetchColumn(int $columnIndex = 0)
    {
        $row = $this->fetch();

        return $row ? array_values($row)[$columnIndex] : null;
    }

    public function getAffectedRows(): int
    {
        return $this->affectedRows;
    }

    public function nextRowset(): bool
    {
        // TFIDK: database is support for multiple rowset.
        return false;
    }

    public function rowCount(): int
    {
        return $this->affectedRows;
    }

    public function closeCursor(): void
    {
        $this->statement->reset();
    }

    private function hasNamedParameters(array $parameters): bool
    {
        foreach (array_keys($parameters) as $key) {
            if (is_string($key)) {
                return true;
            }
        }

        return false;
    }
}
