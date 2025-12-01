<?php

namespace Norm\Tests\Modules\MigrationsGenerator;

use Norm\Modules\DatabaseSchemaComparator\Models\ColumnCompareResult;
use Norm\Modules\DatabaseSchemaComparator\Models\PropertiesData;
use Norm\Modules\DatabaseSchemaComparator\Models\TableCompareResult;
use Norm\Modules\MigrationsGenerator\MigrationsCommandGenerator;
use Norm\Tests\AbstractTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class MigrationsCommandGeneratorColumnsTest extends AbstractTestCase
{

    #[DataProvider('cases')]
    public function testFieldMigration($query, $params)
    {
        $tableCompareResult = new TableCompareResult(
            'test_table',
            'update',
            [
                new ColumnCompareResult(...$params),
            ]
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
}
