<?php

namespace Turso\Driver\Laravel\Tests\Feature\EloquentAttributeCasting\Enums;

enum Status: int
{
    case Pending = 0;
    case Approved = 1;
    case Rejected = 2;
}
