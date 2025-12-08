<?php

namespace Articulate\Exceptions;

use Exception;

class EmptyPropertiesList extends Exception
{
    public function __construct(string $tableName, int $code = 0, ?Exception $previous = null)
    {
        parent::__construct('No columns specified for ' . $tableName, $code, $previous);
    }
}
