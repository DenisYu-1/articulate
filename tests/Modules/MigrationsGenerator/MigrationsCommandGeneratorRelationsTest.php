<?php

namespace Articulate\Tests\Modules\MigrationsGenerator;

use Articulate\Modules\Database\SchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\ForeignKeyCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\PropertiesData;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;
use Articulate\Tests\DatabaseTestCase;
use Articulate\Tests\MigrationsGeneratorTestHelper;

class MigrationsCommandGeneratorRelationsTest extends DatabaseTestCase {
    /**
     * Test field migration operations for both databases.
     *
     * @dataProvider databaseProvider
     */
    public function testFieldMigration(string $databaseName): void
    {
        $cases = $this->getFieldMigrationCases($databaseName);

        foreach ($cases as $case) {
            $tableCompareResult = new TableCompareResult(
                'test_table',
                'update',
                [
                    new ColumnCompareResult(...$case['params']),
                ],
                [],
                [],
            );

            $generator = match ($databaseName) {
                'mysql' => MigrationsGeneratorTestHelper::forMySql(),
                'pgsql' => MigrationsGeneratorTestHelper::forPostgresql(),
            };

            $this->assertEquals(
                $case['query'],
                $generator->generate($tableCompareResult),
                "Failed for database: {$databaseName}"
            );
        }
    }

    /**
     * Get field migration test cases for the specified database.
     */
    private function getFieldMigrationCases(string $databaseName): array
    {
        $quote = match ($databaseName) {
            'mysql' => '`',
            'pgsql' => '"',
        };

        $updateSyntax = match ($databaseName) {
            'mysql' => 'MODIFY',
            'pgsql' => 'ALTER COLUMN',
        };

        return [
            [
                'query' => "ALTER TABLE {$quote}test_table{$quote} ADD {$quote}id{$quote} VARCHAR(255) NOT NULL",
                'params' => [
                    'id',
                    'create',
                    new PropertiesData('string', false),
                    new PropertiesData(),
                ],
            ], [
                'query' => "ALTER TABLE {$quote}test_table{$quote} DROP {$quote}id{$quote}",
                'params' => [
                    'id',
                    'delete',
                    new PropertiesData('string', false),
                    new PropertiesData(),
                ],
            ], [
                'query' => "ALTER TABLE {$quote}test_table{$quote} {$updateSyntax} {$quote}id{$quote} VARCHAR(255) NOT NULL",
                'params' => [
                    'id',
                    'update',
                    new PropertiesData('string', false),
                    new PropertiesData(),
                ],
            ], [
                'query' => "ALTER TABLE {$quote}test_table{$quote} {$updateSyntax} {$quote}id{$quote} VARCHAR(255) NOT NULL DEFAULT \"test\"",
                'params' => [
                    'id',
                    'update',
                    new PropertiesData('string', false, 'test'),
                    new PropertiesData(),
                ],
            ], [
                'query' => "ALTER TABLE {$quote}test_table{$quote} {$updateSyntax} {$quote}id{$quote} VARCHAR(254) NOT NULL DEFAULT \"test\"",
                'params' => [
                    'id',
                    'update',
                    new PropertiesData('string', false, 'test', 254),
                    new PropertiesData(),
                ],
            ],
        ];
    }

    /**
     * Test one-to-one foreign key creation for both databases.
     *
     * @dataProvider databaseProvider
     */
    public function testOneToOneForeignKeyCreation(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            'test_table',
            'update',
            [
                new ColumnCompareResult(
                    name: 'related_entity_id',
                    operation: 'create',
                    propertyData: new PropertiesData('int', false),
                    columnData: new PropertiesData(),
                ),
            ],
            [],
            [
                new ForeignKeyCompareResult(
                    name: 'fk_test_table_related_entity_related_entity_id',
                    operation: 'create',
                    column: 'related_entity_id',
                    referencedTable: 'related_entity',
                ),
            ],
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

        $expected = "ALTER TABLE {$quote}test_table{$quote} ADD {$quote}related_entity_id{$quote} {$intType} NOT NULL, ADD CONSTRAINT {$quote}fk_test_table_related_entity_related_entity_id{$quote} FOREIGN KEY ({$quote}related_entity_id{$quote}) REFERENCES {$quote}related_entity{$quote}({$quote}id{$quote})";

        $this->assertEquals(
            $expected,
            $generator->generate($tableCompareResult),
            "Failed for database: {$databaseName}"
        );
    }

