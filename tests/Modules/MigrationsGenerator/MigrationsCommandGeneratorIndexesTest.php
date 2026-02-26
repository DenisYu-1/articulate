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

class MigrationsCommandGeneratorIndexesTest extends DatabaseTestCase {
    /**
     * Test dropping indexes for both databases.
     */
    #[DataProvider('databaseProvider')]
    public function testDropsIndex(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            name: 'test_table',
            operation: CompareResult::OPERATION_UPDATE,
            columns: [],
            indexes: [
                new IndexCompareResult(
                    name: 'idx_test_table_id',
                    operation: CompareResult::OPERATION_DELETE,
                    columns: ['id'],
                    isUnique: false,
                    isConcurrent: false,
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

        $expected = match ($databaseName) {
            'mysql' => "ALTER TABLE {$quote}test_table{$quote} DROP INDEX {$quote}idx_test_table_id{$quote}",
            'pgsql' => "ALTER TABLE {$quote}test_table{$quote} DROP INDEX {$quote}idx_test_table_id{$quote}",
        };

        $this->assertEquals(
            $expected,
            $generator->generate($tableCompareResult),
            "Failed for database: {$databaseName}"
        );
    }

    /**
     * Test dropping indexes with column updates for both databases.
     */
    #[DataProvider('databaseProvider')]
    public function testDropsIndexOnUpdate(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            name: 'test_table',
            operation: CompareResult::OPERATION_UPDATE,
            columns: [
                new ColumnCompareResult(
                    name: 'id',
                    operation: CompareResult::OPERATION_UPDATE,
                    propertyData: new PropertiesData('string', false),
                    columnData: new PropertiesData('string', false),
                ),
            ],
            indexes: [
                new IndexCompareResult(
                    name: 'idx_test_table_id',
                    operation: CompareResult::OPERATION_DELETE,
                    columns: ['id'],
                    isUnique: false,
                    isConcurrent: false,
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

        $updateSyntax = match ($databaseName) {
            'mysql' => 'MODIFY',
            'pgsql' => 'ALTER COLUMN',
        };

        $expected = "ALTER TABLE {$quote}test_table{$quote} DROP INDEX {$quote}idx_test_table_id{$quote}, {$updateSyntax} {$quote}id{$quote} VARCHAR(255) NOT NULL";

        $this->assertEquals(
            $expected,
            $generator->generate($tableCompareResult),
            "Failed for database: {$databaseName}"
        );
    }

    /**
     * Test restoring deleted indexes on rollback for both databases.
     */
    #[DataProvider('databaseProvider')]
    public function testRestoresDeletedIndexOnRollback(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            name: 'test_table',
            operation: CompareResult::OPERATION_UPDATE,
            columns: [
                new ColumnCompareResult(
                    name: 'id',
                    operation: CompareResult::OPERATION_DELETE,
                    propertyData: new PropertiesData('string', false),
                    columnData: new PropertiesData('string', false),
                ),
            ],
            indexes: [
                new IndexCompareResult(
                    name: 'idx_test_table_id',
                    operation: CompareResult::OPERATION_DELETE,
                    columns: ['id'],
                    isUnique: false,
                    isConcurrent: false,
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

        $expected = "ALTER TABLE {$quote}test_table{$quote} ADD {$quote}id{$quote} VARCHAR(255) NOT NULL, ADD INDEX {$quote}idx_test_table_id{$quote} ({$quote}id{$quote})";

        $this->assertEquals(
            $expected,
            $generator->rollback($tableCompareResult),
            "Failed for database: {$databaseName}"
        );
    }

    /**
     * Test creating concurrent indexes for both databases.
     */
    #[DataProvider('databaseProvider')]
    public function testCreatesConcurrentIndex(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            name: 'test_table',
            operation: CompareResult::OPERATION_UPDATE,
            columns: [],
            indexes: [
                new IndexCompareResult(
                    name: 'idx_test_table_email',
                    operation: CompareResult::OPERATION_CREATE,
                    columns: ['email'],
                    isUnique: true,
                    isConcurrent: true,
                ),
            ],
        );

        $generator = match ($databaseName) {
            'mysql' => MigrationsGeneratorTestHelper::forMySql(),
            'pgsql' => MigrationsGeneratorTestHelper::forPostgresql(),
        };

        $expected = match ($databaseName) {
            'mysql' => 'ALTER TABLE `test_table` ALGORITHM=INPLACE ADD UNIQUE INDEX `idx_test_table_email` (`email`)',
            'pgsql' => 'ALTER TABLE "test_table" ADD CONCURRENTLY UNIQUE INDEX "idx_test_table_email" ("email")',
        };

        $this->assertEquals(
            $expected,
            $generator->generate($tableCompareResult),
            "Failed for database: {$databaseName}"
        );
    }
}
