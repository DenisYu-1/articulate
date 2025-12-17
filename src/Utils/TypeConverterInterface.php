<?php

namespace Articulate\Utils;

/**
 * Interface for converting values between PHP and database representations.
 */
interface TypeConverterInterface
{
    /**
     * Convert a PHP value to database representation.
     */
    public function convertToDatabase(mixed $value): mixed;

    /**
     * Convert a database value to PHP representation.
     */
    public function convertToPHP(mixed $value): mixed;
}

