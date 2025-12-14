<?php

namespace Articulate\Tests\Modules\MigrationsGenerator;

use Articulate\Modules\DatabaseSchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\CompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\IndexCompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\PropertiesData;
use Articulate\Modules\DatabaseSchemaComparator\Models\TableCompareResult;
use Articulate\Modules\MigrationsGenerator\MigrationsCommandGenerator;
use Articulate\Tests\AbstractTestCase;

class MigrationsCommandGeneratorIndexesTest extends AbstractTestCase
{
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
                ),
            ],
        );

        $this->assertEquals(
            'ALTER TABLE `test_table` ADD `id` VARCHAR(255) NOT NULL, ADD INDEX `idx_test_table_id` (`id`)',
            (MigrationsCommandGenerator::forMySql())->rollback($tableCompareResult)
        );
    }
}
