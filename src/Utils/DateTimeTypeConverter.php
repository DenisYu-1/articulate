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

    public function convertToPHP(mixed $value, ?string $targetType = null): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeImmutable || $value instanceof DateTime) {
            if ($targetType === DateTimeImmutable::class && $value instanceof DateTime) {
                return DateTimeImmutable::createFromMutable($value);
            }

            if ($targetType === DateTime::class && $value instanceof DateTimeImmutable) {
                return DateTime::createFromImmutable($value);
            }

            return $value;
        }

        if (is_string($value)) {
            if ($targetType === DateTimeImmutable::class) {
                return new DateTimeImmutable($value);
            }

            return new DateTime($value);
        }

        return $value;
    }
}
