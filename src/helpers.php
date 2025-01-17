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
