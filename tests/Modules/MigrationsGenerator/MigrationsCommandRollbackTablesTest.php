<?php

namespace Norm\Tests\Modules\MigrationsGenerator;

use Norm\Modules\DatabaseSchemaComparator\Models\ColumnCompareResult;
use Norm\Modules\DatabaseSchemaComparator\Models\PropertiesData;
use Norm\Modules\DatabaseSchemaComparator\Models\TableCompareResult;
use Norm\Modules\MigrationsGenerator\MigrationsCommandGenerator;
use Norm\Tests\AbstractTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class MigrationsCommandRollbackTablesTest extends AbstractTestCase
{
    #[DataProvider('cases')]
    public function testCreateTable(string $query, string $operation, array $parameters)
    {
        $tableCompareResult = new TableCompareResult(
            'test_table',
            $operation,
            [
                new ColumnCompareResult(...$parameters),
            ]
        );
        $this->assertEquals(
            $query,
            (new MigrationsCommandGenerator())->rollback($tableCompareResult)
        );
    }

    public static function cases()
    {
        return [
            [
                'query' => 'DROP TABLE `test_table`',
                'operation' => 'create',
                'parameters' => [
                    'name' => 'id',
                    'operation' => 'create',
                    'propertyData' => new PropertiesData('string', false),
                    'columnData' => new PropertiesData(),
                ],
            ],
            [
                'query' => 'CREATE TABLE `test_table` (id VARCHAR(255) NOT NULL)',
                'operation' => 'delete',
                'parameters' => [
                    'name' => 'id',
                    'operation' => 'create',
                    'propertyData' => new PropertiesData('int', false),
                    'columnData' => new PropertiesData('string', false),
                ],
            ],
            [
                'query' => 'ALTER TABLE `test_table` MODIFY id int NOT NULL',
                'operation' => 'update',
                'parameters' => [
                    'name' => 'id',
                    'operation' => 'update',
                    'propertyData' => new PropertiesData('string', false),
                    'columnData' => new PropertiesData('int', false),
                ],
            ],
            [
                'query' => 'ALTER TABLE `test_table` DROP COLUMN id',
                'operation' => 'update',
                'parameters' => [
                    'name' => 'id',
                    'operation' => 'create',
                    'propertyData' => new PropertiesData('string', false, 'test'),
                    'columnData' => new PropertiesData(),
                ],
            ],
            [
                'query' => 'ALTER TABLE `test_table` DROP COLUMN id',
                'operation' => 'update',
                'parameters' => [
                    'name' => 'id',
                    'operation' => 'create',
                    'propertyData' => new PropertiesData('string', true),
                    'columnData' => new PropertiesData(),
                ],
            ],
        ];
    }
}
