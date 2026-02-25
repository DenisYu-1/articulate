<?php

namespace Articulate\QueryLogger;

use Psr\Log\LoggerInterface;

final class PsrQueryLogger implements QueryLoggerInterface {
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function log(string $sql, array $parameters, float $durationMs): void
    {
        $this->logger->debug('SQL query executed', [
            'sql' => $sql,
            'parameters' => $parameters,
            'duration_ms' => $durationMs,
        ]);
    }
}
