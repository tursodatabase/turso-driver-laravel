<?php

declare(strict_types=1);

function arrayToStdClass(array $array): array
{
    $result = [];

    foreach ($array as $item) {
        $formattedItem = [];

        foreach ($item as $key => $value) {
            if (is_array($value) && ! is_vector($value)) {
                // Encode only the nested array as a JSON string
                $formattedItem[$key] = json_encode($value);
            } else {
                $formattedItem[$key] = $value;
            }
        }

        // Convert the formatted item to a stdClass
        $result[] = (object) $formattedItem;
    }

    return $result;
}

function stdClassToArray(\stdClass|array $object): array
{
    if (is_array($object)) {
        return array_map('stdClassToArray', $object);
    }

    if (! $object instanceof \stdClass) {
        return $object;
    }

    $array = [];

    foreach (get_object_vars($object) as $key => $value) {
        $array[$key] = (is_array($value) || $value instanceof \stdClass)
            ? stdClassToArray($value)
            : $value;
    }

    return $array;
}

/**
 * Decode result-row cells exactly the way LibSQLPDOStatement decodes them:
 * rows stored as double-base64 (blob round-trip through the extension) and
 * cells holding JSON arrays (vector storage). Extracted so the streaming
 * cursor() path decodes identically to select().
 */
function decodeDoubleBase64Rows(array $rows): array
{
    $isBase64 = static function (string $str): bool {
        if (! preg_match('/^(?:[A-Za-z0-9+\/]{4})*(?:[A-Za-z0-9+\/]{2}==|[A-Za-z0-9+\/]{3}=)?$/', $str)) {
            return false;
        }

        $decoded = base64_decode($str, true);
        if ($decoded === false) {
            return false;
        }

        if (base64_encode($decoded) !== $str) {
            return false;
        }

        if (! ctype_print($decoded)) {
            return false;
        }

        return true;
    };

    $isValidDateOrTimestamp = static function (string $string): bool {
        if (is_numeric($string) && (int) $string > 0 && (int) $string <= PHP_INT_MAX) {
            return true;
        }

        if ((is_numeric($string) && strlen($string) === 4 && (int) $string >= 1000 && (int) $string <= 9999)) {
            return true;
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d'] as $format) {
            $dateTime = \DateTime::createFromFormat($format, $string);
            if ($dateTime && $dateTime->format($format) === $string) {
                return true;
            }
        }

        return false;
    };

    foreach ($rows as &$row) {
        foreach ($row as $key => &$value) {
            if (is_string($value) && str_starts_with($value, 'libsqlblob:')) {
                // Marker written by parameterCasting() for transported blobs —
                // unambiguous, unlike the legacy double-base64 heuristic.
                $value = base64_decode(base64_decode(substr($value, strlen('libsqlblob:'))));

                continue;
            }

            if (is_string($value) && preg_match('/^[A-Za-z]{1,24}$/', $value)) {
                // Plain alphabetic words (e.g. Faker names) are never
                // transported blobs; the legacy heuristic would corrupt them.
                continue;
            }

            if (is_string($value) && $isValidDateOrTimestamp($value)) {
                continue;
            }

            if (is_string($value) && $decoded = json_decode($value, true)) {
                $value = $decoded;
            }

            if (is_string($value) && $isBase64($value)) {
                $value = base64_decode(base64_decode($value));
            }
        }
    }
    unset($row, $value);

    return $rows;
}

function decodeBlobs(array $row): array
{
    return array_map(function ($value) {
        return is_resource($value) ? stream_get_contents($value) : $value;
    }, $row);
}

function reorderArrayKeys(array $data, array $keyOrder): array
{
    return array_map(function ($item) use ($keyOrder) {
        $ordered = array_fill_keys($keyOrder, null);

        return array_merge($ordered, $item);
    }, $data);
}

function is_vector($value): bool
{
    if (! is_array($value)) {
        return false;
    }

    foreach ($value as $element) {
        if (! is_numeric($element)) {
            return false;
        }
    }

    return array_keys($value) === range(0, count($value) - 1);
}
