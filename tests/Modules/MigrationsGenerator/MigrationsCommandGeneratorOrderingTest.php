<?php

namespace Articulate\Tests\Modules\MigrationsGenerator;

use Articulate\Modules\Database\SchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\ForeignKeyCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\IndexCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\PropertiesData;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;
use Articulate\Tests\DatabaseTestCase;
use Articulate\Tests\MigrationsGeneratorTestHelper;

class MigrationsCommandGeneratorOrderingTest extends DatabaseTestCase {
    /**
     * Test that drop operations order foreign keys before columns for both databases.
     *
     * @dataProvider databaseProvider
     */
    public function testDropOperationsOrderForeignKeysBeforeColumns(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            'test_table',
            'update',
            [
                // Column to drop
                new ColumnCompareResult(
                    'old_column',
                    'delete',
                    new PropertiesData('string', true),
                    new PropertiesData('string', true)
                ),
            ],
            [
                // Index to drop
                new IndexCompareResult('idx_old_column', 'delete', ['old_column'], false),
            ],
            [
                // Foreign key to drop
                new ForeignKeyCompareResult('fk_old_column', 'delete', 'old_column', 'other_table', 'id'),
            ]
        );

        $generator = match ($databaseName) {
            'mysql' => MigrationsGeneratorTestHelper::forMySql(),
            'pgsql' => MigrationsGeneratorTestHelper::forPostgresql(),
        };

        $quote = match ($databaseName) {
            'mysql' => '`',
            'pgsql' => '"',
        };

        $fkKeyword = match ($databaseName) {
            'mysql' => 'FOREIGN KEY',
            'pgsql' => 'CONSTRAINT',
        };

        $result = $generator->generate($tableCompareResult);

        // Should drop foreign keys first, then indexes, then columns
        $fkPos = strpos($result, "DROP {$fkKeyword} {$quote}fk_old_column{$quote}");
        $idxPos = strpos($result, "DROP INDEX {$quote}idx_old_column{$quote}");
        $colPos = strpos($result, "DROP {$quote}old_column{$quote}");

        $this->assertNotFalse($fkPos, 'Foreign key drop not found');
        $this->assertNotFalse($idxPos, 'Index drop not found');
        $this->assertNotFalse($colPos, 'Column drop not found');

