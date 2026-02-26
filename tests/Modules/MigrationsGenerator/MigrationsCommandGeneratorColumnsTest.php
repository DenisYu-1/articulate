<?php

namespace Articulate\Tests\Modules\MigrationsGenerator;

use Articulate\Modules\Database\SchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\PropertiesData;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;
use Articulate\Tests\DatabaseTestCase;
use Articulate\Tests\MigrationsGeneratorTestHelper;
use PHPUnit\Framework\Attributes\DataProvider;

class MigrationsCommandGeneratorColumnsTest extends DatabaseTestCase {
    /**
     * Test field migration operations for both databases.
     */
    #[DataProvider('databaseProvider')]
    public function testFieldMigration(string $databaseName): void
    {
        $cases = $this->getFieldMigrationCases($databaseName);

        foreach ($cases as $case) {
            $tableCompareResult = new TableCompareResult(
                'test_table',
                'update',
                [
                    new ColumnCompareResult(...$case['params']),
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
                "Failed for database: {$databaseName}"
            );
        }
    }

    /**
     * Get field migration test cases for the specified database.
     */
    private function getFieldMigrationCases(string $databaseName): array
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
                'query' => "ALTER TABLE {$quote}test_table{$quote} ADD {$quote}id{$quote} VARCHAR(255) NOT NULL",
                'params' => [
                    'id',
                    'create',
                    new PropertiesData('string', false),
                    new PropertiesData(),
                ],
            ], [
                'query' => "ALTER TABLE {$quote}test_table{$quote} DROP {$quote}id{$quote}",
                'params' => [
                    'id',
                    'delete',
                    new PropertiesData('string', false),
                    new PropertiesData(),
                ],
            ], [
                'query' => "ALTER TABLE {$quote}test_table{$quote} {$updateSyntax} {$quote}id{$quote} VARCHAR(255) NOT NULL",
                'params' => [
                    'id',
                    'update',
                    new PropertiesData('string', false),
                    new PropertiesData(),
                ],
            ], [
                'query' => "ALTER TABLE {$quote}test_table{$quote} {$updateSyntax} {$quote}id{$quote} VARCHAR(255) NOT NULL DEFAULT \"test\"",
                'params' => [
                    'id',
                    'update',
                    new PropertiesData('string', false, 'test'),
                    new PropertiesData(),
                ],
            ], [
                'query' => "ALTER TABLE {$quote}test_table{$quote} {$updateSyntax} {$quote}id{$quote} VARCHAR(254) NOT NULL DEFAULT \"test\"",
                'params' => [
                    'id',
                    'update',
                    new PropertiesData('string', false, 'test', 254),
                    new PropertiesData(),
                ],
            ], [
                'query' => "ALTER TABLE {$quote}test_table{$quote} ADD {$quote}user_id{$quote} " . ($databaseName === 'mysql' ? 'INT' : 'INTEGER') . ' NOT NULL',
                'params' => [
                    'user_id',
                    'create',
                    new PropertiesData('int', false),
                    new PropertiesData(),
                ],
            ], [
                'query' => "ALTER TABLE {$quote}test_table{$quote} DROP {$quote}old_column{$quote}",
                'params' => [
                    'old_column',
                    'delete',
                    new PropertiesData('string', true),
                    new PropertiesData(),
                ],
            ],
        ];
    }

    /**
     * Test column quoting in generated SQL for both databases.
     */
    #[DataProvider('databaseProvider')]
    public function testColumnQuotingInSql(string $databaseName): void
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

        $generator = match ($databaseName) {
            'mysql' => MigrationsGeneratorTestHelper::forMySql(),
            'pgsql' => MigrationsGeneratorTestHelper::forPostgresql(),
        };

        $quote = match ($databaseName) {
            'mysql' => '`',
            'pgsql' => '"',
        };

        $result = $generator->generate($tableCompareResult);
        $this->assertStringContainsString("ADD {$quote}user_id{$quote}", $result);
        $this->assertStringContainsString("DROP {$quote}old_column{$quote}", $result);
    }
}
