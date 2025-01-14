<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Tests\Feature\EloquentAttributeCasting\CastingModels;

use Illuminate\Database\Eloquent\Model;

class CollectionCastingModel extends Model
{
    protected $table = 'collection_casting_table';

    protected $guarded = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'data' => 'collection',
        ];
    }
}