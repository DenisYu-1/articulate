<?php

namespace Articulate\Tests\Modules\MigrationsGenerator;

use Articulate\Modules\Database\SchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\PropertiesData;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;
use Articulate\Modules\Migrations\Generator\MigrationsCommandGenerator;
use Articulate\Tests\AbstractTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class MigrationsCommandGeneratorColumnsTest extends AbstractTestCase {
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
            (MigrationsCommandGenerator::forMySql())->generate($tableCompareResult)
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
            ], [
                'query' => 'ALTER TABLE `test_table` ADD `user_id` INT NOT NULL',
                'params' => [
                    'user_id',
                    'create',
                    new PropertiesData('int', false),
                    new PropertiesData(),
                ],
            ], [
                'query' => 'ALTER TABLE `test_table` DROP `old_column`',
                'params' => [
                    'old_column',
                    'delete',
                    new PropertiesData('string', true),
                    new PropertiesData(),
                ],
            ],
        ];
    }

    public function testColumnQuotingInSql()
    {
        $tableCompareResult = new TableCompareResult(
            'test_table',
            'update',
            [
                new ColumnCompareResult(
                    'user_id',
                    'create',
                    new PropertiesData('int', false),
                    new PropertiesData()
                ),
                new ColumnCompareResult(
                    'old_column',
                    'delete',
                    new PropertiesData('string', true),
                    new PropertiesData()
                ),
            ],
            [],
            []
        );

        $result = (MigrationsCommandGenerator::forMySql())->generate($tableCompareResult);
        $this->assertStringContainsString('ADD `user_id`', $result);
        $this->assertStringContainsString('DROP `old_column`', $result);
    }
}
