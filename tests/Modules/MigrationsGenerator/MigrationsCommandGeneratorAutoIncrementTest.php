<?php

namespace Articulate\Tests\Modules\MigrationsGenerator;

use Articulate\Modules\Database\SchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\CompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\PropertiesData;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;
use Articulate\Tests\DatabaseTestCase;
use Articulate\Tests\MigrationsGeneratorTestHelper;

class MigrationsCommandGeneratorAutoIncrementTest extends DatabaseTestCase {
    /**
     * Test AutoIncrement attribute on primary key column.
     *
     * @dataProvider databaseProvider
     */
    public function testAutoIncrementPrimaryKey(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            'users',
            CompareResult::OPERATION_CREATE,
            [
                new ColumnCompareResult(
                    'id',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('int', false, null, null, null, null, true, true),
                    new PropertiesData()
                ),
                new ColumnCompareResult(
                    'name',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('string', false, null, 255),
                    new PropertiesData()
                ),
            ],
            [],
            [],
            ['id']
        );

        $generator = match ($databaseName) {
            'mysql' => MigrationsGeneratorTestHelper::forMySql(),
            'pgsql' => MigrationsGeneratorTestHelper::forPostgresql(),
        };

        $quote = match ($databaseName) {
            'mysql' => '`',
            'pgsql' => '"',
        };

        $result = $generator->generate($tableCompareResult);

        // MySQL uses AUTO_INCREMENT, PostgreSQL uses IDENTITY
        $autoIncrementSql = match ($databaseName) {
            'mysql' => 'AUTO_INCREMENT',
            'pgsql' => 'GENERATED ALWAYS AS IDENTITY',
        };

        $intType = match ($databaseName) {
            'mysql' => 'INT',
            'pgsql' => 'INTEGER',
        };

        $expected = "CREATE TABLE {$quote}users{$quote} (" .
            "{$quote}id{$quote} {$intType} {$autoIncrementSql} NOT NULL, " .
            "{$quote}name{$quote} VARCHAR(255) NOT NULL, " .
            "PRIMARY KEY ({$quote}id{$quote})" .
            ")";

        $this->assertEquals($expected, $result);
    }

    /**
     * Test AutoIncrement attribute on non-primary key column.
     *
     * @dataProvider databaseProvider
     */
    public function testAutoIncrementNonPrimaryKey(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            'documents',
            CompareResult::OPERATION_CREATE,
            [
                new ColumnCompareResult(
                    'id',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('uuid', false, null, null, 'uuid_v4', null, true),
                    new PropertiesData()
                ),
                new ColumnCompareResult(
                    'version',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('int', false, null, null, null, null, false, true),
                    new PropertiesData()
                ),
                new ColumnCompareResult(
                    'title',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('string', false, null, 255),
                    new PropertiesData()
                ),
            ],
            [],
            [],
            ['id']
        );

        $generator = match ($databaseName) {
            'mysql' => MigrationsGeneratorTestHelper::forMySql(),
            'pgsql' => MigrationsGeneratorTestHelper::forPostgresql(),
        };

        $quote = match ($databaseName) {
            'mysql' => '`',
            'pgsql' => '"',
        };

        $uuidType = match ($databaseName) {
            'mysql' => 'uuid',
            'pgsql' => 'UUID',
        };

        $intType = match ($databaseName) {
            'mysql' => 'INT',
            'pgsql' => 'INTEGER',
        };

        $result = $generator->generate($tableCompareResult);

        // AutoIncrement on non-primary key column
        $autoIncrementSql = match ($databaseName) {
            'mysql' => 'AUTO_INCREMENT',
            'pgsql' => 'GENERATED ALWAYS AS IDENTITY',
        };

        $expected = "CREATE TABLE {$quote}documents{$quote} (" .
            "{$quote}id{$quote} {$uuidType}  NOT NULL, " .
            "{$quote}version{$quote} {$intType} {$autoIncrementSql} NOT NULL, " .
            "{$quote}title{$quote} VARCHAR(255) NOT NULL, " .
            "PRIMARY KEY ({$quote}id{$quote})" .
            ")";

        $this->assertEquals($expected, $result);
    }

    /**
     * Test altering table to add AutoIncrement column.
     *
     * @dataProvider databaseProvider
     */
    public function testAddAutoIncrementColumn(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            'products',
            CompareResult::OPERATION_UPDATE,
            [
                new ColumnCompareResult(
                    'sort_order',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('int', false, null, null, null, null, false, true),
                    new PropertiesData()
                ),
            ],
            [],
            []
        );

        $generator = match ($databaseName) {
            'mysql' => MigrationsGeneratorTestHelper::forMySql(),
            'pgsql' => MigrationsGeneratorTestHelper::forPostgresql(),
        };

        $quote = match ($databaseName) {
            'mysql' => '`',
            'pgsql' => '"',
        };

        $intType = match ($databaseName) {
            'mysql' => 'INT',
            'pgsql' => 'INTEGER',
        };

        $updateSyntax = match ($databaseName) {
            'mysql' => 'AUTO_INCREMENT',
            'pgsql' => 'GENERATED ALWAYS AS IDENTITY',
        };

        $result = $generator->generate($tableCompareResult);

        $expected = "ALTER TABLE {$quote}products{$quote} ADD {$quote}sort_order{$quote} {$intType} {$updateSyntax} NOT NULL";

        $this->assertEquals($expected, $result);
    }

    /**
     * Test rollback of AutoIncrement column addition.
     *
     * @dataProvider databaseProvider
     */
    public function testRollbackAutoIncrementColumn(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            'products',
            CompareResult::OPERATION_UPDATE,
            [
                new ColumnCompareResult(
                    'sort_order',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('int', false, null, null, null, null, false, true),
                    new PropertiesData('int', false, null, null, null, null, false, true)
                ),
            ],
            [],
            []
        );

        $generator = match ($databaseName) {
            'mysql' => MigrationsGeneratorTestHelper::forMySql(),
            'pgsql' => MigrationsGeneratorTestHelper::forPostgresql(),
        };

        $quote = match ($databaseName) {
            'mysql' => '`',
            'pgsql' => '"',
        };

        $result = $generator->rollback($tableCompareResult);

        $expected = "ALTER TABLE {$quote}products{$quote} DROP {$quote}sort_order{$quote}";

        $this->assertEquals($expected, $result);
    }
}