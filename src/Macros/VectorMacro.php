<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Macros;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VectorMacro
{
    /**
     * Validate a SQL identifier (table, column or index name).
     *
     * These cannot be passed as bound parameters: `vector_top_k()` takes its
     * index name as a string literal, and `CREATE INDEX` accepts no
     * placeholder for a table, column or index name. Validating against a
     * strict pattern is therefore the only option available here.
     *
     * @throws InvalidArgumentException
     */
    public static function assertIdentifier(string $name, string $what): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new InvalidArgumentException("Invalid {$what}: {$name}");
        }

        return $name;
    }

    /**
     * Quote a validated identifier for interpolation.
     */
    public static function quoteIdentifier(string $name, string $what): string
    {
        return '"' . self::assertIdentifier($name, $what) . '"';
    }

    /**
     * Build a vector literal from an array of numbers.
     *
     * Each component is validated rather than cast. A bare `(float)` cast is
     * safe but lenient -- PHP would quietly reduce a non-numeric string to
     * `0.0` or to its leading digits, turning a malformed request into a
     * silently wrong query instead of an error.
     *
     * `var_export()` renders each component rather than string interpolation,
     * because interpolation is bounded by the `precision` ini setting -- 14
     * significant digits by default -- so a component would be quietly
     * rounded on its way into the query. `var_export()` uses
     * `serialize_precision`, which defaults to the shortest representation
     * that round-trips exactly.
     *
     * @param  array<int, mixed>  $vector
     *
     * @throws InvalidArgumentException
     */
    public static function vectorLiteral(array $vector): string
    {
        if ($vector === []) {
            throw new InvalidArgumentException('Vector must not be empty.');
        }

        $components = [];

        foreach ($vector as $index => $component) {
            if (! is_numeric($component)) {
                throw new InvalidArgumentException("Vector component {$index} is not numeric.");
            }

            $value = (float) $component;

            if (! is_finite($value)) {
                throw new InvalidArgumentException("Vector component {$index} is not finite.");
            }

            $components[] = var_export($value, true);
        }

        return '[' . implode(',', $components) . ']';
    }

    /**
     * Validate a row limit.
     *
     * A numeric string is accepted, as is a float with no fractional part, so
     * that a limit taken straight from request input still works. Anything
     * else -- a fraction, a non-numeric string, a value below one -- is an
     * error rather than something to coerce.
     *
     * @throws InvalidArgumentException
     */
    public static function assertLimit(mixed $limit): int
    {
        if (is_int($limit)) {
            $value = $limit;
        } elseif (is_float($limit) && is_finite($limit) && $limit === floor($limit)) {
            $value = (int) $limit;
        } elseif (is_string($limit) && preg_match('/^[+-]?[0-9]+$/', $limit) === 1) {
            $value = (int) $limit;
        } else {
            throw new InvalidArgumentException('Limit must be an integer.');
        }

        if ($value < 1) {
            throw new InvalidArgumentException('Limit must be greater than zero.');
        }

        return $value;
    }

    public static function register(): void
    {
        Blueprint::macro('vectorIndex', function ($column, $indexName) {
            /** @var Blueprint $this * */
            $index = VectorMacro::quoteIdentifier((string) $indexName, 'index name');
            $table = VectorMacro::quoteIdentifier((string) $this->table, 'table name');
            $column = VectorMacro::quoteIdentifier((string) $column, 'column name');

            return DB::statement("CREATE INDEX {$index} ON {$table}(libsql_vector_idx({$column}))");
        });

        Builder::macro('nearest', function ($indexName, $vector, $limit = 10) {
            /** @var Builder $this * */
            // Not quoted: vector_top_k() receives the index name as a string
            // literal, not as an identifier. Validated, so it holds no quote.
            $index = VectorMacro::assertIdentifier((string) $indexName, 'index name');
            $literal = VectorMacro::vectorLiteral($vector);
            $limit = VectorMacro::assertLimit($limit);

            return $this->joinSub(
                DB::table(DB::raw("vector_top_k('{$index}', '{$literal}', {$limit})")),
                'v',
                "{$this->from}.rowid",
                '=',
                'v.id'
            );
        });
    }
}
