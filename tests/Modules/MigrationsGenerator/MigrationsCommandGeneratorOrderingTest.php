<?php

namespace Articulate\Tests\Modules\MigrationsGenerator;

use Articulate\Modules\DatabaseSchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\ForeignKeyCompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\IndexCompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\PropertiesData;
use Articulate\Modules\DatabaseSchemaComparator\Models\TableCompareResult;
use Articulate\Modules\MigrationsGenerator\MigrationsCommandGenerator;
use Articulate\Tests\AbstractTestCase;

class MigrationsCommandGeneratorOrderingTest extends AbstractTestCase
{
    public function testDropOperationsOrderForeignKeysBeforeColumns()
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

        $result = (MigrationsCommandGenerator::forMySql())->generate($tableCompareResult);

        // Should drop foreign keys first, then indexes, then columns
        $fkPos = strpos($result, 'DROP FOREIGN KEY `fk_old_column`');
        $idxPos = strpos($result, 'DROP INDEX `idx_old_column`');
        $colPos = strpos($result, 'DROP `old_column`');

        $this->assertNotFalse($fkPos, 'Foreign key drop not found');
        $this->assertNotFalse($idxPos, 'Index drop not found');
        $this->assertNotFalse($colPos, 'Column drop not found');

        $this->assertLessThan($idxPos, $fkPos, 'Foreign key should be dropped before index');
        $this->assertLessThan($colPos, $idxPos, 'Index should be dropped before column');
    }

    public function testAddOperationsOrderColumnsBeforeIndexesBeforeForeignKeys()
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

        $result = (MigrationsCommandGenerator::forMySql())->generate($tableCompareResult);

        // Should add columns first, then indexes, then foreign keys
        $colPos = strpos($result, 'ADD `new_column`');
        $idxPos = strpos($result, 'ADD INDEX `idx_new_column`');
        $fkPos = strpos($result, 'ADD CONSTRAINT `fk_new_column`');

        $this->assertNotFalse($colPos, 'Column add not found');
        $this->assertNotFalse($idxPos, 'Index add not found');
        $this->assertNotFalse($fkPos, 'Foreign key add not found');

        $this->assertLessThan($idxPos, $colPos, 'Column should be added before index');
        $this->assertLessThan($fkPos, $idxPos, 'Index should be added before foreign key');
    }

    public function testRollbackOrderingMatchesForwardMigration()
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

        $result = (MigrationsCommandGenerator::forMySql())->rollback($tableCompareResult);

        // Rollback should reverse the forward migration order
        // Forward: DROP FKs -> DROP indexes -> DROP columns -> ADD columns -> ADD indexes -> ADD FKs
        // Rollback: DROP FKs -> DROP indexes -> DROP columns -> ADD columns -> ADD indexes -> ADD FKs

        $this->assertStringContainsString('DROP FOREIGN KEY `fk_column1`', $result);
        $this->assertStringContainsString('DROP INDEX `idx_column1`', $result);
        $this->assertStringContainsString('DROP `column1`', $result);
        $this->assertStringContainsString('ADD `column2`', $result);
        $this->assertStringContainsString('ADD UNIQUE INDEX `idx_column2`', $result);
        $this->assertStringContainsString('ADD CONSTRAINT `fk_column2`', $result);
    }

    public function testComplexMixedOperationsOrdering()
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

        $result = (MigrationsCommandGenerator::forMySql())->generate($tableCompareResult);

        // Complex ordering: DROP FKs -> DROP indexes -> ADD columns -> DROP columns -> ADD indexes -> ADD FKs
        // Note: ADD operations come before DROP operations within the same ALTER TABLE to avoid conflicts
        $expectedOrder = [
            'DROP FOREIGN KEY `fk_old_col`',
            'DROP INDEX `idx_old_col`',
            'ADD `new_col`',
            'DROP `old_col`',
            'ADD INDEX `idx_new_col`',
            'ADD CONSTRAINT `fk_new_col`',
        ];

        $positions = [];
        foreach ($expectedOrder as $operation) {
            $pos = strpos($result, $operation);
            $this->assertNotFalse($pos, "$operation not found in result");
            $positions[] = $pos;
        }

        // Verify ordering is correct
        for ($i = 1; $i < count($positions); $i++) {
            $this->assertLessThan($positions[$i], $positions[$i-1],
                "Operation '{$expectedOrder[$i]}' should come before '{$expectedOrder[$i-1]}'");
        }
    }
}
