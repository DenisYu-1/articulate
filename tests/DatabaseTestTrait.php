<?php

namespace Articulate\Tests;

use Articulate\Connection;
use Exception;

/**
 * Trait for running tests across multiple database systems.
 *
 * This trait provides data providers and utilities to automatically run
 * the same test method against MySQL and PostgreSQL databases.
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
     * Get the current database connection based on the database name.
     * @throws \RuntimeException if database connection is not available
     */
    protected function getConnection(string $databaseName): Connection
    {
        $connection = match ($databaseName) {
            'mysql' => $this->mysqlConnection ?? null,
            'pgsql' => $this->pgsqlConnection ?? null,
            default => throw new \InvalidArgumentException("Unknown database: {$databaseName}")
        };

        if ($connection === null) {
            throw new \RuntimeException("Database '{$databaseName}' is not available. Make sure the database is running and properly configured.");
        }

        return $connection;
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
