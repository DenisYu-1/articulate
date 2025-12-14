<?php

namespace Articulate\Tests\Commands\InitCommand;

use Articulate\Commands\InitCommand;
use Articulate\Connection;
use Articulate\Tests\DatabaseTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ExistingTablesTest extends DatabaseTestCase
{
    /**
     * Test init command execution with existing migrations table.
     *
     * @dataProvider databaseProvider
     * @group database
     */
    public function testExecuteWithExistingMigrationsTable(string $databaseName): void
    {
        $connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($connection, $databaseName);

        // Clean up any existing migrations table first
        $this->cleanUpTables(['migrations']);

        // Create migrations table manually first
        $createTableSql = match ($databaseName) {
            'mysql' => 'CREATE TABLE migrations (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB',
            'pgsql' => 'CREATE TABLE migrations (id SERIAL PRIMARY KEY)',
            'sqlite' => 'CREATE TABLE migrations (id INTEGER PRIMARY KEY AUTOINCREMENT)'
        };

        $connection->executeQuery($createTableSql);

        // Verify table exists query
        $existenceQuery = match ($databaseName) {
            'mysql' => "SHOW TABLES LIKE 'migrations'",
            'pgsql' => "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'migrations'",
            'sqlite' => "SELECT name FROM sqlite_master WHERE type='table' AND name='migrations'"
        };

        $this->runTestWithConnection($connection, $existenceQuery);
    }

    protected function tearDown(): void
    {
        if ($this->currentConnection && $this->currentDatabaseName) {
            $this->cleanUpTables(['migrations']);
        }

        parent::tearDown();
    }

    private function runTestWithConnection(Connection $connection, string $existenceQuery): void
    {
        $command = new InitCommand($connection);
        $commandTester = new CommandTester($command);

        // Execute the command
        $commandTester->execute([]);

        // Check the command output
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Migrations table created successfully.', $output);

        // Verify that the table was created
        $result = $connection->executeQuery($existenceQuery);

        $tables = $result->fetchAll();
        $this->assertNotEmpty($tables, 'Migrations table not found in database.');
    }
}
