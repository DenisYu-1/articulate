<?php

namespace Articulate\QueryLogger;

final class FileQueryLogger implements QueryLoggerInterface {
    public function __construct(
        private readonly string $filePath,
    ) {
    }

    public function log(string $sql, array $parameters, float $durationMs): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $paramsJson = $parameters !== [] ? ' ' . json_encode($parameters) : '';
        $entry = sprintf("[%s] %.2f ms%s\n%s\n\n", $timestamp, $durationMs, $paramsJson, $sql);

        file_put_contents($this->filePath, $entry, FILE_APPEND | LOCK_EX);
    }
}
