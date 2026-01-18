<?php

namespace Articulate\Modules\Migrations\Generator;

use Articulate\Modules\Database\PostgresqlTypeMapper;
use Articulate\Modules\Database\SchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\CompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\PropertiesData;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;

class PostgresqlMigrationGenerator extends AbstractMigrationGenerator implements MigrationGeneratorInterface {
    public function __construct(
        PostgresqlTypeMapper $typeRegistry
    ) {
        parent::__construct($typeRegistry);
    }

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
        $alterParts = [];

        // Generate changes in correct order: deletions first, then modifications, then additions
        $alterParts = array_merge(
            $alterParts,
            $this->generateForeignKeyChanges($compareResult->foreignKeys, CompareResult::OPERATION_DELETE),
            $this->generateIndexChanges($compareResult->indexes, CompareResult::OPERATION_DELETE),
            $this->generateColumnChanges($compareResult->columns, false),
            $this->generateIndexChanges($compareResult->indexes, CompareResult::OPERATION_CREATE, $compareResult->name),
            $this->generateForeignKeyChanges($compareResult->foreignKeys, CompareResult::OPERATION_CREATE)
        );

        if (empty($alterParts)) {
            return '';
        }

        return 'ALTER TABLE "' . $compareResult->name . '" ' . implode(', ', $alterParts);
    }

    /**
     * Generates foreign key changes for the specified operation.
     *
     * @param ForeignKeyCompareResult[] $foreignKeys
     * @param bool $isRollback Whether this is for rollback (reverses operations)
     * @return string[]
     */
    private function generateForeignKeyChanges(array $foreignKeys, string $operation, bool $isRollback = false): array
    {
        $changes = [];
        foreach ($foreignKeys as $foreignKey) {
            $targetOperation = $isRollback ? $this->reverseOperation($foreignKey->operation) : $foreignKey->operation;
            if ($targetOperation === $operation) {
                if ($operation === CompareResult::OPERATION_DELETE) {
                    $changes[] = $this->getDropForeignKeySyntax($foreignKey->name);
                } else {
                    $changes[] = $this->foreignKeyDefinition($foreignKey);
                }
            }
        }

        return $changes;
    }

    /**
     * Generates index changes for the specified operation.
     *
     * @param IndexCompareResult[] $indexes
     * @param bool $isRollback Whether this is for rollback (reverses operations)
     * @return string[]
     */
    private function generateIndexChanges(array $indexes, string $operation, string $tableName = '', bool $isRollback = false): array
    {
        $changes = [];
        foreach ($indexes as $index) {
            $targetOperation = $isRollback ? $this->reverseOperation($index->operation) : $index->operation;
            if ($targetOperation === $operation) {
                if ($operation === CompareResult::OPERATION_DELETE) {
                    $changes[] = $this->getDropIndexSyntax($index->name);
                } else {
                    $changes[] = $this->generateIndexSql($index, $tableName ?: $index->tableName ?? '');
                }
            }
        }

        return $changes;
    }

    /**
     * Generates column changes.
     *
     * @param ColumnCompareResult[] $columns
     * @param bool $isRollback Whether this is for rollback (affects data source and operation reversal)
     * @return string[]
     */
    private function generateColumnChanges(array $columns, bool $isRollback = false): array
    {
        $changes = [];
        foreach ($columns as $column) {
            if ($isRollback) {
                $changes[] = $this->generateRollbackColumnChange($column);
            } else {
                $changes[] = $this->generateForwardColumnChange($column);
            }
        }

        return array_filter($changes); // Remove empty strings
    }

    /**
     * Generates a forward column change (for migration up).
     */
    private function generateForwardColumnChange(ColumnCompareResult $column): string
    {
        if ($column->operation === CompareResult::OPERATION_DELETE) {
            return 'DROP "' . $column->name . '"';
        }

        $parts = [];
        if ($column->operation === CompareResult::OPERATION_CREATE) {
            $parts[] = 'ADD';
        } else {
            $parts[] = 'ALTER COLUMN';
        }

        $parts[] = $this->columnDefinition($column->name, $column->propertyData);

        return implode(' ', $parts);
    }

    /**
     * Generates a rollback column change (for migration down).
     */
    private function generateRollbackColumnChange(ColumnCompareResult $column): string
    {
        if ($column->operation === CompareResult::OPERATION_CREATE) {
            // Undo ADD column -> DROP column
            return 'DROP "' . $column->name . '"';
        }

        $columnParts = [];
        if ($column->operation === CompareResult::OPERATION_DELETE) {
            // Undo DROP column -> ADD column
            $columnParts[] = 'ADD';
        } else {
            // Undo ALTER COLUMN -> ALTER COLUMN with original data
            $columnParts[] = 'ALTER COLUMN';
        }

        $columnParts[] = $this->columnDefinition($column->name, $column->columnData);

        return implode(' ', $columnParts);
    }

    protected function generateAlterTableRollback(TableCompareResult $compareResult): string
    {
        $alterParts = [];

        // For rollback, reverse the operations: undo creations first, then undo deletions
        $alterParts = array_merge(
            $alterParts,
            $this->generateForeignKeyChanges($compareResult->foreignKeys, CompareResult::OPERATION_DELETE, true), // undo ADD FK -> DROP FK
            $this->generateIndexChanges($compareResult->indexes, CompareResult::OPERATION_DELETE, $compareResult->name, true), // undo ADD INDEX -> DROP INDEX
            $this->generateColumnChanges($compareResult->columns, true), // rollback column ops
            $this->generateIndexChanges($compareResult->indexes, CompareResult::OPERATION_CREATE, $compareResult->name, true), // undo DROP INDEX -> ADD INDEX
            $this->generateForeignKeyChanges($compareResult->foreignKeys, CompareResult::OPERATION_CREATE, true) // undo DROP FK -> ADD FK
        );

        if (empty($alterParts)) {
            return '';
        }

        return 'ALTER TABLE "' . $compareResult->name . '" ' . implode(', ', $alterParts);
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
        return 'ALTER COLUMN "' . $columnName . '" TYPE ' . $this->mapTypeLength($column);
    }

    protected function getConcurrentIndexPrefix(): string
    {
        return 'CONCURRENTLY ';
    }

    protected function getPrimaryKeyGenerationSql(string $generatorType, ?string $sequence = null): string
    {
        return match ($generatorType) {
            'serial' => 'DEFAULT nextval(\'' . ($sequence ?: 'serial') . '\')',
            'bigserial' => 'DEFAULT nextval(\'' . ($sequence ?: 'bigserial') . '\')',
            default => '', // UUID, ULID, etc. don't need special SQL
        };
    }

    protected function getAutoIncrementSql(): string
    {
        return 'GENERATED ALWAYS AS IDENTITY';
    }

    /**
     * Reverses an operation for rollback purposes.
     */
    private function reverseOperation(string $operation): string
    {
        return match ($operation) {
            CompareResult::OPERATION_CREATE => CompareResult::OPERATION_DELETE,
            CompareResult::OPERATION_DELETE => CompareResult::OPERATION_CREATE,
            default => $operation,
        };
    }
}
