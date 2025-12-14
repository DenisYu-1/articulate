<?php

namespace Articulate\Tests\Commands\InitCommand;

use Articulate\Commands\InitCommand;
use Articulate\Connection;
use Articulate\Tests\DatabaseTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class InitialCreationTest extends DatabaseTestCase
{
    /**
     * Test that init command creates migrations table
     *
     * @dataProvider databaseProvider
     * @group database
     */
    public function testCreatesMigrationsTable(string $databaseName): void
    {
        $connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($connection, $databaseName);

        $connection->executeQuery('DROP TABLE IF EXISTS migrations');

        $command = new InitCommand($connection);
        $commandTester = new CommandTester($command);

        $existenceQuery = $this->getExistenceQuery($databaseName);

        $result = $connection->executeQuery($existenceQuery);
        $tables = $result->fetchAll();
        $this->assertEmpty($tables, 'Migrations table already present before init.');

        $statusCode = $commandTester->execute([]);
        $this->assertSame(0, $statusCode);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Migrations table created successfully.', $output);

        $result = $connection->executeQuery($existenceQuery);
        $tables = $result->fetchAll();
        $this->assertNotEmpty($tables, 'Migrations table not found in database.');

        $this->assertMigrationSchema($connection, $databaseName);
    }

    /**
     * Test that init command does nothing when migrations table already exists
     *
     * @dataProvider databaseProvider
     * @group database
     */
    public function testDoesNothingWhenTableExists(string $databaseName): void
    {
        $connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($connection, $databaseName);

        $connection->executeQuery('DROP TABLE IF EXISTS migrations');

        $command = new InitCommand($connection);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $existenceQuery = $this->getExistenceQuery($databaseName);
        $this->assertNotEmpty($connection->executeQuery($existenceQuery)->fetchAll());

        $this->insertSampleMigration($connection, $databaseName);
        $countBefore = $this->countMigrations($connection);

        $statusCode = $commandTester->execute([]);
        $this->assertSame(0, $statusCode);

        $this->assertMigrationSchema($connection, $databaseName);
        $this->assertSame($countBefore, $this->countMigrations($connection));
    }

    private function getExistenceQuery(string $databaseName): string
    {
        return match ($databaseName) {
            'mysql' => "SHOW TABLES LIKE 'migrations'",
            'pgsql' => "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'migrations'",
            'sqlite' => "SELECT name FROM sqlite_master WHERE type='table' AND name='migrations'"
        };
    }

    private function assertMigrationSchema(Connection $connection, string $databaseName): void
    {
        $columnQuery = match ($databaseName) {
            'mysql' => 'SHOW COLUMNS FROM migrations',
            'pgsql' => "SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'migrations' ORDER BY ordinal_position",
            'sqlite' => "PRAGMA table_info('migrations')"
        };

        $result = $connection->executeQuery($columnQuery);
        $columns = array_column($result->fetchAll(), match ($databaseName) {
            'mysql' => 'Field',
            'pgsql' => 'column_name',
            'sqlite' => 'name'
        });

        $this->assertSame(['id', 'name', 'executed_at', 'running_time'], $columns);
    }

    private function insertSampleMigration(Connection $connection, string $databaseName): void
    {
        $sql = match ($databaseName) {
            'mysql', 'pgsql' => "INSERT INTO migrations (name, executed_at, running_time) VALUES ('baseline', NOW(), 1)",
            'sqlite' => "INSERT INTO migrations (name, executed_at, running_time) VALUES ('baseline', datetime('now'), 1)"
        };

        $connection->executeQuery($sql);
    }

    private function countMigrations(Connection $connection): int
    {
        $result = $connection->executeQuery('SELECT COUNT(*) AS cnt FROM migrations');
        $rows = $result->fetchAll();

        return (int) $rows[0]['cnt'];
    }
}