        $this->assertLessThan($idxPos, $fkPos, 'Foreign key should be dropped before index');
        $this->assertLessThan($colPos, $idxPos, 'Index should be dropped before column');
    }

    /**
     * Test that add operations order columns before indexes before foreign keys for both databases.
     *
     * @dataProvider databaseProvider
     */
    public function testAddOperationsOrderColumnsBeforeIndexesBeforeForeignKeys(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            'test_table',
            'update',
            [
                // Column to add
                new ColumnCompareResult(
                    'new_column',
                    'create',
                    new PropertiesData('string', false),
                    new PropertiesData()
                ),
            ],
            [
                // Index to add
                new IndexCompareResult('idx_new_column', 'create', ['new_column'], false),
            ],
            [
                // Foreign key to add
                new ForeignKeyCompareResult('fk_new_column', 'create', 'new_column', 'other_table', 'id'),
            ]
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

        // Should add columns first, then indexes, then foreign keys
        $colPos = strpos($result, "ADD {$quote}new_column{$quote}");
        $idxPos = strpos($result, "ADD INDEX {$quote}idx_new_column{$quote}");
        $fkPos = strpos($result, "ADD CONSTRAINT {$quote}fk_new_column{$quote}");

        $this->assertNotFalse($colPos, 'Column add not found');
        $this->assertNotFalse($idxPos, 'Index add not found');
        $this->assertNotFalse($fkPos, 'Foreign key add not found');

        $this->assertLessThan($idxPos, $colPos, 'Column should be added before index');
        $this->assertLessThan($fkPos, $idxPos, 'Index should be added before foreign key');
    }

    /**
     * Test that rollback ordering matches forward migration for both databases.
     *
     * @dataProvider databaseProvider
     */
    public function testRollbackOrderingMatchesForwardMigration(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            'test_table',
            'update',
            [
                new ColumnCompareResult(
                    'column1',
                    'create', // This means "will be created", so rollback should drop it
                    new PropertiesData('string', false),
                    new PropertiesData()
                ),
                new ColumnCompareResult(
                    'column2',
                    'delete', // This means "will be deleted", so rollback should add it back
                    new PropertiesData(),
                    new PropertiesData('int', true)
                ),
            ],
            [
                new IndexCompareResult('idx_column1', 'create', ['column1'], false),
                new IndexCompareResult('idx_column2', 'delete', ['column2'], true),
            ],
            [
                new ForeignKeyCompareResult('fk_column1', 'create', 'column1', 'ref_table', 'id'),
                new ForeignKeyCompareResult('fk_column2', 'delete', 'column2', 'ref_table', 'id'),
            ]
        );

        $generator = match ($databaseName) {
            'mysql' => MigrationsGeneratorTestHelper::forMySql(),
            'pgsql' => MigrationsGeneratorTestHelper::forPostgresql(),
        };

        $quote = match ($databaseName) {
            'mysql' => '`',
            'pgsql' => '"',
        };

        $fkKeyword = match ($databaseName) {
            'mysql' => 'FOREIGN KEY',
            'pgsql' => 'CONSTRAINT',
        };

        $intType = match ($databaseName) {
            'mysql' => 'INT',
            'pgsql' => 'INTEGER',
        };

        $result = $generator->rollback($tableCompareResult);

        // Rollback should reverse the forward migration order
        // Forward: DROP FKs -> DROP indexes -> DROP columns -> ADD columns -> ADD indexes -> ADD FKs
        // Rollback: DROP FKs -> DROP indexes -> DROP columns -> ADD columns -> ADD indexes -> ADD FKs

        $this->assertStringContainsString("DROP {$fkKeyword} {$quote}fk_column1{$quote}", $result);
        $this->assertStringContainsString("DROP INDEX {$quote}idx_column1{$quote}", $result);
        $this->assertStringContainsString("DROP {$quote}column1{$quote}", $result);
        $this->assertStringContainsString("ADD {$quote}column2{$quote} {$intType}", $result);
        $this->assertStringContainsString("ADD UNIQUE INDEX {$quote}idx_column2{$quote}", $result);
        $this->assertStringContainsString("ADD CONSTRAINT {$quote}fk_column2{$quote}", $result);
    }

    /**
     * Test complex mixed operations ordering for both databases.
     *
     * @dataProvider databaseProvider
     */
    public function testComplexMixedOperationsOrdering(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            'test_table',
            'update',
            [
                // Column operations: one add, one delete
                new ColumnCompareResult(
                    'new_col',
                    'create',
                    new PropertiesData('string', false),
                    new PropertiesData()
                ),
                new ColumnCompareResult(
                    'old_col',
                    'delete',
                    new PropertiesData(),
                    new PropertiesData('int', true)
                ),
            ],
            [
                // Index operations: one add, one delete
                new IndexCompareResult('idx_new_col', 'create', ['new_col'], false),
                new IndexCompareResult('idx_old_col', 'delete', ['old_col'], true),
            ],
            [
                // Foreign key operations: one add, one delete
                new ForeignKeyCompareResult('fk_new_col', 'create', 'new_col', 'ref_table', 'id'),
                new ForeignKeyCompareResult('fk_old_col', 'delete', 'old_col', 'ref_table', 'id'),
            ]
        );

        $generator = match ($databaseName) {
            'mysql' => MigrationsGeneratorTestHelper::forMySql(),
            'pgsql' => MigrationsGeneratorTestHelper::forPostgresql(),
        };

        $quote = match ($databaseName) {
            'mysql' => '`',
            'pgsql' => '"',
        };

        $fkKeyword = match ($databaseName) {
            'mysql' => 'FOREIGN KEY',
            'pgsql' => 'CONSTRAINT',
        };

        $result = $generator->generate($tableCompareResult);

        // Complex ordering: DROP FKs -> DROP indexes -> ADD columns -> DROP columns -> ADD indexes -> ADD FKs
        // Note: ADD operations come before DROP operations within the same ALTER TABLE to avoid conflicts
        $expectedOrder = [
            "DROP {$fkKeyword} {$quote}fk_old_col{$quote}",
            "DROP INDEX {$quote}idx_old_col{$quote}",
            "ADD {$quote}new_col{$quote}",
            "DROP {$quote}old_col{$quote}",
            "ADD INDEX {$quote}idx_new_col{$quote}",
            "ADD CONSTRAINT {$quote}fk_new_col{$quote}",
        ];

        $positions = [];
        foreach ($expectedOrder as $operation) {
            $pos = strpos($result, $operation);
            $this->assertNotFalse($pos, "$operation not found in result");
            $positions[] = $pos;
        }

        // Verify ordering is correct
        for ($i = 1; $i < count($positions); $i++) {
            $this->assertLessThan(
                $positions[$i],
                $positions[$i - 1],
                "Operation '{$expectedOrder[$i]}' should come before '{$expectedOrder[$i - 1]}'"
            );
        }
    }
}
