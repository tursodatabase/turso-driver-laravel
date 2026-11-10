<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Database;

use Exception;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use LibSQL;
use LibSQLStatement;
use PDO;
use PDOException;
use ReturnTypeWillChange;

class LibSQLPDOStatement
{
    protected int $affectedRows = 0;

    protected int $mode = PDO::FETCH_OBJ;

    protected array $bindings = [];

    protected array|object $response = [];

    protected array $lastInsertIds = [];

    public function __construct(
        private ?LibSQLStatement $statement,
        protected string $query,
        private ?LibSQL $direct = null,
    ) {
    }

    /**
     * Offline-write mode: the extension refuses prepare() in offline_write
     * mode (prepared statements would bypass the write journal). The driver
     * instead binds parameters and routes through the connection's
     * execute()/query() methods, which journal writes properly.
     */
    public static function direct(LibSQL $db, string $query): self
    {
        return new self(null, $query, $db);
    }

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
            throw new InvalidArgumentException('Parameter must be an integer or string.');
        }

        return $this;
    }

    public function prepare(string $query): self
    {
        return new self($this->statement, $query, $this->direct);
    }

    public function query(array $parameters = []): mixed
    {
        if ($this->direct !== null) {
            $parameters = empty($parameters) ? $this->bindings : $parameters;
            [$sql, $values] = $this->bindParametersForDirect($parameters);
            $values = $this->parameterCasting($values);

            if (preg_match('/^\s*(select|pragma|with|explain)/i', $sql) !== 1) {
                // DDL/DML — not row-returning. The extension only journals
                // writes submitted through execute() in offline_write mode;
                // route them there too (the fake empty envelope keeps
                // statement()'s rows contract intact).
                $affected = (int) $this->direct->execute($sql, $values);
                $result = ['columns' => [], 'rows' => [], 'rows_affected' => $affected];
            } else {
                $result = $this->direct->query($sql, $values)->fetchArray(LibSQL::LIBSQL_ALL);
            }

            $rows = $this->decodeDoubleBase64($this->normalizeRows($result));

            return match ($this->mode) {
                PDO::FETCH_ASSOC => collect($rows),
                PDO::FETCH_OBJ => (object) $rows,
                PDO::FETCH_NUM => array_values($rows),
                default => collect($rows)
            };
        }

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

    /**
     * The extension's fetchArray() does not always include a `rows` key —
     * e.g. for DDL run through query(). Normalize so downstream code can
     * rely on `$result['rows']` unconditionally.
     */
    private function normalizeRows(mixed $result): array
    {
        if (! is_array($result)) {
            return ['rows' => []];
        }

        // Online flavor: {columns, rows, rows_affected, ...} envelope.
        if (isset($result['rows']) && is_array($result['rows'])) {
            return $result;
        }

        // Offline flavor: fetchArray(ALL) is a plain list of row objects —
        // repackage it as the same envelope.
        return ['rows' => array_values($result), 'columns' => array_keys($result[0] ?? [])];
    }

    private function parameterCasting(array $parameters): array
    {
        $parameters = collect(array_values($parameters))->map(function ($value) {
            $type = match (true) {
                $this->is_blob($value) => 'blob',
                is_float($value) || is_float($value) => 'float',
                is_int($value) => 'integer',
                is_bool($value) => 'boolean',
                $value === null => 'null',
                $value instanceof Carbon => 'datetime',
                is_vector($value) => 'vector',
                default => 'text',
            };

            if ($type === 'blob') {
                // Transported blobs carry an explicit marker so reads can
                // decode them unambiguously (see decodeDoubleBase64Rows()).
                $value = 'libsqlblob:' . base64_encode(base64_encode($value));
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
        // Shared with the streaming cursor() path — see helpers.php
        if (isset($result['rows']) && is_array($result['rows'])) {
            $result['rows'] = decodeDoubleBase64Rows($result['rows']);
        }

        return $result;
    }

    private function is_blob($value): bool
    {
        return is_string($value) && !$this->isValidBlob($value) && !mb_check_encoding($value, 'UTF-8');
    }

    private function isValidBlob($data)
    {
        return (bool) is_string($data) && preg_match('/^(?:[A-Za-z0-9+\/]{4})*(?:[A-Za-z0-9+\/]{2}==|[A-Za-z0-9+\/]{3}=)?$/', $data);
    }

    public function execute(array $parameters = []): bool
    {
        if ($this->direct !== null) {
            $parameters = empty($parameters) ? $this->bindings : $parameters;
            [$sql, $values] = $this->bindParametersForDirect($parameters);
            $values = $this->parameterCasting($values);

            try {
                if (preg_match('/^\s*(select|pragma|with|explain)/i', $sql) === 1) {
                    $rows = $this->normalizeRows($this->direct->query($sql, $values)->fetchArray(LibSQL::LIBSQL_ALL));
                    $this->affectedRows = count($rows['rows']);
                } else {
                    $this->affectedRows = (int) $this->direct->execute($sql, $values);
                }

                return true;
            } catch (Exception $e) {
                // Rethrow as PDOException so Laravel reports the failure instead of ignoring it
                $exception = new PDOException($e->getMessage(), 0, $e);
                $exception->errorInfo = ['HY000', $e->getCode(), $e->getMessage()];

                throw $exception;
            }
        }

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
        } catch (Exception $e) {
            // Rethrow as PDOException so Laravel reports the failure instead of ignoring it
            $exception = new PDOException($e->getMessage(), 0, $e);
            $exception->errorInfo = ['HY000', $e->getCode(), $e->getMessage()];

            throw $exception;
        }
    }

    #[ReturnTypeWillChange]
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): array|false
    {
        if ($mode === PDO::FETCH_DEFAULT) {
            $mode = $this->mode;
        }

        if ($this->direct !== null) {
            $parameters = $this->bindings;
            [$sql, $values] = $this->bindParametersForDirect($parameters);
            $values = $this->parameterCasting($values);
            $rows = $this->direct->query($sql, $values)->fetchArray(LibSQL::LIBSQL_ASSOC);

            if (! $rows) {
                return false;
            }

            $row = $rows[$cursorOffset];
            $row = decodeDoubleBase64Rows([$row])[0];

            return match ($mode) {
                PDO::FETCH_ASSOC => collect($row),
                PDO::FETCH_OBJ => (object) $row,
                PDO::FETCH_NUM => array_values($row),
                default => collect($row)
            };
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

        if (!$rows) {
            $anotherStatement = $this->statement->query($parameters);
            $rows = $anotherStatement->fetchArray(LibSQL::LIBSQL_ASSOC);

            if (!$rows) {
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
        $row = decodeDoubleBase64Rows([$row])[0];
        $mode = $this->mode ?? $mode;

        return match ($mode) {
            PDO::FETCH_ASSOC => collect($row),
            PDO::FETCH_OBJ => (object) $row,
            PDO::FETCH_NUM => array_values($row),
            default => collect($row)
        };
    }

    #[ReturnTypeWillChange]
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, ...$args): array
    {
        if ($mode === PDO::FETCH_DEFAULT) {
            $mode = $this->mode;
        }

        if ($this->direct !== null) {
            $parameters = $this->bindings;
            [$sql, $values] = $this->bindParametersForDirect($parameters);
            $values = $this->parameterCasting($values);
            $result = $this->direct->query($sql, $values);
            $response = $this->normalizeRows($result->fetchArray(LibSQL::LIBSQL_ALL));

            $allRows = $response['rows'];
            $rowValues = \array_map('array_values', $allRows);

            return match ($mode) {
                PDO::FETCH_BOTH => [...$allRows, ...$rowValues],
                PDO::FETCH_ASSOC, PDO::FETCH_NAMED => $allRows,
                PDO::FETCH_NUM => $rowValues,
                PDO::FETCH_OBJ => $allRows,
                default => throw new PDOException('Unsupported fetch mode.'),
            };
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
        $rowValues = \array_map('array_values', $allRows);

        $response = match ($mode) {
            PDO::FETCH_BOTH => [...$allRows, ...$rowValues],
            PDO::FETCH_ASSOC, PDO::FETCH_NAMED => $allRows,
            PDO::FETCH_NUM => $rowValues,
            PDO::FETCH_OBJ => $allRows,
            default => throw new PDOException('Unsupported fetch mode.'),
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

    /**
     * Convert the statement's binding state into a flat positional list the
     * extension's execute()/query() methods accept, translating named
     * placeholders (:name) into positional ones (?) in order of appearance.
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function bindParametersForDirect(array $parameters): array
    {
        if ($this->hasNamedParameters($parameters)) {
            $values = [];
            $sql = (string) preg_replace_callback(
                '/:([a-zA-Z_][a-zA-Z0-9_]*)/',
                static function (array $matches) use (&$values, $parameters): string {
                    $values[] = $parameters[$matches[1]] ?? $parameters[':' . $matches[1]] ?? null;

                    return '?';
                },
                $this->query
            );

            return [$sql, $values];
        }

        return [$this->query, array_values($parameters)];
    }
}
