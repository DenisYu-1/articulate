<?php

namespace Articulate\Tests\Modules\MigrationsGenerator;

use Articulate\Connection;
use Articulate\Modules\Database\SchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\CompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\IndexCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\PropertiesData;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;
use Articulate\Modules\Migrations\Generator\MigrationsCommandGenerator;
use Articulate\Tests\AbstractTestCase;

class MigrationsCommandGeneratorIndexesTest extends AbstractTestCase {
    public function testDropsIndex()
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

        $this->assertEquals(
            'ALTER TABLE `test_table` DROP INDEX `idx_test_table_id`',
            (MigrationsCommandGenerator::forMySql())->generate($tableCompareResult)
        );
    }

    public function testDropsIndexOnUpdate()
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

        $this->assertEquals(
            'ALTER TABLE `test_table` DROP INDEX `idx_test_table_id`, MODIFY `id` VARCHAR(255) NOT NULL',
            (MigrationsCommandGenerator::forMySql())->generate($tableCompareResult)
        );
    }

    public function testRestoresDeletedIndexOnRollback()
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

        $this->assertEquals(
            'ALTER TABLE `test_table` ADD `id` VARCHAR(255) NOT NULL, ADD INDEX `idx_test_table_id` (`id`)',
            (MigrationsCommandGenerator::forMySql())->rollback($tableCompareResult)
        );
    }

    public function testCreatesConcurrentIndex()
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

        // PostgreSQL should use CONCURRENTLY
        $this->assertEquals(
            'ALTER TABLE "test_table" ADD CONCURRENTLY UNIQUE INDEX "idx_test_table_email" ("email")',
            (MigrationsCommandGenerator::forDatabase(Connection::PGSQL))->generate($tableCompareResult)
        );

        // MySQL should use ALGORITHM=INPLACE for the ALTER TABLE
        $this->assertEquals(
            'ALTER TABLE `test_table` ALGORITHM=INPLACE ADD UNIQUE INDEX `idx_test_table_email` (`email`)',
            (MigrationsCommandGenerator::forMySql())->generate($tableCompareResult)
        );
    }
}
