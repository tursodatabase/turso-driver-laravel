<?php
declare(strict_types=1);

namespace Turso\Driver\Laravel\Database\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Facades\DB;

class VectorCast implements CastsAttributes
{
    public function set($model, $key, $value, $attributes)
    {
        return DB::raw("vector32('[" . implode(',', $value) . "]')");
    }

    public function get($model, $key, $value, $attributes)
    {
        return json_decode($value, true);
    }
}
