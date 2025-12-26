<?php

namespace Articulate\Modules\Migrations\Generator;

use Articulate\Modules\Database\SchemaComparator\Models\CompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\PropertiesData;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;

class SqliteMigrationGenerator extends AbstractMigrationGenerator
{
    public function getIdentifierQuote(): string
    {
        return '"';
    }

    protected function generateDropTable(string $tableName): string
    {
        return 'DROP TABLE "' . $tableName . '"';
    }

    protected function generateCreateTable(TableCompareResult $compareResult): string
    {
        $query = 'CREATE TABLE "' . $compareResult->name . '" (';
        $columns = [];
        foreach ($compareResult->columns as $column) {
            $columns[] = $this->columnDefinition($column->name, $column->propertyData);
        }
        $parts = [implode(', ', $columns)];
        if (!empty($compareResult->primaryColumns)) {
            $quotedColumns = array_map(fn ($col) => '"' . $col . '"', $compareResult->primaryColumns);
            $parts[] = 'PRIMARY KEY (' . implode(', ', $quotedColumns) . ')';
        }
        foreach ($compareResult->foreignKeys as $foreignKey) {
            if ($foreignKey->operation !== CompareResult::OPERATION_CREATE) {
                continue;
            }
            $parts[] = $this->foreignKeyDefinition($foreignKey, false);
        }
        $query .= implode(', ', $parts) . ')';

        return $query;
    }

    protected function generateAlterTable(TableCompareResult $compareResult): string
    {
        // SQLite doesn't support ALTER TABLE for most operations
        // In a real implementation, you'd need to recreate the table
        // For now, we'll return a comment indicating this limitation
        return '-- SQLite ALTER TABLE not fully supported - table recreation required';
    }

    protected function generateAlterTableRollback(TableCompareResult $compareResult): string
    {
        // SQLite doesn't support ALTER TABLE rollback
        return '-- SQLite ALTER TABLE rollback not supported';
    }

    protected function generateCreateTableFromRollback(TableCompareResult $compareResult): string
    {
        $query = 'CREATE TABLE "' . $compareResult->name . '" (';
        $columns = [];
        foreach ($compareResult->columns as $column) {
            $columns[] = $this->columnDefinition($column->name, $column->columnData);
        }
        $parts = [implode(', ', $columns)];
        if (!empty($compareResult->primaryColumns)) {
            $quotedColumns = array_map(fn ($col) => '"' . $col . '"', $compareResult->primaryColumns);
            $parts[] = 'PRIMARY KEY (' . implode(', ', $quotedColumns) . ')';
        }
        foreach ($compareResult->foreignKeys as $foreignKey) {
            if ($foreignKey->operation !== CompareResult::OPERATION_DELETE) {
                continue;
            }
            $parts[] = $this->foreignKeyDefinition($foreignKey, false);
        }
        $query .= implode(', ', $parts) . ')';

        // Add indexes
        foreach ($compareResult->indexes as $index) {
            if ($index->operation !== CompareResult::OPERATION_DELETE) {
                $query .= ', ' . $this->generateIndexSql($index, $compareResult->name, false);
            }
        }

        return $query;
    }

    protected function getForeignKeyKeyword(): string
    {
        return 'CONSTRAINT';
    }

    protected function getDropForeignKeySyntax(string $constraintName): string
    {
        return 'DROP CONSTRAINT "' . $constraintName . '"';
    }

    protected function getDropIndexSyntax(string $indexName): string
    {
        return 'DROP INDEX "' . $indexName . '"';
    }

    protected function getModifyColumnSyntax(string $columnName, PropertiesData $column): string
    {
        return '-- SQLite column modification requires table recreation';
    }
}
