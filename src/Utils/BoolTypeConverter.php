<?php

namespace Articulate\Utils;

/**
 * Converter for boolean values to/from TINYINT(1)
 */
class BoolTypeConverter implements TypeConverterInterface
{
    /**
     * Convert PHP boolean to database TINYINT(1)
     */
    public function convertToDatabase(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return $value ? 1 : 0;
    }

    /**
     * Convert database TINYINT(1) to PHP boolean
     */
    public function convertToPHP(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        // Handle various representations of boolean from database
        return (bool) $value;
    }
}
