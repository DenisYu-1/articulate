<?php

namespace Articulate\Tests;

use Dotenv\Dotenv;
use Articulate\Connection;
use PHPUnit\Framework\TestCase;

abstract class AbstractTestCase extends TestCase
{
    protected Connection $mysqlConnection;
    protected Connection $pgsqlConnection;
    protected Connection $sqliteConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->load();

        $databaseName = $this->getDatabaseName();

        $this->sqliteConnection = new Connection('sqlite::memory:', '1', '2');
        $this->mysqlConnection = new Connection('mysql:host=' . $_ENV['DATABASE_HOST'] . ';dbname=' . $databaseName . ';charset=utf8mb4', $_ENV['DATABASE_USER'], $_ENV['DATABASE_PASSWORD']);
        $this->pgsqlConnection = new Connection('pgsql:host=pgsql;port=5432;dbname=' . $databaseName, $_ENV['DATABASE_USER'], $_ENV['DATABASE_PASSWORD']);

        $this->sqliteConnection->beginTransaction();
        $this->mysqlConnection->beginTransaction();
        $this->pgsqlConnection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->pgsqlConnection->rollbackTransaction();
        $this->mysqlConnection->rollbackTransaction();
        $this->sqliteConnection->rollbackTransaction();
        unset($this->sqliteConnection, $this->mysqlConnection, $this->pgsqlConnection);
        parent::tearDown();
    }

    protected function getDatabaseName(): string
    {
        return $_ENV['DATABASE_NAME'];
    }
}
