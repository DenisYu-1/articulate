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
     * Set up method called before each test.
     *
     * This method is called by PHPUnit before each test method.
     * For data provider tests, it will be called with the database connection
     * and name as parameters.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure clean state at the start of each test
        $this->ensureCleanDatabaseState();

        // If this is a data provider test, the parameters will be set
        // by the data provider. Otherwise, we'll use the default behavior.
    }

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

        // Now drop tables in reverse order (child tables first)
        $tableNamesReversed = array_reverse($tableNames);

        foreach ($tableNamesReversed as $tableName) {
            // Try multiple variations of DROP TABLE to ensure it works
            $dropAttempts = [];

            if ($this->currentDatabaseName === 'mysql') {
                $dropAttempts = [
                    "DROP TABLE IF EXISTS `{$tableName}`",
                    "SET FOREIGN_KEY_CHECKS = 0; DROP TABLE IF EXISTS `{$tableName}`; SET FOREIGN_KEY_CHECKS = 1;",
                ];
            } elseif ($this->currentDatabaseName === 'pgsql') {
                // Use CASCADE for PostgreSQL to handle dependencies
                $dropAttempts = [
                    "DROP TABLE IF EXISTS \"{$tableName}\" CASCADE",
                    "DROP TABLE IF EXISTS {$tableName} CASCADE",
                ];
            } else {
                $dropAttempts = ["DROP TABLE IF EXISTS `{$tableName}`"];
            }

            foreach ($dropAttempts as $dropSql) {
                try {
                    if (str_contains($dropSql, ';')) {
                        // Multiple statements for MySQL
                        foreach (array_map('trim', explode(';', $dropSql)) as $stmt) {
                            if (!empty($stmt)) {
                                $this->currentConnection->executeQuery($stmt);
                            }
                        }
                    } else {
                        $this->currentConnection->executeQuery($dropSql);
                    }

                    break; // If successful, don't try other variations
                } catch (\Exception $e) {
                    // Continue to next attempt
                    error_log("Failed to drop table {$tableName} with SQL: {$dropSql} - " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Tear down method to clean up after each test.
     */
    protected function tearDown(): void
    {
        // Clean up common test tables that might be left behind
        if ($this->currentConnection && $this->currentDatabaseName) {
            try {
                // Try to rollback any failed transaction first
                try {
                    $this->currentConnection->rollbackTransaction();
                } catch (\Exception $e) {
                    // Transaction might not be active, that's fine
                }

                // Start a fresh transaction for cleanup
                $this->currentConnection->beginTransaction();

                try {
                    $this->cleanUpTables(['migrations', 'test_basic', 'test_table', 'users', 'products', 'test_entity', 'test', 'test_users']);
                    $this->currentConnection->commit();
                } catch (\Exception $e) {
                    // If cleanup fails, rollback
                    try {
                        $this->currentConnection->rollbackTransaction();
                    } catch (\Exception $rollbackException) {
                        // Ignore rollback errors
                    }
                }
            } catch (\Exception $e) {
                // Ignore cleanup errors in tearDown
                error_log('Cleanup failed in tearDown: ' . $e->getMessage());
            }
        }

        parent::tearDown();
    }

    /**
     * Ensure database is in a clean state by attempting to drop common test tables.
     */
    private function ensureCleanDatabaseState(): void
    {
        // Try to clean up tables for both databases if they're available
        $databases = ['mysql', 'pgsql'];

        foreach ($databases as $dbName) {
            try {
                $connection = $this->getConnection($dbName);
                $this->setCurrentDatabase($connection, $dbName);

                // Try to rollback any failed transaction first
                try {
                    $connection->rollbackTransaction();
                } catch (\Exception $e) {
                    // Transaction might not be active, that's fine
                }

                // DDL operations (like DROP TABLE) cannot be executed within transactions
                // in MySQL/PostgreSQL - they cause implicit commits. So we execute
                // cleanup outside of any transaction.
                $this->cleanUpTables(['migrations', 'test_basic', 'test_table', 'users', 'products', 'test_entity', 'test', 'test_users']);

                // Reset current database state
                $this->currentConnection = null;
                $this->currentDatabaseName = null;
            } catch (\Exception $e) {
                // Database not available or connection failed, skip cleanup
                continue;
            }
        }
    }
}
