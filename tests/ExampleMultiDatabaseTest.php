<?php

namespace Articulate\Tests;

use Articulate\Connection;

/**
 * Example test demonstrating how to use the multi-database testing setup.
 *
 * This test shows various patterns for testing across multiple databases:
 * 1. Testing all databases with databaseProvider()
 * 2. Testing specific databases with dedicated providers
 * 3. Testing single database scenarios
 */
class ExampleMultiDatabaseTest extends DatabaseTestCase
{
    /**
     * Test that runs against all available databases.
     *
     * This test will be executed once for each available database.
     * The test method receives the database name as parameter and gets the connection itself.
     *
     * @dataProvider databaseProvider
     * @group database
     */
    public function testBasicTableCreation(string $databaseName): void
    {
        try {
            $connection = $this->getConnection($databaseName);
        } catch (\Exception $e) {
            $this->markTestSkipped("{$databaseName} database not available: " . $e->getMessage());
        }

        // Set the current database for this test
        $this->setCurrentDatabase($connection, $databaseName);

        // Ensure clean state
        $tableName = $this->getTableName('test_basic', $databaseName);
        $dropSql = match ($databaseName) {
            'mysql' => "DROP TABLE IF EXISTS `{$tableName}`",
            'pgsql' => "DROP TABLE IF EXISTS \"{$tableName}\"",
            'sqlite' => "DROP TABLE IF EXISTS {$tableName}"
        };

        try {
            $connection->executeQuery($dropSql);
        } catch (\Exception $e) {
            // Ignore if table doesn't exist
        }

        // Create a simple test table

        $sql = match ($databaseName) {
            'mysql' => "CREATE TABLE `{$tableName}` (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255) NOT NULL)",
            'pgsql' => "CREATE TABLE \"{$tableName}\" (id SERIAL PRIMARY KEY, name VARCHAR(255) NOT NULL)",
            'sqlite' => "CREATE TABLE {$tableName} (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)"
        };

        $connection->executeQuery($sql);

        // Verify table was created
        $result = match ($databaseName) {
            'mysql' => $connection->executeQuery("SHOW TABLES LIKE '{$tableName}'"),
            'pgsql' => $connection->executeQuery("SELECT tablename FROM pg_tables WHERE tablename = '{$tableName}'"),
            'sqlite' => $connection->executeQuery("SELECT name FROM sqlite_master WHERE type='table' AND name='{$tableName}'")
        };

        $this->assertGreaterThan(0, count($result->fetchAll()), "Table {$tableName} should exist in {$databaseName}");
    }

    /**
     * Test that runs against all databases with data insertion and retrieval.
     *
     * @dataProvider databaseProvider
     * @group database
     */
    public function testDataInsertionAndRetrieval(string $databaseName): void
    {
        $connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($connection, $databaseName);

        // Clean up any existing table
        $this->cleanUpTables([$this->getTableName('test_data', $databaseName)]);

        $tableName = $this->getTableName('test_data', $databaseName);

        // Create table
        $createSql = match ($databaseName) {
            'mysql' => "CREATE TABLE `{$tableName}` (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255), value INT)",
            'pgsql' => "CREATE TABLE \"{$tableName}\" (id SERIAL PRIMARY KEY, name VARCHAR(255), value INT)",
            'sqlite' => "CREATE TABLE {$tableName} (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, value INTEGER)"
        };

        $connection->executeQuery($createSql);

        // Insert data
        $insertSql = match ($databaseName) {
            'mysql' => "INSERT INTO `{$tableName}` (name, value) VALUES (?, ?)",
            'pgsql' => "INSERT INTO \"{$tableName}\" (name, value) VALUES (?, ?)",
            'sqlite' => "INSERT INTO {$tableName} (name, value) VALUES (?, ?)"
        };

        $connection->executeQuery($insertSql, ['test_name', 42]);

        // Retrieve data
        $selectSql = match ($databaseName) {
            'mysql' => "SELECT * FROM `{$tableName}` WHERE id = 1",
            'pgsql' => "SELECT * FROM \"{$tableName}\" WHERE id = 1",
            'sqlite' => "SELECT * FROM {$tableName} WHERE id = 1"
        };

        $result = $connection->executeQuery($selectSql)->fetch();

        $this->assertEquals('test_name', $result['name']);
        $this->assertEquals(42, (int) $result['value']);
    }

    /**
     * Test that runs only on MySQL.
     *
     * @dataProvider mysqlProvider
     * @group mysql
     */
    public function testMySqlSpecificFeatures(string $databaseName): void
    {
        $connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($connection, $databaseName);

        // Clean up any existing table
        $this->cleanUpTables([$this->getTableName('test_mysql', $databaseName)]);

        // Test MySQL-specific features like ENGINE specification
        $tableName = $this->getTableName('test_mysql', $databaseName);
        $sql = "CREATE TABLE `{$tableName}` (id INT PRIMARY KEY AUTO_INCREMENT, data TEXT) ENGINE=InnoDB";

        $connection->executeQuery($sql);

        // Verify table exists
        $result = $connection->executeQuery("SHOW TABLES LIKE '{$tableName}'");
        $this->assertGreaterThan(0, count($result->fetchAll()));
    }

