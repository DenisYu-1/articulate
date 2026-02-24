<?php

namespace Articulate\QueryLogger;

interface QueryLoggerInterface
{
    public function log(string $sql, array $parameters, float $durationMs): void;
}
