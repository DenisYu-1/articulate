<?php

namespace Articulate\Tests\Commands\InitCommand;

use Articulate\Commands\InitCommand;
use Articulate\Connection;
use Articulate\Tests\AbstractTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class InitialCreationTest extends AbstractTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        foreach ([
            $this->sqliteConnection,
            $this->mysqlConnection,
            $this->pgsqlConnection,
        ] as $connection) {
            $connection->executeQuery('DROP TABLE IF EXISTS migrations');
            $connection->beginTransaction();
        }
    }

    public function testExecuteWithSQLite(): void
    {
        $this->runTestWithConnection($this->sqliteConnection, "
            SELECT name 
            FROM sqlite_master 
            WHERE type='table' 
                AND name='migrations';
        ");
    }

    public function testExecuteWithMySQL(): void
    {
        $this->runTestWithConnection($this->mysqlConnection, "
            SHOW TABLES LIKE 'migrations'
        ");
    }

    public function testExecuteWithPostgreSQL(): void
    {
        $this->runTestWithConnection($this->pgsqlConnection, "
            SELECT table_name 
            FROM information_schema.tables 
            WHERE table_schema = 'public' 
                AND table_name = 'migrations'
        ");
    }

    private function runTestWithConnection(Connection $connection, string $resultQuery): void
    {
        $command = new InitCommand($connection);
        $commandTester = new CommandTester($command);

        $result = $connection->executeQuery($resultQuery);

        $tables = $result->fetchAll();
        $this->assertEmpty($tables, 'Migrations table already present before init.');

        $statusCode = $commandTester->execute([]);
        $this->assertSame(0, $statusCode);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Migrations table created successfully.', $output);

        $result = $connection->executeQuery($resultQuery);

        $tables = $result->fetchAll();
        $this->assertNotEmpty($tables, 'Migrations table not found in database.');
    }
}
