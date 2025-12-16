<?php

namespace Articulate\Utils;

/**
 * Converter for Point objects to/from database POINT type
 */
class PointTypeConverter implements TypeConverterInterface
{
    /**
     * Convert Point object to database representation
     */
    public function convertToDatabase(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Point) {
            return $value->toString();
        }

        throw new \InvalidArgumentException('Value must be a Point instance');
    }

    /**
     * Convert database POINT string to Point object
     */
    public function convertToPHP(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return Point::fromString($value);
        }

        throw new \InvalidArgumentException('Database value must be a POINT string');
    }
}
