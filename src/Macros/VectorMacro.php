<?php

namespace Turso\Driver\Laravel\Macros;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class VectorMacro
{
    public static function register(): void
    {
        Blueprint::macro('vectorIndex', function ($column, $indexName) {
            /** @var Blueprint $this * */
            return DB::statement("CREATE INDEX {$indexName} ON {$this->table}(libsql_vector_idx({$column}))");
        });

        Builder::macro('nearest', function ($indexName, $vector, $limit = 10) {
            /** @var Builder $this * */
            return $this->joinSub(
                DB::table(DB::raw("vector_top_k('$indexName', '[".implode(',', $vector)."]', $limit)")),
                'v',
                "{$this->from}.rowid",
                '=',
                'v.id'
            );
        });
    }
}
