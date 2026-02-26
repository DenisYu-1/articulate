<?php

namespace Articulate\Tests\Modules\MigrationsGenerator;

use Articulate\Modules\Database\SchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\CompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\IndexCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\PropertiesData;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;
use Articulate\Tests\DatabaseTestCase;
use Articulate\Tests\MigrationsGeneratorTestHelper;
use PHPUnit\Framework\Attributes\DataProvider;

class MigrationsCommandGeneratorPolymorphicTest extends DatabaseTestCase {
    /**
     * Test that polymorphic relation creates correct migration for both databases.
     */
    #[DataProvider('databaseProvider')]
    public function testPolymorphicRelationCreatesCorrectMigration(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            name: 'poll',
            operation: CompareResult::OPERATION_CREATE,
            columns: [
                new ColumnCompareResult(
                    'id',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('int', false, null, null, null, null, null, false, null),
                    new PropertiesData()
                ),
                new ColumnCompareResult(
                    'question',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('string', false, null, 255, null, null, null, false, null),
                    new PropertiesData()
                ),
                new ColumnCompareResult(
                    'pollable_type',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('string', false, null, 255, null, null, null, false, null),
                    new PropertiesData()
                ),
                new ColumnCompareResult(
                    'pollable_id',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('int', false, null, null, null, null, null, false, null),
                    new PropertiesData()
                ),
            ],
            indexes: [
                new IndexCompareResult(
                    'pollable_morph_index',
                    CompareResult::OPERATION_CREATE,
                    ['pollable_type', 'pollable_id'],
                    false
                ),
            ],
            foreignKeys: [],
            primaryColumns: ['id']
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

        $result = $generator->generate($tableCompareResult);

        $expected = match ($databaseName) {
            'mysql' => "CREATE TABLE {$quote}poll{$quote} (" .
                "{$quote}id{$quote} {$intType} NOT NULL, " .
                "{$quote}question{$quote} VARCHAR(255) NOT NULL, " .
                "{$quote}pollable_type{$quote} VARCHAR(255) NOT NULL, " .
                "{$quote}pollable_id{$quote} {$intType} NOT NULL, " .
                "PRIMARY KEY ({$quote}id{$quote}), " .
                "INDEX {$quote}pollable_morph_index{$quote} ({$quote}pollable_type{$quote}, {$quote}pollable_id{$quote})" .
                ')',
            'pgsql' => "CREATE TABLE {$quote}poll{$quote} (" .
                "{$quote}id{$quote} {$intType} NOT NULL, " .
                "{$quote}question{$quote} VARCHAR(255) NOT NULL, " .
                "{$quote}pollable_type{$quote} VARCHAR(255) NOT NULL, " .
                "{$quote}pollable_id{$quote} {$intType} NOT NULL, " .
                "PRIMARY KEY ({$quote}id{$quote})" .
                ')',
        };

        $this->assertEquals($expected, $result);
    }

    /**
     * Test polymorphic relation alter table migration for both databases.
     */
    #[DataProvider('databaseProvider')]
    public function testPolymorphicRelationAlterTableMigration(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            name: 'poll',
            operation: CompareResult::OPERATION_UPDATE,
            columns: [
                new ColumnCompareResult(
                    'pollable_type',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('string', false, null, 255, null, null, null, false, null),
                    new PropertiesData()
                ),
                new ColumnCompareResult(
                    'pollable_id',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('int', false, null, null, null, null, null, false, null),
                    new PropertiesData()
                ),
            ],
            indexes: [
                new IndexCompareResult(
                    'pollable_morph_index',
                    CompareResult::OPERATION_CREATE,
                    ['pollable_type', 'pollable_id'],
                    false
                ),
            ],
            foreignKeys: []
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

        $result = $generator->generate($tableCompareResult);

        $expected = "ALTER TABLE {$quote}poll{$quote} " .
            "ADD {$quote}pollable_type{$quote} VARCHAR(255) NOT NULL, " .
            "ADD {$quote}pollable_id{$quote} {$intType} NOT NULL, " .
            "ADD INDEX {$quote}pollable_morph_index{$quote} ({$quote}pollable_type{$quote}, {$quote}pollable_id{$quote})";

        $this->assertEquals($expected, $result);
    }

    /**
     * Test polymorphic relation migration rollback for both databases.
     */
    #[DataProvider('databaseProvider')]
    public function testPolymorphicRelationMigrationRollback(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            name: 'poll',
            operation: CompareResult::OPERATION_UPDATE,
            columns: [
                new ColumnCompareResult(
                    'pollable_type',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('string', false, null, 255, null, null, null, false, null),
                    new PropertiesData()
                ),
                new ColumnCompareResult(
                    'pollable_id',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('int', false, null, null, null, null, null, false, null),
                    new PropertiesData()
                ),
            ],
            indexes: [
                new IndexCompareResult(
                    'pollable_morph_index',
                    CompareResult::OPERATION_CREATE,
                    ['pollable_type', 'pollable_id'],
                    false
                ),
            ],
            foreignKeys: []
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

        $expected = "ALTER TABLE {$quote}poll{$quote} " .
            "DROP INDEX {$quote}pollable_morph_index{$quote}, " .
            "DROP {$quote}pollable_type{$quote}, " .
            "DROP {$quote}pollable_id{$quote}";

        $this->assertEquals($expected, $result);
    }
}
