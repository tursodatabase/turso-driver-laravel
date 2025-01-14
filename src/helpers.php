<?php

function arrayToStdClass(array $array): \stdClass|array
{
    if (empty($array)) {
        return [];
    }

    $object = new \stdClass();

    foreach ($array as $key => $value) {
        $object->{$key} = is_array($value) ? arrayToStdClass($value) : $value;
    }

    return $object;
}

function stdClassToArray(\stdClass|array $object): array
{
    if (is_array($object)) {
        return array_map('stdClassToArray', $object);
    }

    if (!$object instanceof \stdClass) {
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
