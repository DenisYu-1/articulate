<?php

namespace Articulate;

use PDO;
use PDOStatement;

class Connection {
    public const MYSQL = 'mysql';

    public const PGSQL = 'pgsql';

    public const SQLITE = 'sqlite';

    private readonly PDO $pdo;

    public function __construct(
        private readonly string $dsn,
        private readonly string $user,
        private readonly string $password,
    ) {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_AUTOCOMMIT => false,
        ];

        $this->pdo = new PDO($this->dsn, $this->user, $this->password, $options);
    }

    public function executeQuery(string $sql, array $parameters = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return $statement;
    }

    public function getDriverName(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public function beginTransaction()
    {
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }
    }

    public function commit()
    {
        $this->pdo->commit();
    }

    public function rollbackTransaction()
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function rollback()
    {
        $this->rollbackTransaction();
    }
}
