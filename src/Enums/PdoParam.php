<?php

namespace Turso\Driver\Laravel\Enums;

use PDO;

enum PdoParam: int
{
    case NULL = PDO::PARAM_NULL;
    case BOOL = PDO::PARAM_BOOL;
    case INT = PDO::PARAM_INT;
    case STR = PDO::PARAM_STR;
    case LOB = PDO::PARAM_LOB;

    public static function fromValue(mixed $value): static
    {
        return match (gettype($value)) {
            'boolean', 'integer' => self::BOOL,
            'double', 'float' => self::INT,
            'resource' => self::LOB,
            'NULL' => self::NULL,
            default => self::STR,
        };
    }
}
