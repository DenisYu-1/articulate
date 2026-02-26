<?php

namespace Articulate\Tests\Modules\MigrationsGenerator;

use Articulate\Modules\Database\SchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\CompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\ForeignKeyCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\PropertiesData;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;
use Articulate\Schema\SchemaNaming;
use Articulate\Tests\DatabaseTestCase;
use Articulate\Tests\MigrationsGeneratorTestHelper;
use PHPUnit\Framework\Attributes\DataProvider;

class MigrationsCommandGeneratorManyToManyTest extends DatabaseTestCase {
    /**
     * Test creating a basic many-to-many mapping table.
     */
    #[DataProvider('databaseProvider')]
    public function testCreateBasicManyToManyMappingTable(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            'user_post_map',
            CompareResult::OPERATION_CREATE,
            [
                new ColumnCompareResult(
                    'user_id',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('int', false),
                    new PropertiesData()
                ),
                new ColumnCompareResult(
                    'post_id',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('int', false),
                    new PropertiesData()
                ),
            ],
            [],
            [
                new ForeignKeyCompareResult(
                    (new SchemaNaming())->foreignKeyName('user_post_map', 'users', 'user_id'),
                    CompareResult::OPERATION_CREATE,
                    'user_id',
                    'users'
                ),
                new ForeignKeyCompareResult(
                    (new SchemaNaming())->foreignKeyName('user_post_map', 'posts', 'post_id'),
                    CompareResult::OPERATION_CREATE,
                    'post_id',
                    'posts'
                ),
            ],
            ['user_id', 'post_id']
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

        // Should create table with compound primary key and foreign keys
        $expected = "CREATE TABLE {$quote}user_post_map{$quote} (" .
            "{$quote}user_id{$quote} {$intType} NOT NULL, " .
            "{$quote}post_id{$quote} {$intType} NOT NULL, " .
            "PRIMARY KEY ({$quote}user_id{$quote}, {$quote}post_id{$quote}), " .
            "CONSTRAINT {$quote}" . (new SchemaNaming())->foreignKeyName('user_post_map', 'users', 'user_id') . "{$quote} FOREIGN KEY ({$quote}user_id{$quote}) REFERENCES {$quote}users{$quote}({$quote}id{$quote}), " .
            "CONSTRAINT {$quote}" . (new SchemaNaming())->foreignKeyName('user_post_map', 'posts', 'post_id') . "{$quote} FOREIGN KEY ({$quote}post_id{$quote}) REFERENCES {$quote}posts{$quote}({$quote}id{$quote})" .
            ')';

        $this->assertEquals($expected, $result);
    }

    /**
     * Test creating a many-to-many mapping table with extra columns.
     */
    #[DataProvider('databaseProvider')]
    public function testCreateManyToManyMappingTableWithExtras(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            'owner_target_map',
            CompareResult::OPERATION_CREATE,
            [
                new ColumnCompareResult(
                    'test_many_to_many_owner_id',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('int', false),
                    new PropertiesData()
                ),
                new ColumnCompareResult(
                    'test_many_to_many_target_id',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('int', false),
                    new PropertiesData()
                ),
                new ColumnCompareResult(
                    'created_at',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('datetime', true),
                    new PropertiesData()
                ),
            ],
            [],
            [
                new ForeignKeyCompareResult(
                    (new SchemaNaming())->foreignKeyName('owner_target_map', 'test_many_to_many_owner', 'test_many_to_many_owner_id'),
                    CompareResult::OPERATION_CREATE,
                    'test_many_to_many_owner_id',
                    'test_many_to_many_owner'
                ),
                new ForeignKeyCompareResult(
                    (new SchemaNaming())->foreignKeyName('owner_target_map', 'test_many_to_many_target', 'test_many_to_many_target_id'),
                    CompareResult::OPERATION_CREATE,
                    'test_many_to_many_target_id',
                    'test_many_to_many_target'
                ),
            ],
            ['test_many_to_many_owner_id', 'test_many_to_many_target_id']
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

        $datetimeType = match ($databaseName) {
            'mysql' => 'DATETIME',
            'pgsql' => 'TIMESTAMP',
        };

        $result = $generator->generate($tableCompareResult);

        // Should create table with compound primary key, extra columns, and foreign keys
        $expected = "CREATE TABLE {$quote}owner_target_map{$quote} (" .
            "{$quote}test_many_to_many_owner_id{$quote} {$intType} NOT NULL, " .
            "{$quote}test_many_to_many_target_id{$quote} {$intType} NOT NULL, " .
            "{$quote}created_at{$quote} {$datetimeType}, " .
            "PRIMARY KEY ({$quote}test_many_to_many_owner_id{$quote}, {$quote}test_many_to_many_target_id{$quote}), " .
            "CONSTRAINT {$quote}" . (new SchemaNaming())->foreignKeyName('owner_target_map', 'test_many_to_many_owner', 'test_many_to_many_owner_id') . "{$quote} FOREIGN KEY ({$quote}test_many_to_many_owner_id{$quote}) REFERENCES {$quote}test_many_to_many_owner{$quote}({$quote}id{$quote}), " .
            "CONSTRAINT {$quote}" . (new SchemaNaming())->foreignKeyName('owner_target_map', 'test_many_to_many_target', 'test_many_to_many_target_id') . "{$quote} FOREIGN KEY ({$quote}test_many_to_many_target_id{$quote}) REFERENCES {$quote}test_many_to_many_target{$quote}({$quote}id{$quote})" .
            ')';

        $this->assertEquals($expected, $result);
    }

    /**
     * Test dropping a many-to-many mapping table.
     */
    #[DataProvider('databaseProvider')]
    public function testDropManyToManyMappingTable(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            'user_post_map',
            CompareResult::OPERATION_DELETE,
            [
                new ColumnCompareResult(
                    'user_id',
                    CompareResult::OPERATION_DELETE,
                    new PropertiesData(),
                    new PropertiesData('int', false)
                ),
                new ColumnCompareResult(
                    'post_id',
                    CompareResult::OPERATION_DELETE,
                    new PropertiesData(),
                    new PropertiesData('int', false)
                ),
            ],
            [],
            [
                new ForeignKeyCompareResult(
                    (new SchemaNaming())->foreignKeyName('user_post_map', 'users', 'user_id'),
                    CompareResult::OPERATION_DELETE,
                    'user_id',
                    'users'
                ),
                new ForeignKeyCompareResult(
                    (new SchemaNaming())->foreignKeyName('user_post_map', 'posts', 'post_id'),
                    CompareResult::OPERATION_DELETE,
                    'post_id',
                    'posts'
                ),
            ]
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

        // Should recreate the table with all original structure
        $intType = match ($databaseName) {
            'mysql' => 'INT',
            'pgsql' => 'INTEGER',
        };

        $expected = "CREATE TABLE {$quote}user_post_map{$quote} (" .
            "{$quote}user_id{$quote} {$intType} NOT NULL, " .
            "{$quote}post_id{$quote} {$intType} NOT NULL, " .
            "CONSTRAINT {$quote}" . (new SchemaNaming())->foreignKeyName('user_post_map', 'users', 'user_id') . "{$quote} FOREIGN KEY ({$quote}user_id{$quote}) REFERENCES {$quote}users{$quote}({$quote}id{$quote}), " .
            "CONSTRAINT {$quote}" . (new SchemaNaming())->foreignKeyName('user_post_map', 'posts', 'post_id') . "{$quote} FOREIGN KEY ({$quote}post_id{$quote}) REFERENCES {$quote}posts{$quote}({$quote}id{$quote})" .
            ')';

        $this->assertEquals($expected, $result);
    }
}