    /**
     * Test one-to-one foreign key skipped for both databases.
     *
     * @dataProvider databaseProvider
     */
    public function testOneToOneForeignKeySkipped(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            'test_table',
            'update',
            [
                new ColumnCompareResult(
                    name: 'related_entity_id',
                    operation: 'create',
                    propertyData: new PropertiesData('int', false),
                    columnData: new PropertiesData(),
                ),
            ],
            [],
            [],
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

        $expected = "ALTER TABLE {$quote}test_table{$quote} ADD {$quote}related_entity_id{$quote} {$intType} NOT NULL";

        $this->assertEquals(
            $expected,
            $generator->generate($tableCompareResult),
            "Failed for database: {$databaseName}"
        );
    }

    /**
     * Test one-to-one foreign key drop for both databases.
     *
     * @dataProvider databaseProvider
     */
    public function testOneToOneForeignKeyDrop(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            'test_table',
            'update',
            [],
            [],
            [
                new ForeignKeyCompareResult(
                    name: 'fk_test_table_related_entity_related_entity_id',
                    operation: 'delete',
                    column: 'related_entity_id',
                    referencedTable: 'related_entity',
                ),
            ],
        );

        $generator = match ($databaseName) {
            'mysql' => MigrationsGeneratorTestHelper::forMySql(),
            'pgsql' => MigrationsGeneratorTestHelper::forPostgresql(),
        };

        $quote = match ($databaseName) {
            'mysql' => '`',
            'pgsql' => '"',
        };

        $fkKeyword = match ($databaseName) {
            'mysql' => 'FOREIGN KEY',
            'pgsql' => 'CONSTRAINT',
        };

        $expected = "ALTER TABLE {$quote}test_table{$quote} DROP {$fkKeyword} {$quote}fk_test_table_related_entity_related_entity_id{$quote}";

        $this->assertEquals(
            $expected,
            $generator->generate($tableCompareResult),
            "Failed for database: {$databaseName}"
        );
    }

    /**
     * Test foreign key and column drop combined for both databases.
     *
     * @dataProvider databaseProvider
     */
    public function testForeignKeyAndColumnDropCombined(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            'test_table',
            'update',
            [
                new ColumnCompareResult(
                    name: 'related_entity_id',
                    operation: 'delete',
                    propertyData: new PropertiesData(),
                    columnData: new PropertiesData('int', false),
                ),
            ],
            [],
            [
                new ForeignKeyCompareResult(
                    name: 'fk_test_table_related_entity_related_entity_id',
                    operation: 'delete',
                    column: 'related_entity_id',
                    referencedTable: 'related_entity',
                ),
            ],
        );

        $generator = match ($databaseName) {
            'mysql' => MigrationsGeneratorTestHelper::forMySql(),
            'pgsql' => MigrationsGeneratorTestHelper::forPostgresql(),
        };

        $quote = match ($databaseName) {
            'mysql' => '`',
            'pgsql' => '"',
        };

        $fkKeyword = match ($databaseName) {
            'mysql' => 'FOREIGN KEY',
            'pgsql' => 'CONSTRAINT',
        };

        $expected = "ALTER TABLE {$quote}test_table{$quote} DROP {$fkKeyword} {$quote}fk_test_table_related_entity_related_entity_id{$quote}, DROP {$quote}related_entity_id{$quote}";

        $this->assertEquals(
            $expected,
            $generator->generate($tableCompareResult),
            "Failed for database: {$databaseName}"
        );
    }

    /**
     * Test rollback recreates foreign key on table restore for both databases.
     *
     * @dataProvider databaseProvider
     */
    public function testRollbackRecreatesForeignKeyOnTableRestore(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            'test_table',
            'delete',
            [
                new ColumnCompareResult(
                    name: 'related_entity_id',
                    operation: 'delete',
                    propertyData: new PropertiesData(),
                    columnData: new PropertiesData('int', false),
                ),
            ],
            [],
            [
                new ForeignKeyCompareResult(
                    name: 'fk_test_table_related_entity_related_entity_id',
                    operation: 'delete',
                    column: 'related_entity_id',
                    referencedTable: 'related_entity',
                ),
            ],
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

        $expected = "CREATE TABLE {$quote}test_table{$quote} ({$quote}related_entity_id{$quote} {$intType} NOT NULL, CONSTRAINT {$quote}fk_test_table_related_entity_related_entity_id{$quote} FOREIGN KEY ({$quote}related_entity_id{$quote}) REFERENCES {$quote}related_entity{$quote}({$quote}id{$quote}))";

        $this->assertEquals(
            $expected,
            $generator->rollback($tableCompareResult),
            "Failed for database: {$databaseName}"
        );
    }
}
