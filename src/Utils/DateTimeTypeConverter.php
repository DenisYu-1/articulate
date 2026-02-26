<?php

namespace Articulate\Utils;

use DateTime;
use DateTimeImmutable;

class DateTimeTypeConverter implements TypeConverterInterface {
    public function convertToDatabase(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTime || $value instanceof DateTimeImmutable) {
            return $value->format('Y-m-d H:i:s');
        }

        return $value;
    }

    public function convertToPHP(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTime) {
            return $value;
        }

        if (is_string($value)) {
            return new DateTime($value);
        }

        return $value;
    }
}
