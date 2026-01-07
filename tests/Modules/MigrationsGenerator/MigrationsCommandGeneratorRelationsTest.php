<?php

namespace Articulate\Tests\Modules\MigrationsGenerator;

use Articulate\Modules\Database\SchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\ForeignKeyCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\PropertiesData;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;
use Articulate\Tests\AbstractTestCase;
use Articulate\Tests\MigrationsGeneratorTestHelper;
use PHPUnit\Framework\Attributes\DataProvider;

class MigrationsCommandGeneratorRelationsTest extends AbstractTestCase {
    #[DataProvider('cases')]
    public function testFieldMigration($query, $params)
    {
        $tableCompareResult = new TableCompareResult(
            'test_table',
            'update',
            [
                new ColumnCompareResult(...$params),
            ],
            [],
            [],
        );
        $this->assertEquals(
            $query,
            MigrationsGeneratorTestHelper::forMySql()->generate($tableCompareResult)
        );
    }

    public static function cases()
    {
        return [
            [
                'query' => 'ALTER TABLE `test_table` ADD `id` VARCHAR(255) NOT NULL',
                'params' => [
                    'id',
                    'create',
                    new PropertiesData('string', false),
                    new PropertiesData(),
                ],
            ], [
                'query' => 'ALTER TABLE `test_table` DROP `id`',
                'params' => [
                    'id',
                    'delete',
                    new PropertiesData('string', false),
                    new PropertiesData(),
                ],
            ], [
                'query' => 'ALTER TABLE `test_table` MODIFY `id` VARCHAR(255) NOT NULL',
                'params' => [
                    'id',
                    'update',
                    new PropertiesData('string', false),
                    new PropertiesData(),
                ],
            ], [
                'query' => 'ALTER TABLE `test_table` MODIFY `id` VARCHAR(255) NOT NULL DEFAULT "test"',
                'params' => [
                    'id',
                    'update',
                    new PropertiesData('string', false, 'test'),
                    new PropertiesData(),
                ],
            ], [
                'query' => 'ALTER TABLE `test_table` MODIFY `id` VARCHAR(254) NOT NULL DEFAULT "test"',
                'params' => [
                    'id',
                    'update',
                    new PropertiesData('string', false, 'test', 254),
                    new PropertiesData(),
                ],
            ],
        ];
    }

    public function testOneToOneForeignKeyCreation()
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

        $this->assertEquals(
            'ALTER TABLE `test_table` ADD `related_entity_id` INT NOT NULL, ADD CONSTRAINT `fk_test_table_related_entity_related_entity_id` FOREIGN KEY (`related_entity_id`) REFERENCES `related_entity`(`id`)',
            MigrationsGeneratorTestHelper::forMySql()->generate($tableCompareResult)
        );
    }

    public function testOneToOneForeignKeySkipped()
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

        $this->assertEquals(
            'ALTER TABLE `test_table` ADD `related_entity_id` INT NOT NULL',
            MigrationsGeneratorTestHelper::forMySql()->generate($tableCompareResult)
        );
    }

    public function testOneToOneForeignKeyDrop()
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

        $this->assertEquals(
            'ALTER TABLE `test_table` DROP FOREIGN KEY `fk_test_table_related_entity_related_entity_id`',
            MigrationsGeneratorTestHelper::forMySql()->generate($tableCompareResult)
        );
    }

    public function testForeignKeyAndColumnDropCombined()
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

        $this->assertEquals(
            'ALTER TABLE `test_table` DROP FOREIGN KEY `fk_test_table_related_entity_related_entity_id`, DROP `related_entity_id`',
            MigrationsGeneratorTestHelper::forMySql()->generate($tableCompareResult)
        );
    }

    public function testRollbackRecreatesForeignKeyOnTableRestore()
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

        $this->assertEquals(
            'CREATE TABLE `test_table` (`related_entity_id` INT NOT NULL, CONSTRAINT `fk_test_table_related_entity_related_entity_id` FOREIGN KEY (`related_entity_id`) REFERENCES `related_entity`(`id`))',
            MigrationsGeneratorTestHelper::forMySql()->rollback($tableCompareResult)
        );
    }
}
