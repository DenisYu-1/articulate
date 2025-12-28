<?php

namespace Articulate\Tests;

use Articulate\Connection;
use Exception;

/**
 * Trait for running tests across multiple database systems.
 *
 * This trait provides data providers and utilities to automatically run
 * the same test method against MySQL, PostgreSQL, and SQLite databases.
 */
trait DatabaseTestTrait {
    /**
     * Data provider that returns all database types.
     *
     * @return array<array{string}> Array of [database_name] tuples
     */
    public static function databaseProvider(): array
    {
        return [
            ['sqlite'], // SQLite is always available
            ['mysql'],  // MySQL may be available
            ['pgsql'],  // PostgreSQL may be available
        ];
    }

    /**
     * Data provider for MySQL only.
     *
     * @return array<array{string}>
     */
    public static function mysqlProvider(): array
    {
        return [['mysql']];
    }

    /**
     * Data provider for PostgreSQL only.
     *
     * @return array<array{string}>
     */
    public static function pgsqlProvider(): array
    {
        return [['pgsql']];
    }

    /**
     * Data provider for SQLite only.
     *
     * @return array<array{string}>
     */
    public static function sqliteProvider(): array
    {
        return [['sqlite']];
    }

    /**
     * Get the current database connection based on the database name.
     */
    protected function getConnection(string $databaseName): Connection
    {
        return match ($databaseName) {
            'mysql' => $this->mysqlConnection,
            'pgsql' => $this->pgsqlConnection,
            'sqlite' => $this->sqliteConnection,
            default => throw new \InvalidArgumentException("Unknown database: {$databaseName}")
        };
    }

    /**
     * Skip test if specific database is not available.
     */
    protected function skipIfDatabaseNotAvailable(string $databaseName): void
    {
        $connection = match ($databaseName) {
            'mysql' => $this->mysqlConnection ?? null,
            'pgsql' => $this->pgsqlConnection ?? null,
            'sqlite' => $this->sqliteConnection ?? null,
            default => null
        };

        if (!$connection) {
            $this->markTestSkipped("{$databaseName} database is not available");
        }
    }

    /**
     * Get database-specific table name to avoid conflicts.
     */
    protected function getTableName(string $baseName, string $databaseName): string
    {
        return $baseName . '_' . $databaseName;
    }

    /**
     * Clean up database state between tests.
     */
    protected function cleanDatabase(Connection $connection, string $databaseName): void
    {
        // For SQLite, we rely on transactions being rolled back
        if ($databaseName === 'sqlite') {
            return;
        }

        try {
            // Get all tables and drop them
            $tables = match ($databaseName) {
                'mysql' => $connection->executeQuery('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN),
                'pgsql' => $connection->executeQuery("SELECT tablename FROM pg_tables WHERE schemaname = 'public'")->fetchAll(\PDO::FETCH_COLUMN),
                default => []
            };

            foreach ($tables as $table) {
                $connection->executeQuery("DROP TABLE IF EXISTS `{$table}`");
            }
        } catch (Exception $e) {
            // Ignore cleanup errors
        }
    }
}
