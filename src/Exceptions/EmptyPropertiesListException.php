<?php

namespace Articulate\Exceptions;

class EmptyPropertiesListException extends ArticulateException {
    public function __construct(string $tableName, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct('No columns specified for ' . $tableName, $code, $previous);
    }
}
