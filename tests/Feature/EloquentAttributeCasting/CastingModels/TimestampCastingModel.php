<?php
declare(strict_types=1);

namespace Turso\Driver\Laravel\Tests\Feature\EloquentAttributeCasting\CastingModels;

use Illuminate\Database\Eloquent\Model;

class TimestampCastingModel extends Model
{
    protected $table = 'timestamp_casting_table';

    protected $guarded = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'added_at' => 'timestamp',
        ];
    }
}
