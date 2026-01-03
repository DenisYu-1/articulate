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

        // First, drop foreign key constraints
        if ($this->currentDatabaseName === 'mysql') {
            foreach ($tableNames as $tableName) {
                try {
                    // Get foreign key constraint names for this table
                    $fkResult = $this->currentConnection->executeQuery(
                        "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
                         WHERE CONSTRAINT_TYPE = 'FOREIGN KEY'
                         AND TABLE_NAME = '{$tableName}'
                         AND TABLE_SCHEMA = DATABASE()"
                    );

                    $constraints = $fkResult->fetchAll();
                    foreach ($constraints as $constraint) {
                        $constraintName = $constraint['CONSTRAINT_NAME'];

                        try {
                            $this->currentConnection->executeQuery("ALTER TABLE `{$tableName}` DROP FOREIGN KEY `{$constraintName}`");
                        } catch (\Exception $e) {
                            error_log("Failed to drop FK {$constraintName} from {$tableName}: " . $e->getMessage());
                        }
                    }
                } catch (\Exception $e) {
                    // Ignore errors when checking constraints
                }
            }
        } elseif ($this->currentDatabaseName === 'pgsql') {
            foreach ($tableNames as $tableName) {
                try {
                    // Get foreign key constraint names for this table in PostgreSQL
                    $fkResult = $this->currentConnection->executeQuery(
                        "SELECT conname FROM pg_constraint
                         INNER JOIN pg_class ON conrelid = pg_class.oid
                         WHERE contype = 'f'
                         AND pg_class.relname = '{$tableName}'"
                    );

                    $constraints = $fkResult->fetchAll();
                    foreach ($constraints as $constraint) {
                        $constraintName = $constraint['conname'];

                        try {
                            $this->currentConnection->executeQuery("ALTER TABLE \"{$tableName}\" DROP CONSTRAINT \"{$constraintName}\"");
                        } catch (\Exception $e) {
                            error_log("Failed to drop FK {$constraintName} from {$tableName}: " . $e->getMessage());
                        }
                    }
                } catch (\Exception $e) {
                    // Ignore errors when checking constraints
                }
            }
        }

        // Now drop tables in reverse order (child tables first)
        $tableNamesReversed = array_reverse($tableNames);

        foreach ($tableNamesReversed as $tableName) {
            // Try multiple variations of DROP TABLE to ensure it works
            $dropAttempts = [];

            if ($this->currentDatabaseName === 'mysql') {
                $dropAttempts = [
                    "DROP TABLE IF EXISTS `{$tableName}`",
                    "DROP TABLE IF EXISTS {$tableName}",
                ];
            } elseif ($this->currentDatabaseName === 'pgsql') {
                // Use CASCADE for PostgreSQL to handle dependencies
                $dropAttempts = [
                    "DROP TABLE IF EXISTS \"{$tableName}\" CASCADE",
                    "DROP TABLE IF EXISTS {$tableName} CASCADE",
                ];
            } else { // sqlite
                $dropAttempts = ["DROP TABLE IF EXISTS {$tableName}"];
            }

            foreach ($dropAttempts as $dropSql) {
                try {
                    $this->currentConnection->executeQuery($dropSql);

                    break; // If successful, don't try other variations
                } catch (\Exception $e) {
                    // Continue to next attempt
                    error_log("Failed to drop table {$tableName} with SQL: {$dropSql} - " . $e->getMessage());
                }
            }
        }
    }
}
