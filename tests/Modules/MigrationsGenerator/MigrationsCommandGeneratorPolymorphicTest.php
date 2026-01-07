<?php

namespace Articulate\Tests\Modules\MigrationsGenerator;

use Articulate\Modules\Database\SchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\CompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\IndexCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\PropertiesData;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;
use Articulate\Modules\Migrations\Generator\MigrationsCommandGenerator;
use Articulate\Tests\AbstractTestCase;
use Articulate\Tests\MigrationsGeneratorTestHelper;

class MigrationsCommandGeneratorPolymorphicTest extends AbstractTestCase {
    public function testPolymorphicRelationCreatesCorrectMigration()
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

        $result = MigrationsGeneratorTestHelper::forMySql()->generate($tableCompareResult);

        $expected = 'CREATE TABLE `poll` (' .
            '`id` INT NOT NULL, ' .
            '`question` VARCHAR(255) NOT NULL, ' .
            '`pollable_type` VARCHAR(255) NOT NULL, ' .
            '`pollable_id` INT NOT NULL, ' .
            'PRIMARY KEY (`id`), ' .
            'INDEX `pollable_morph_index` (`pollable_type`, `pollable_id`)' .
            ')';

        $this->assertEquals($expected, $result);
    }

    public function testPolymorphicRelationAlterTableMigration()
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

        $result = MigrationsGeneratorTestHelper::forMySql()->generate($tableCompareResult);

        $expected = 'ALTER TABLE `poll` ' .
            'ADD `pollable_type` VARCHAR(255) NOT NULL, ' .
            'ADD `pollable_id` INT NOT NULL, ' .
            'ADD INDEX `pollable_morph_index` (`pollable_type`, `pollable_id`)';

        $this->assertEquals($expected, $result);
    }

    public function testPolymorphicRelationMigrationRollback()
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

        $result = MigrationsGeneratorTestHelper::forMySql()->rollback($tableCompareResult);

        $expected = 'ALTER TABLE `poll` ' .
            'DROP INDEX `pollable_morph_index`, ' .
            'DROP `pollable_type`, ' .
            'DROP `pollable_id`';

        $this->assertEquals($expected, $result);
    }
}
