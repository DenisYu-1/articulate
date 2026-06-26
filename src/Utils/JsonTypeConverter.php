<?php

namespace Articulate\Utils;

class JsonTypeConverter implements TypeConverterInterface {
    public function convertToDatabase(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    public function convertToPHP(mixed $value): mixed
    {
        if ($value === null || is_array($value)) {
            return $value;
        }

        return json_decode($value, associative: true, flags: JSON_THROW_ON_ERROR);
    }
}
