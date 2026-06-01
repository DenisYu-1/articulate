<?php

namespace Articulate\Tests;

use Articulate\Connection;

/**
 * Base test case for tests that need to run against multiple databases.
 *
 * This class combines AbstractTestCase (which provides database connections)
 * with DatabaseTestTrait (which provides multi-database testing utilities).
 *
 * Usage:
 * - Extend this class instead of AbstractTestCase for database tests
 * - Use data providers like databaseProvider(), mysqlProvider(), etc.
 * - Test methods will receive Connection and database name parameters
 */
abstract class DatabaseTestCase extends AbstractTestCase {
    use DatabaseTestTrait;

    /**
     * Current database connection (set during data provider tests).
     */
    protected ?Connection $currentConnection = null;

    /**
     * Current database name (set during data provider tests).
     */
    protected ?string $currentDatabaseName = null;

    /**
     * Helper method to set the current database for a test.
     */
    protected function setCurrentDatabase(Connection $connection, string $databaseName): void
    {
        $this->currentConnection = $connection;
        $this->currentDatabaseName = $databaseName;
    }

    /**
     * Get the current database connection for the test.
     */
    protected function getCurrentConnection(): Connection
    {
        if ($this->currentConnection === null) {
            throw new \RuntimeException('Current connection not set. Make sure to use setCurrentDatabase() in your test.');
        }

        return $this->currentConnection;
    }

    /**
     * Get the current database name for the test.
     */
    protected function getCurrentDatabaseName(): string
    {
        if ($this->currentDatabaseName === null) {
            throw new \RuntimeException('Current database name not set. Make sure to use setCurrentDatabase() in your test.');
        }

        return $this->currentDatabaseName;
    }

    /**
     * Clean up tables created during tests.
     */
    protected function cleanUpTables(array $tableNames): void
    {
        if (!$this->currentConnection || !$this->currentDatabaseName) {
            return;
        }

        if ($this->currentDatabaseName === 'mysql') {
            $this->currentConnection->executeQuery('SET FOREIGN_KEY_CHECKS = 0');

            foreach ($tableNames as $tableName) {
                try {
                    $this->currentConnection->executeQuery("DROP TABLE IF EXISTS `{$tableName}`");
                } catch (\Exception $e) {
                    error_log("Failed to drop table {$tableName}: " . $e->getMessage());
                }
            }

            $this->currentConnection->executeQuery('SET FOREIGN_KEY_CHECKS = 1');
        } else {
            foreach ($tableNames as $tableName) {
                try {
                    $this->currentConnection->executeQuery("DROP TABLE IF EXISTS \"{$tableName}\" CASCADE");
                } catch (\Exception $e) {
                    error_log("Failed to drop table {$tableName}: " . $e->getMessage());
                }
            }
        }
    }

    protected function tearDown(): void
    {
        $this->currentConnection = null;
        $this->currentDatabaseName = null;

        parent::tearDown();
    }
}
