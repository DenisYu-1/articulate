<?php

namespace Articulate\Exceptions;

use Exception;

class EntityNotFoundException extends Exception {
    public function __construct(string $className, int $code = 0, ?Exception $previous = null)
    {
        parent::__construct("Entity class '{$className}' is not a valid entity", $code, $previous);
    }
}
