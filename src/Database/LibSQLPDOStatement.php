<?php

namespace Turso\Driver\Laravel\Database;

use LibSQL;
use PDO;
use Turso\Driver\Laravel\Enums\LibSQLType;
use Turso\Driver\Laravel\Enums\PdoParam;

class LibSQLPDOStatement
{
    protected int $affectedRows = 0;

    protected int $fetchMode = PDO::FETCH_ASSOC;

    protected array $bindings = [];

    protected array $response = [];

    protected array $lastInsertIds = [];

    public function __construct(
        protected LibSQLDatabase $db,
        protected string $query
    ) {
        // Use regex to find and replace incorrect table prefixes in the SET clause
        $correctedQuery = preg_replace('/(\s|,)"[a-zA-Z0-9_]+"."([a-zA-Z0-9_]+)"\s*=\s*\?/', ' "$2" = ?', $query);
        $this->query = $correctedQuery;
    }

    public function setFetchMode(int $mode, mixed ...$args): bool
    {
        $this->fetchMode = $mode;

        return true;
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $type = LibSQLType::fromValue($value);
        $this->bindings[$param] = $type->bind($value);

        return true;
    }

    public function prepare(string $query)
    {
        return new self($this->db, $query);
    }

    public function query(array $parameters = []): array
    {
        return $this->db->getDb()->query($this->query, $parameters)->fetchArray(LibSQL::LIBSQL_ALL);
    }

    public function execute(array $parameters = []): bool
    {
        collect((array) $parameters)
            ->each(function (mixed $value, int $key) {
                $type = PdoParam::fromValue($value);
                $this->bindValue($key, $value, $type->value);
            });

        if (str_starts_with(strtolower($this->query), 'select') || str_starts_with(strtolower($this->query), 'drop')) {
            $this->response = $this->db->getDb()->query($this->query, array_column($this->bindings, 'value'))->fetchArray(LibSQL::LIBSQL_ALL);
        } else {
            $statement = $this->db->getDb()->prepare($this->query);
            $this->response = $statement->query(array_column($this->bindings, 'value'))->fetchArray(LibSQL::LIBSQL_ALL);
        }

        $lastId = (int) $this->response['last_insert_rowid'];
        if ($lastId > 0) {
            $this->db->setLastInsertId(value: $lastId);
        }

        $this->affectedRows = $this->response['rows_affected'];

        return $this->affectedRows > 0;
    }

    public function fetch(
        int $mode = PDO::FETCH_DEFAULT,
        int $cursorOrientation = PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ): mixed {
        if ($mode === PDO::FETCH_DEFAULT) {
            $mode = $this->fetchMode;
        }

        if (empty($this->response['rows'])) {
            return false;
        }

        $rows = array_shift($this->response['rows']);
        $rowValues = array_values($rows);

        return match ($mode) {
            PDO::FETCH_BOTH => array_merge(
                $rows,
                $rowValues
            ),
            PDO::FETCH_ASSOC, PDO::FETCH_NAMED => $rows,
            PDO::FETCH_NUM => $rowValues,
            PDO::FETCH_OBJ => (object) $rows,

            default => throw new \PDOException('Unsupported fetch mode.'),
        };
    }

    #[\ReturnTypeWillChange]
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, ...$args): array
    {
        if ($mode === PDO::FETCH_DEFAULT) {
            $mode = $this->fetchMode;
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
        return max((int) count($this->response['rows']), $this->affectedRows);
    }
}
