<?php

namespace Articulate\Tests\Modules\MigrationsGenerator;

use Articulate\Modules\Database\SchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\CompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\PropertiesData;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;
use Articulate\Tests\DatabaseTestCase;
use Articulate\Tests\MigrationsGeneratorTestHelper;

class MigrationsCommandGeneratorPrimaryKeyTest extends DatabaseTestCase {
    /**
     * Test auto_increment primary key generation.
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
                    new PropertiesData('int', false, null, null, 'auto_increment', null, true),
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

        $intType = match ($databaseName) {
            'mysql' => 'INT UNSIGNED',
            'pgsql' => 'INTEGER',
        };

        // MySQL uses AUTO_INCREMENT, PostgreSQL doesn't generate special SQL for auto_increment
        $pkSql = $databaseName === 'mysql' ? ' AUTO_INCREMENT' : '';

        $expected = match ($databaseName) {
            'mysql' => "CREATE TABLE {$quote}users{$quote} (" .
                "{$quote}id{$quote} {$intType} AUTO_INCREMENT NOT NULL, " .
                "{$quote}name{$quote} VARCHAR(255) NOT NULL, " .
                "PRIMARY KEY ({$quote}id{$quote})" .
                ')',
            'pgsql' => "CREATE TABLE {$quote}users{$quote} (" .
                "{$quote}id{$quote} {$intType}  NOT NULL, " .
                "{$quote}name{$quote} VARCHAR(255) NOT NULL, " .
                "PRIMARY KEY ({$quote}id{$quote})" .
                ')',
        };

        $this->assertEquals($expected, $result);
    }

    /**
     * Test SERIAL primary key generation (PostgreSQL).
     */
    public function testSerialPrimaryKey(): void
    {
        $tableCompareResult = new TableCompareResult(
            'users',
            CompareResult::OPERATION_CREATE,
            [
                new ColumnCompareResult(
                    'id',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('int', false, null, null, 'serial', null, true),
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

        $generator = MigrationsGeneratorTestHelper::forPostgresql();
        $result = $generator->generate($tableCompareResult);

        $expected = 'CREATE TABLE "users" (' .
            '"id" INTEGER DEFAULT nextval(\'serial\') NOT NULL, ' .
            '"name" VARCHAR(255) NOT NULL, ' .
            'PRIMARY KEY ("id")' .
            ')';

        $this->assertEquals($expected, $result);
    }

    /**
     * Test BIGSERIAL primary key generation (PostgreSQL).
     */
    public function testBigSerialPrimaryKey(): void
    {
        $tableCompareResult = new TableCompareResult(
            'posts',
            CompareResult::OPERATION_CREATE,
            [
                new ColumnCompareResult(
                    'id',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('bigint', false, null, null, 'bigserial', null, true),
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

        $generator = MigrationsGeneratorTestHelper::forPostgresql();
        $result = $generator->generate($tableCompareResult);

        $expected = 'CREATE TABLE "posts" (' .
            '"id" bigint DEFAULT nextval(\'bigserial\') NOT NULL, ' .
            '"title" VARCHAR(255) NOT NULL, ' .
            'PRIMARY KEY ("id")' .
            ')';

        $this->assertEquals($expected, $result);
    }

    /**
     * Test UUID primary key generation (both databases).
     *
     * @dataProvider databaseProvider
     */
    public function testUuidPrimaryKey(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            'users',
            CompareResult::OPERATION_CREATE,
            [
                new ColumnCompareResult(
                    'id',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('uuid', false, null, null, 'uuid_v4', null, true),
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

        $uuidType = match ($databaseName) {
            'mysql' => 'uuid', // MySQL uses lowercase type names
            'pgsql' => 'UUID',
        };

        $result = $generator->generate($tableCompareResult);

        $expected = "CREATE TABLE {$quote}users{$quote} (" .
            "{$quote}id{$quote} {$uuidType}  NOT NULL, " .
            "{$quote}name{$quote} VARCHAR(255) NOT NULL, " .
            "PRIMARY KEY ({$quote}id{$quote})" .
            ')';

        $this->assertEquals($expected, $result);
    }

    /**
     * Test ULID primary key generation (both databases).
     *
     * @dataProvider databaseProvider
     */
    public function testUlidPrimaryKey(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            'documents',
            CompareResult::OPERATION_CREATE,
            [
                new ColumnCompareResult(
                    'id',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('ulid', false, null, null, 'ulid', null, true),
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

        $result = $generator->generate($tableCompareResult);

        $expected = "CREATE TABLE {$quote}documents{$quote} (" .
            "{$quote}id{$quote} ulid  NOT NULL, " .
            "{$quote}title{$quote} VARCHAR(255) NOT NULL, " .
            "PRIMARY KEY ({$quote}id{$quote})" .
            ')';

        $this->assertEquals($expected, $result);
    }

    /**
     * Test custom sequence for PostgreSQL primary key.
     */
    public function testCustomSequencePrimaryKey(): void
    {
        $tableCompareResult = new TableCompareResult(
            'orders',
            CompareResult::OPERATION_CREATE,
            [
                new ColumnCompareResult(
                    'id',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('int', false, null, null, 'serial', 'order_seq', true),
                    new PropertiesData()
                ),
                new ColumnCompareResult(
                    'order_number',
                    CompareResult::OPERATION_CREATE,
                    new PropertiesData('string', false, null, 50),
                    new PropertiesData()
                ),
            ],
            [],
            [],
            ['id']
        );

        $generator = MigrationsGeneratorTestHelper::forPostgresql();
        $result = $generator->generate($tableCompareResult);

        $expected = 'CREATE TABLE "orders" (' .
            '"id" INTEGER DEFAULT nextval(\'order_seq\') NOT NULL, ' .
            '"order_number" VARCHAR(50) NOT NULL, ' .
            'PRIMARY KEY ("id")' .
            ')';

        $this->assertEquals($expected, $result);
    }
}
