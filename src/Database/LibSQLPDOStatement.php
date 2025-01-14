<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Database;

use Illuminate\Support\Carbon;
use LibSQL;
use PDO;

class LibSQLPDOStatement
{
    protected int $affectedRows = 0;

    protected int $mode = PDO::FETCH_OBJ;

    protected array $bindings = [];

    protected array|object $response = [];

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

    public function bindValue($parameter, $value, $type = PDO::PARAM_STR): self
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

    public function prepare(string $query): self
    {
        return new self($this->statement, $query);
    }

    public function query(array $parameters = []): mixed
    {
        if (empty($parameters)) {
            $parameters = $this->bindings;

            // Determine if parameters are named or positional
            if ($this->hasNamedParameters($parameters)) {
                $this->statement->bindNamed($parameters);
            } else {
                $parameters = $this->parameterCasting($parameters);
                $this->statement->bindPositional(array_values($parameters));
            }

            $result = $this->statement->query()->fetchArray(LibSQL::LIBSQL_ALL);
            $rows = $this->decodeDoubleBase64($result);

            return match ($this->mode) {
                PDO::FETCH_ASSOC => collect($rows),
                PDO::FETCH_OBJ => (object) $rows,
                PDO::FETCH_NUM => array_values($rows),
                default => collect($rows)
            };
        }

        $parameters = $this->parameterCasting($parameters);
        $result = $this->statement->query($parameters)->fetchArray(LibSQL::LIBSQL_ALL);
        $rows = $this->decodeDoubleBase64($result);

        return match ($this->mode) {
            PDO::FETCH_ASSOC => collect($rows),
            PDO::FETCH_OBJ => (object) $rows,
            PDO::FETCH_NUM => array_values($rows),
            default => collect($rows)
        };
    }

    private function parameterCasting(array $parameters): array
    {
        $parameters = collect(array_values($parameters))->map(function ($value) {
            $type = match (true) {
                is_string($value) && (! ctype_print($value) || ! mb_check_encoding($value, 'UTF-8')) => 'blob',
                is_float($value) || is_float($value) => 'float',
                is_int($value) => 'integer',
                is_bool($value) => 'boolean',
                $value === null => 'null',
                $value instanceof Carbon => 'datetime',
                is_vector($value) => 'vector',
                default => 'text',
            };

            if ($type === 'blob') {
                $value = base64_encode(base64_encode($value));
            }

            if ($type === 'boolean') {
                $value = (int) $value;
            }

            if ($type === 'datetime') {
                $value = $value->toDateTimeString();
            }

            if ($type === 'vector') {
                $value = json_encode($value);
            }

            return $value;
        })->toArray();

        return $parameters;
    }

    private function decodeDoubleBase64(array $result): array
    {
        if (isset($result['rows']) && is_array($result['rows'])) {
            foreach ($result['rows'] as &$row) {
                foreach ($row as $key => &$value) {
                    if (is_string($value) && $this->isValidDateOrTimestamp($value)) {
                        continue;
                    }

                    if (is_string($value) && $decoded = json_decode($value, true)) {
                        $value = $decoded;
                    }

                    if (is_string($value) && $this->isValidBlob($value)) {
                        $value = base64_decode(base64_decode($value));
                    }
                }
            }
        }

        return $result;
    }

    private function isValidBlob(mixed $value): bool
    {
        return (bool) preg_match('/^(?:[A-Za-z0-9+\/]{4})*(?:[A-Za-z0-9+\/]{2}==|[A-Za-z0-9+\/]{3}=)?$/', $value);
    }

    private function isValidDateOrTimestamp($string, $format = null): bool
    {
        if (is_numeric($string) && (int) $string > 0 && (int) $string <= PHP_INT_MAX) {
            return true;
        }

        if (is_numeric($string) && strlen($string) === 4 && (int) $string >= 1000 && (int) $string <= 9999) {
            return true;
        }

        $formats = $format ? [$format] : ['Y-m-d H:i:s', 'Y-m-d'];

        foreach ($formats as $fmt) {
            $dateTime = \DateTime::createFromFormat($fmt, $string);
            if ($dateTime && $dateTime->format($fmt) === $string) {
                return true;
            }
        }

        return false;
    }

    public function execute(array $parameters = []): bool
    {
        if (empty($parameters)) {
            $parameters = $this->bindings;
        }

        try {
            // Determine if parameters are named or positional
            if ($this->hasNamedParameters($parameters)) {
                $this->bindings = $parameters;
                $this->statement->bindNamed($parameters);
            } else {
                $parameters = $this->parameterCasting($parameters);
                $this->bindings = $parameters;
                $this->statement->bindPositional(array_values($parameters));
            }

            if (str_starts_with(strtolower($this->query), 'select')) {
                $queryRows = $this->statement->query($parameters)->fetchArray(LibSQL::LIBSQL_ASSOC);
                $this->affectedRows = count($queryRows);
            } else {
                $this->affectedRows = $this->statement->execute($parameters);
            }

            return true;
        } catch (\Exception $e) {
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
            $parameters = $this->parameterCasting($parameters);
            $this->statement->bindPositional(array_values($parameters));
        }
        $result = $this->statement->query();
        $rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);

        if (! $rows) {
            $anotherStatement = $this->statement->query($parameters);
            $rows = $anotherStatement->fetchArray(LibSQL::LIBSQL_ASSOC);

            if (! $rows) {
                return false;
            }

            $row = $rows[$cursorOffset];
            $mode = PDO::FETCH_ASSOC;

            $data = match ($mode) {
                PDO::FETCH_ASSOC => $row,
                PDO::FETCH_OBJ => (object) $row,
                PDO::FETCH_NUM => array_values($row),
                default => $row
            };

            $this->bindings = [];
            $parameters = [];

            return $data;
        }

        $row = $rows[$cursorOffset];
        $row = array_map(function ($value) {
            return is_string($value) && base64_encode(base64_decode($value, true)) === $value
                ? base64_decode($value)
                : $value;
        }, $row);
        $mode = $this->mode ?? $mode;

        return match ($mode) {
            PDO::FETCH_ASSOC => collect($row),
            PDO::FETCH_OBJ => (object) $row,
            PDO::FETCH_NUM => array_values($row),
            default => collect($row)
        };
    }

    #[\ReturnTypeWillChange]
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, ...$args): array
    {
        if ($mode === PDO::FETCH_DEFAULT) {
            $mode = $this->mode;
        }

        $parameters = $this->bindings;
        if ($this->hasNamedParameters($parameters)) {
            $this->statement->bindNamed($parameters);
        } else {
            $parameters = $this->parameterCasting($parameters);
            $this->statement->bindPositional(array_values($parameters));
        }
        $result = $this->statement->query($parameters);
        $response = $result->fetchArray(LibSQL::LIBSQL_ALL);

        $allRows = $response['rows'];
        $decodedRows = $this->parameterCasting($allRows);
        $rowValues = \array_map('array_values', $decodedRows);

        $response = match ($mode) {
            PDO::FETCH_BOTH => [...$allRows, ...$rowValues],
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
