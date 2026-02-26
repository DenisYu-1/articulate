<?php

namespace Articulate;

use Articulate\QueryLogger\QueryLoggerInterface;
use PDO;
use PDOStatement;

class Connection {
    public const MYSQL = 'mysql';

    public const PGSQL = 'pgsql';

    private readonly PDO $pdo;

    public function __construct(
        private readonly string $dsn,
        private readonly string $user,
        private readonly string $password,
        private readonly ?QueryLoggerInterface $queryLogger = null,
        bool $autocommit = false,
    ) {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_AUTOCOMMIT => $autocommit,
        ];

        $this->pdo = new PDO($this->dsn, $this->user, $this->password, $options);
    }

    public function executeQuery(string $sql, array $parameters = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        if ($parameters !== []) {
            $parameters = $this->normalizeParameters($parameters);
        }

        $start = hrtime(true);
        $statement->execute($parameters);
        $durationMs = (hrtime(true) - $start) / 1e6;

        $this->queryLogger?->log($sql, $parameters, $durationMs);

        return $statement;
    }

    private function normalizeParameters(array $parameters): array
    {
        foreach ($parameters as $key => $value) {
            if (is_bool($value)) {
                $parameters[$key] = $value ? 1 : 0;
            }
        }

        return $parameters;
    }

    public function getDriverName(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public function beginTransaction(): void
    {
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollbackTransaction(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return $this->pdo->lastInsertId($name);
    }
}
