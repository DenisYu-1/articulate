<?php

namespace Articulate\Tests\Commands\InitCommand;

use Articulate\Commands\InitCommand;
use Articulate\Connection;
use Articulate\Tests\AbstractTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ExistingTablesTest extends AbstractTestCase
{
    public function testExecuteWithSQLite(): void
    {
        $this->sqliteConnection->executeQuery('CREATE TABLE migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT
        )');
        $this->runTestWithConnection($this->sqliteConnection, "
            SELECT name 
            FROM sqlite_master 
            WHERE type='table' 
                AND name='migrations';
        ");
    }

    public function testExecuteWithMySQL(): void
    {
        $this->mysqlConnection->executeQuery('
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY
            ) ENGINE=InnoDB
        ');
        $this->runTestWithConnection($this->mysqlConnection, "
            SHOW TABLES LIKE 'migrations'
        ");
    }

    public function testExecuteWithPostgreSQL(): void
    {
        $this->pgsqlConnection->executeQuery('
            CREATE TABLE IF NOT EXISTS migrations (
                id SERIAL PRIMARY KEY
            )
        ');
        $this->runTestWithConnection($this->pgsqlConnection, "
            SELECT table_name 
            FROM information_schema.tables 
            WHERE table_schema = 'public' 
                AND table_name = 'migrations'
        ");
    }

    private function runTestWithConnection(Connection $connection, $resultQuery): void
    {
        $command = new InitCommand($connection);
        $commandTester = new CommandTester($command);

        // Execute the command
        $commandTester->execute([]);

        // Check the command output
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Migrations table created successfully.', $output);

        // Verify that the table was created
        $result = $connection->executeQuery($resultQuery);

        $tables = $result->fetchAll();
        $this->assertNotEmpty($tables, "Migrations table not found in database.");
    }
}
