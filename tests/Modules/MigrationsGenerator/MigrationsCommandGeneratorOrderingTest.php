<?php

namespace Articulate\Tests\Modules\MigrationsGenerator;

use Articulate\Modules\Database\SchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\CompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\ForeignKeyCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\IndexCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\PropertiesData;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;
use Articulate\Tests\DatabaseTestCase;
use Articulate\Tests\MigrationsGeneratorTestHelper;
use PHPUnit\Framework\Attributes\DataProvider;

class MigrationsCommandGeneratorOrderingTest extends DatabaseTestCase {
    /**
     * Test that drop operations order index drops before FK/column drops for both databases.
     * MySQL: all in one ALTER TABLE (FK, index, column order).
     * PostgreSQL: standalone DROP INDEX first, then ALTER TABLE with FK + column drops.
     */
    #[DataProvider('databaseProvider')]
    public function testDropOperationsOrderForeignKeysBeforeColumns(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            'test_table',
            'update',
            [
                new ColumnCompareResult(
                    'old_column',
                    'delete',
                    new PropertiesData('string', true),
                    new PropertiesData('string', true)
                ),
            ],
            [
                new IndexCompareResult('idx_old_column', 'delete', ['old_column'], false),
            ],
            [
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

        $stmts = $generator->generate($tableCompareResult);
        $result = implode(' ', $stmts);

        $this->assertStringContainsString("DROP {$fkKeyword} {$quote}fk_old_column{$quote}", $result, 'FK drop not found');
        $this->assertStringContainsString("DROP INDEX {$quote}idx_old_column{$quote}", $result, 'Index drop not found');
        $this->assertStringContainsString("DROP {$quote}old_column{$quote}", $result, 'Column drop not found');

        if ($databaseName === 'mysql') {
            // MySQL: all in one statement — FK before index before column
            $fkPos = strpos($result, "DROP {$fkKeyword} {$quote}fk_old_column{$quote}");
            $idxPos = strpos($result, "DROP INDEX {$quote}idx_old_column{$quote}");
            $colPos = strpos($result, "DROP {$quote}old_column{$quote}");

            $this->assertLessThan($idxPos, $fkPos, 'FK should be dropped before index (MySQL)');
            $this->assertLessThan($colPos, $idxPos, 'Index should be dropped before column (MySQL)');
        } else {
            // PostgreSQL: standalone DROP INDEX is a separate statement before the ALTER TABLE
            $this->assertCount(2, $stmts, 'PostgreSQL should emit 2 statements');
            $this->assertStringStartsWith('DROP INDEX', $stmts[0], 'First statement should be DROP INDEX');
            $this->assertStringContainsString('ALTER TABLE', $stmts[1], 'Second statement should be ALTER TABLE');
        }
    }

    /**
     * Test that add operations order columns/FKs before separate CREATE INDEX for both databases.
     * MySQL: all in one ALTER TABLE (columns, indexes, FKs).
     * PostgreSQL: ALTER TABLE (columns + FKs) first, then standalone CREATE INDEX.
     */
    #[DataProvider('databaseProvider')]
    public function testAddOperationsOrderColumnsBeforeIndexesBeforeForeignKeys(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            'test_table',
            'update',
            [
                new ColumnCompareResult(
                    'new_column',
                    'create',
                    new PropertiesData('string', false),
                    new PropertiesData()
                ),
            ],
            [
                new IndexCompareResult('idx_new_column', 'create', ['new_column'], false),
            ],
            [
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

        $stmts = $generator->generate($tableCompareResult);
        $result = implode(' ', $stmts);

        $this->assertStringContainsString("ADD {$quote}new_column{$quote}", $result, 'Column add not found');
        $this->assertStringContainsString("ADD CONSTRAINT {$quote}fk_new_column{$quote}", $result, 'FK add not found');

        if ($databaseName === 'mysql') {
            $this->assertStringContainsString("ADD INDEX {$quote}idx_new_column{$quote}", $result, 'Index add not found');
            $colPos = strpos($result, "ADD {$quote}new_column{$quote}");
            $idxPos = strpos($result, "ADD INDEX {$quote}idx_new_column{$quote}");
            $fkPos = strpos($result, "ADD CONSTRAINT {$quote}fk_new_column{$quote}");

            $this->assertLessThan($idxPos, $colPos, 'Column should be added before index');
            $this->assertLessThan($fkPos, $idxPos, 'Index should be added before FK');
        } else {
            // PostgreSQL: ALTER TABLE (col + FK), then CREATE INDEX
            $this->assertStringContainsString('CREATE INDEX "idx_new_column" ON "test_table"', $result, 'CREATE INDEX not found');
            $this->assertCount(2, $stmts, 'PostgreSQL should emit 2 statements');
            $this->assertStringContainsString('ALTER TABLE', $stmts[0], 'First statement should be ALTER TABLE');
            $this->assertStringStartsWith('CREATE INDEX', $stmts[1], 'Second statement should be CREATE INDEX');
        }
    }

    /**
     * Test that rollback ordering is correct for both databases.
     */
    #[DataProvider('databaseProvider')]
    public function testRollbackOrderingMatchesForwardMigration(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            'test_table',
            'update',
            [
                new ColumnCompareResult(
                    'column1',
                    'create',
                    new PropertiesData('string', false),
                    new PropertiesData()
                ),
                new ColumnCompareResult(
                    'column2',
                    'delete',
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

        $stmts = $generator->rollback($tableCompareResult);
        $result = implode(' ', $stmts);

        $this->assertStringContainsString("DROP {$fkKeyword} {$quote}fk_column1{$quote}", $result);
        $this->assertStringContainsString("DROP {$quote}column1{$quote}", $result);
        $this->assertStringContainsString("ADD {$quote}column2{$quote} {$intType}", $result);
        $this->assertStringContainsString("ADD CONSTRAINT {$quote}fk_column2{$quote}", $result);

        if ($databaseName === 'mysql') {
            $this->assertStringContainsString("DROP INDEX {$quote}idx_column1{$quote}", $result);
            $this->assertStringContainsString("ADD UNIQUE INDEX {$quote}idx_column2{$quote}", $result);
        } else {
            // PostgreSQL: idx_column1 (was CREATEd) rollback = DROP INDEX standalone
            // idx_column2 (was DELETEd) rollback = CREATE INDEX standalone
            $this->assertStringContainsString('DROP INDEX "idx_column1"', $result);
            $this->assertStringContainsString('CREATE UNIQUE INDEX "idx_column2" ON "test_table"', $result);
        }
    }

    public function testMysqlRollbackDropsReferencingTableBeforeReferencedTable(): void
    {
        $generator = MigrationsGeneratorTestHelper::forMySql();
        $orderedCreateResults = [
            new TableCompareResult('customer_addresses', CompareResult::OPERATION_CREATE),
            new TableCompareResult(
                'customers',
                CompareResult::OPERATION_CREATE,
                foreignKeys: [
                    new ForeignKeyCompareResult(
                        'fk_customers_address_id',
                        CompareResult::OPERATION_CREATE,
                        'address_id',
                        'customer_addresses',
                    ),
                ],
            ),
        ];

        $rollbackStatements = [];
        foreach ($orderedCreateResults as $result) {
            array_push($rollbackStatements, ...$generator->rollback($result));
        }

        $downStatements = array_reverse($rollbackStatements);

        $this->assertSame(
            ['DROP TABLE `customers`', 'DROP TABLE `customer_addresses`'],
            $downStatements,
        );
    }

    /**
     * Test complex mixed operations ordering for both databases.
     */
    #[DataProvider('databaseProvider')]
    public function testComplexMixedOperationsOrdering(string $databaseName): void
    {
        $tableCompareResult = new TableCompareResult(
            'test_table',
            'update',
            [
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
                new IndexCompareResult('idx_new_col', 'create', ['new_col'], false),
                new IndexCompareResult('idx_old_col', 'delete', ['old_col'], true),
            ],
            [
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

        $stmts = $generator->generate($tableCompareResult);
        $result = implode(' ', $stmts);

        // All these must be present in the output
        $this->assertStringContainsString("DROP {$fkKeyword} {$quote}fk_old_col{$quote}", $result);
        $this->assertStringContainsString("ADD {$quote}new_col{$quote}", $result);
        $this->assertStringContainsString("DROP {$quote}old_col{$quote}", $result);
        $this->assertStringContainsString("ADD CONSTRAINT {$quote}fk_new_col{$quote}", $result);

        if ($databaseName === 'mysql') {
            $this->assertStringContainsString("DROP INDEX {$quote}idx_old_col{$quote}", $result);
            $this->assertStringContainsString("ADD INDEX {$quote}idx_new_col{$quote}", $result);

            // MySQL ordering: DROP FK -> DROP INDEX -> ADD col -> DROP col -> ADD INDEX -> ADD FK
            $positions = [
                strpos($result, "DROP {$fkKeyword} {$quote}fk_old_col{$quote}"),
                strpos($result, "DROP INDEX {$quote}idx_old_col{$quote}"),
                strpos($result, "ADD {$quote}new_col{$quote}"),
                strpos($result, "DROP {$quote}old_col{$quote}"),
                strpos($result, "ADD INDEX {$quote}idx_new_col{$quote}"),
                strpos($result, "ADD CONSTRAINT {$quote}fk_new_col{$quote}"),
            ];
            for ($i = 1; $i < count($positions); $i++) {
                $this->assertLessThan($positions[$i], $positions[$i - 1]);
            }
        } else {
            // PostgreSQL: standalone DROP INDEX first, then ALTER TABLE, then standalone CREATE INDEX
            $this->assertStringContainsString('DROP INDEX "idx_old_col"', $result);
            $this->assertStringContainsString('CREATE INDEX "idx_new_col" ON "test_table"', $result);
            $this->assertCount(3, $stmts, 'PostgreSQL should emit 3 statements');
            $this->assertStringStartsWith('DROP INDEX', $stmts[0]);
            $this->assertStringContainsString('ALTER TABLE', $stmts[1]);
            $this->assertStringStartsWith('CREATE INDEX', $stmts[2]);
        }
    }
}
