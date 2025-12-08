<?php

namespace Articulate\Tests\Commands\InitCommand;

use Articulate\Commands\InitCommand;
use Articulate\Connection;
use Articulate\Tests\AbstractTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class InitialCreationTest extends AbstractTestCase
{
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

    public function testDoesNothingWhenTableExistsSQLite(): void
    {
        $this->runNoOpTest($this->sqliteConnection);
    }

    public function testDoesNothingWhenTableExistsMySQL(): void
    {
        $this->runNoOpTest($this->mysqlConnection);
    }

    public function testDoesNothingWhenTableExistsPostgreSQL(): void
    {
        $this->runNoOpTest($this->pgsqlConnection);
    }

    private function runTestWithConnection(Connection $connection, string $resultQuery): void
    {
        $connection->executeQuery('DROP TABLE IF EXISTS migrations');

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

        $this->assertMigrationSchema($connection);
    }

    private function runNoOpTest(Connection $connection): void
    {
        $connection->executeQuery('DROP TABLE IF EXISTS migrations');

        $command = new InitCommand($connection);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->insertSampleMigration($connection);
        $countBefore = $this->countMigrations($connection);

        $statusCode = $commandTester->execute([]);
        $this->assertSame(0, $statusCode);

        $this->assertMigrationSchema($connection);
        $this->assertSame($countBefore, $this->countMigrations($connection));
    }

    private function assertMigrationSchema(Connection $connection): void
    {
        $driver = $connection->getDriverName();

        if ($driver === Connection::SQLITE) {
            $result = $connection->executeQuery("PRAGMA table_info('migrations')");
            $columns = array_column($result->fetchAll(), 'name');
        } elseif ($driver === Connection::MYSQL) {
            $result = $connection->executeQuery('SHOW COLUMNS FROM migrations');
            $columns = array_column($result->fetchAll(), 'Field');
        } elseif ($driver === Connection::PGSQL) {
            $result = $connection->executeQuery("
                SELECT column_name 
                FROM information_schema.columns 
                WHERE table_schema = 'public' 
                  AND table_name = 'migrations'
                ORDER BY ordinal_position
            ");
            $columns = array_column($result->fetchAll(), 'column_name');
        } else {
            $columns = [];
        }

        $this->assertSame(['id', 'name', 'executed_at', 'running_time'], $columns);
    }

    private function insertSampleMigration(Connection $connection): void
    {
        $driver = $connection->getDriverName();

        $sql = match ($driver) {
            Connection::MYSQL,
            Connection::PGSQL=> "INSERT INTO migrations (name, executed_at, running_time) VALUES ('baseline', NOW(), 1)",
            Connection::SQLITE => "INSERT INTO migrations (name, executed_at, running_time) VALUES ('baseline', datetime('now'), 1)",
            default => null,
        };

        if ($sql !== null) {
            $connection->executeQuery($sql);
        }
    }

    private function countMigrations(Connection $connection): int
    {
        $result = $connection->executeQuery('SELECT COUNT(*) AS cnt FROM migrations');
        $rows = $result->fetchAll();

        return (int) $rows[0]['cnt'];
    }
}
