<?php

namespace Articulate\Tests\Modules\MigrationsGenerator;

use Articulate\Modules\Database\SchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\PropertiesData;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;
use Articulate\Tests\DatabaseTestCase;
use Articulate\Tests\MigrationsGeneratorTestHelper;

class MigrationsCommandGeneratorTablesTest extends DatabaseTestCase {
    /**
     * Test table operations for both databases.
     *
     * @dataProvider databaseProvider
     */
    public function testCreateTable(string $databaseName): void
    {
        $cases = $this->getTableTestCases($databaseName);

        foreach ($cases as $case) {
            $tableCompareResult = new TableCompareResult(
                'test_table',
                $case['operation'],
                [
                    new ColumnCompareResult(...$case['parameters']),
                ],
                [],
                [],
            );

            $generator = match ($databaseName) {
                'mysql' => MigrationsGeneratorTestHelper::forMySql(),
                'pgsql' => MigrationsGeneratorTestHelper::forPostgresql(),
            };

            $this->assertEquals(
                $case['query'],
                $generator->generate($tableCompareResult),
                "Failed for database: {$databaseName}, operation: {$case['operation']}"
            );
        }
    }

    /**
     * Get table test cases for the specified database.
     */
    private function getTableTestCases(string $databaseName): array
    {
        $quote = match ($databaseName) {
            'mysql' => '`',
            'pgsql' => '"',
        };

        $updateSyntax = match ($databaseName) {
            'mysql' => 'MODIFY',
            'pgsql' => 'ALTER COLUMN',
        };

        return [
            [
                'query' => "CREATE TABLE {$quote}test_table{$quote} ({$quote}id{$quote} VARCHAR(255) NOT NULL)",
                'operation' => 'create',
                'parameters' => [
                    'name' => 'id',
                    'operation' => 'create',
                    'propertyData' => new PropertiesData('string', false),
                    'columnData' => new PropertiesData(),
                ],
            ],
            [
                'query' => "DROP TABLE {$quote}test_table{$quote}",
                'operation' => 'delete',
                'parameters' => [
                    'name' => 'id',
                    'operation' => 'create',
                    'propertyData' => new PropertiesData('string', false),
                    'columnData' => new PropertiesData(),
                ],
            ],
            [
                'query' => "ALTER TABLE {$quote}test_table{$quote} {$updateSyntax} {$quote}id{$quote} VARCHAR(255) NOT NULL",
                'operation' => 'update',
                'parameters' => [
                    'name' => 'id',
                    'operation' => 'update',
                    'propertyData' => new PropertiesData('string', false),
                    'columnData' => new PropertiesData('int', false),
                ],
            ],
            [
                'query' => "ALTER TABLE {$quote}test_table{$quote} ADD {$quote}id{$quote} VARCHAR(255) NOT NULL DEFAULT \"test\"",
                'operation' => 'update',
                'parameters' => [
                    'name' => 'id',
                    'operation' => 'create',
                    'propertyData' => new PropertiesData('string', false, 'test'),
                    'columnData' => new PropertiesData(),
                ],
            ],
            [
                'query' => "ALTER TABLE {$quote}test_table{$quote} ADD {$quote}id{$quote} VARCHAR(255)",
                'operation' => 'update',
                'parameters' => [
                    'name' => 'id',
                    'operation' => 'create',
                    'propertyData' => new PropertiesData('string', true),
                    'columnData' => new PropertiesData(),
                ],
            ],
            [
                'query' => "ALTER TABLE {$quote}test_table{$quote} ADD {$quote}id{$quote} VARCHAR(254)",
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
