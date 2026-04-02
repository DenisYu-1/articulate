<?php

namespace Articulate\Exceptions;

class TransactionRequiredException extends ArticulateException {
    public function __construct(string $message = 'A transaction is required for this operation', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
