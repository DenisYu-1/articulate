<?php

namespace Articulate\Tests\Modules\MigrationsGenerator;

use Articulate\Modules\DatabaseSchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\ForeignKeyCompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\PropertiesData;
use Articulate\Modules\DatabaseSchemaComparator\Models\TableCompareResult;
use Articulate\Modules\MigrationsGenerator\MigrationsCommandGenerator;
use Articulate\Tests\AbstractTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class MigrationsCommandGeneratorRelationsTest extends AbstractTestCase
{
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
            (new MigrationsCommandGenerator())->generate($tableCompareResult)
        );
    }

    public static function cases()
    {
        return [
            [
                'query' => 'ALTER TABLE `test_table` ADD id VARCHAR(255) NOT NULL',
                'params' => [
                    'id',
                    'create',
                    new PropertiesData('string', false),
                    new PropertiesData(),
                ],
            ], [
                'query' => 'ALTER TABLE `test_table` DROP id',
                'params' => [
                    'id',
                    'delete',
                    new PropertiesData('string', false),
                    new PropertiesData(),
                ],
            ], [
                'query' => 'ALTER TABLE `test_table` MODIFY id VARCHAR(255) NOT NULL',
                'params' => [
                    'id',
                    'update',
                    new PropertiesData('string', false),
                    new PropertiesData(),
                ],
            ], [
                'query' => 'ALTER TABLE `test_table` MODIFY id VARCHAR(255) NOT NULL DEFAULT "test"',
                'params' => [
                    'id',
                    'update',
                    new PropertiesData('string', false, 'test'),
                    new PropertiesData(),
                ],
            ], [
                'query' => 'ALTER TABLE `test_table` MODIFY id VARCHAR(254) NOT NULL DEFAULT "test"',
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
                    name: 'fk_test_table_related_entity',
                    operation: 'create',
                    column: 'related_entity_id',
                    referencedTable: 'related_entity',
                ),
            ],
        );

        $this->assertEquals(
            'ALTER TABLE `test_table` ADD related_entity_id int NOT NULL, ADD CONSTRAINT `fk_test_table_related_entity` FOREIGN KEY (`related_entity_id`) REFERENCES `related_entity`(`id`)',
            (new MigrationsCommandGenerator())->generate($tableCompareResult)
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
            'ALTER TABLE `test_table` ADD related_entity_id int NOT NULL',
            (new MigrationsCommandGenerator())->generate($tableCompareResult)
        );
    }
}
