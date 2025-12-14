<?php

namespace Articulate\Tests\Modules\MigrationsGenerator;

use Articulate\Modules\DatabaseSchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\PropertiesData;
use Articulate\Modules\DatabaseSchemaComparator\Models\TableCompareResult;
use Articulate\Modules\MigrationsGenerator\MigrationsCommandGenerator;
use Articulate\Tests\AbstractTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class MigrationsCommandGeneratorTablesTest extends AbstractTestCase
{
    #[DataProvider('cases')]
    public function testCreateTable(string $query, string $operation, array $parameters)
    {
        $tableCompareResult = new TableCompareResult(
            'test_table',
            $operation,
            [
                new ColumnCompareResult(...$parameters),
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
                'query' => 'CREATE TABLE `test_table` (`id` VARCHAR(255) NOT NULL)',
                'operation' => 'create',
                'parameters' => [
                    'name' => 'id',
                    'operation' => 'create',
                    'propertyData' => new PropertiesData('string', false),
                    'columnData' => new PropertiesData(),
                ],
            ],
            [
                'query' => 'DROP TABLE `test_table`',
                'operation' => 'delete',
                'parameters' => [
                    'name' => 'id',
                    'operation' => 'create',
                    'propertyData' => new PropertiesData('string', false),
                    'columnData' => new PropertiesData(),
                ],
            ],
            [
                'query' => 'ALTER TABLE `test_table` MODIFY `id` VARCHAR(255) NOT NULL',
                'operation' => 'update',
                'parameters' => [
                    'name' => 'id',
                    'operation' => 'update',
                    'propertyData' => new PropertiesData('string', false),
                    'columnData' => new PropertiesData('int', false),
                ],
            ],
            [
                'query' => 'ALTER TABLE `test_table` ADD `id` VARCHAR(255) NOT NULL DEFAULT "test"',
                'operation' => 'update',
                'parameters' => [
                    'name' => 'id',
                    'operation' => 'create',
                    'propertyData' => new PropertiesData('string', false, 'test'),
                    'columnData' => new PropertiesData(),
                ],
            ],
            [
                'query' => 'ALTER TABLE `test_table` ADD `id` VARCHAR(255)',
                'operation' => 'update',
                'parameters' => [
                    'name' => 'id',
                    'operation' => 'create',
                    'propertyData' => new PropertiesData('string', true),
                    'columnData' => new PropertiesData(),
                ],
            ],
            [
                'query' => 'ALTER TABLE `test_table` ADD `id` VARCHAR(254)',
                'operation' => 'update',
                'parameters' => [
                    'name' => 'id',
                    'operation' => 'create',
                    'propertyData' => new PropertiesData('string', true, null, 254),
                    'columnData' => new PropertiesData(),
                ],
            ],
        ];
    }
}
