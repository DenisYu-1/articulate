<?php

namespace Articulate\Exceptions;

use Exception;

class TransactionRequiredException extends Exception {
    public function __construct(string $message = 'A transaction is required for this operation', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