    /**
     * Test that runs only on PostgreSQL.
     *
     * @dataProvider pgsqlProvider
     * @group pgsql
     */
    public function testPostgresqlSpecificFeatures(string $databaseName): void
    {
        $connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($connection, $databaseName);

        // Clean up any existing table
        $this->cleanUpTables([$this->getTableName('test_pgsql', $databaseName)]);

        // Test PostgreSQL-specific features
        $tableName = $this->getTableName('test_pgsql', $databaseName);
        $sql = "CREATE TABLE \"{$tableName}\" (id SERIAL PRIMARY KEY, data JSONB)";

        $connection->executeQuery($sql);

        // Insert JSON data
        $connection->executeQuery("INSERT INTO \"{$tableName}\" (data) VALUES (?)", ['{"key": "value"}']);

        // Query JSON data
        $result = $connection->executeQuery("SELECT data->>'key' as key_value FROM \"{$tableName}\" WHERE id = 1")->fetch();
        $this->assertEquals('value', $result['key_value']);
    }

    /**
     * Test that runs only on SQLite.
     *
     * @dataProvider sqliteProvider
     * @group sqlite
     */
    public function testSqliteSpecificFeatures(string $databaseName): void
    {
        $connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($connection, $databaseName);

        // Test SQLite-specific features
        $tableName = $this->getTableName('test_sqlite', $databaseName);
        $sql = "CREATE TABLE {$tableName} (id INTEGER PRIMARY KEY AUTOINCREMENT, data TEXT)";

        $connection->executeQuery($sql);

        // Test SQLite's AUTOINCREMENT behavior
        $connection->executeQuery("INSERT INTO {$tableName} (data) VALUES (?)", ['test']);
        $result = $connection->executeQuery('SELECT last_insert_rowid() as id')->fetch();

        $this->assertGreaterThan(0, (int) $result['id']);
    }

    /**
     * Example of a test that can be run on a single database for debugging.
     */
    public function testSingleDatabaseExample(): void
    {
        // For debugging, you can test against a specific database
        $databaseName = 'sqlite'; // Change this to 'mysql' or 'pgsql' as needed
        $this->skipIfDatabaseNotAvailable($databaseName);

        $connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($connection, $databaseName);

        // Your test logic here
        $tableName = $this->getTableName('test_single', $databaseName);
        $sql = "CREATE TABLE {$tableName} (id INTEGER PRIMARY KEY, name TEXT)";

        $connection->executeQuery($sql);

        $result = $connection->executeQuery("SELECT name FROM sqlite_master WHERE type='table' AND name='{$tableName}'");
        $this->assertGreaterThan(0, count($result->fetchAll()));
    }

    /**
     * Test foreign key constraints across databases.
     *
     * @dataProvider databaseProvider
     * @group database
     */
    public function testForeignKeyConstraints(string $databaseName): void
    {
        $connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($connection, $databaseName);

        // Clean up any existing tables
        $this->cleanUpTables([
            $this->getTableName('parent_table', $databaseName),
            $this->getTableName('child_table', $databaseName),
        ]);

        $parentTable = $this->getTableName('parent_table', $databaseName);
        $childTable = $this->getTableName('child_table', $databaseName);

        // Create parent table
        $parentSql = match ($databaseName) {
            'mysql' => "CREATE TABLE `{$parentTable}` (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255))",
            'pgsql' => "CREATE TABLE \"{$parentTable}\" (id SERIAL PRIMARY KEY, name VARCHAR(255))",
            'sqlite' => "CREATE TABLE {$parentTable} (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)"
        };

        $connection->executeQuery($parentSql);

        // Create child table with foreign key
        $childSql = match ($databaseName) {
            'mysql' => "CREATE TABLE `{$childTable}` (id INT PRIMARY KEY AUTO_INCREMENT, parent_id INT, FOREIGN KEY (parent_id) REFERENCES `{$parentTable}`(id))",
            'pgsql' => "CREATE TABLE \"{$childTable}\" (id SERIAL PRIMARY KEY, parent_id INT REFERENCES \"{$parentTable}\"(id))",
            'sqlite' => "CREATE TABLE {$childTable} (id INTEGER PRIMARY KEY AUTOINCREMENT, parent_id INTEGER REFERENCES {$parentTable}(id))"
        };

        $connection->executeQuery($childSql);

        // Insert parent record
        $insertParentSql = match ($databaseName) {
            'mysql' => "INSERT INTO `{$parentTable}` (name) VALUES (?)",
            'pgsql' => "INSERT INTO \"{$parentTable}\" (name) VALUES (?)",
            'sqlite' => "INSERT INTO {$parentTable} (name) VALUES (?)"
        };

        $connection->executeQuery($insertParentSql, ['Parent Record']);

        // Get parent ID
        $parentIdResult = match ($databaseName) {
            'mysql' => $connection->executeQuery('SELECT LAST_INSERT_ID() as id'),
            'pgsql' => $connection->executeQuery("SELECT currval(pg_get_serial_sequence('\"{$parentTable}\"', 'id')) as id"),
            'sqlite' => $connection->executeQuery('SELECT last_insert_rowid() as id')
        };

        $parentId = $parentIdResult->fetch()['id'];

        // Insert child record
        $insertChildSql = match ($databaseName) {
            'mysql' => "INSERT INTO `{$childTable}` (parent_id) VALUES (?)",
            'pgsql' => "INSERT INTO \"{$childTable}\" (parent_id) VALUES (?)",
            'sqlite' => "INSERT INTO {$childTable} (parent_id) VALUES (?)"
        };

        $connection->executeQuery($insertChildSql, [$parentId]);

        // Verify child record exists
        $selectSql = match ($databaseName) {
            'mysql' => "SELECT COUNT(*) as count FROM `{$childTable}`",
            'pgsql' => "SELECT COUNT(*) as count FROM \"{$childTable}\"",
            'sqlite' => "SELECT COUNT(*) as count FROM {$childTable}"
        };

        $result = $connection->executeQuery($selectSql)->fetch();
        $this->assertEquals(1, (int) $result['count']);
    }
}
