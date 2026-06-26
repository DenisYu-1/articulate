<?php

namespace Articulate\Modules\Migrations\Generator;

use Articulate\Modules\Database\PostgresqlTypeMapper;
use Articulate\Modules\Database\SchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\CompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\ForeignKeyCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\IndexCompareResult;
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

    public function generate(TableCompareResult $compareResult): array
    {
        if ($compareResult->operation === CompareResult::OPERATION_DELETE) {
            return [$this->generateDropTable($compareResult->name)];
        }

        if ($compareResult->operation === CompareResult::OPERATION_CREATE) {
            $stmts = [$this->generateCreateTable($compareResult)];
            foreach ($compareResult->indexes as $index) {
                if ($index->operation === CompareResult::OPERATION_CREATE) {
                    $stmts[] = $this->buildCreateIndex($index, $compareResult->name);
                }
            }

            return $stmts;
        }

        // ALTER TABLE: index drops are standalone DROP INDEX, index creates are standalone CREATE INDEX
        $stmts = [];
        foreach ($compareResult->indexes as $index) {
            if ($index->operation === CompareResult::OPERATION_DELETE) {
                $stmts[] = 'DROP INDEX "' . $index->name . '"';
            }
        }
        $alter = $this->generateAlterTable($compareResult);
        if ($alter !== '') {
            $stmts[] = $alter;
        }
        foreach ($compareResult->indexes as $index) {
            if ($index->operation === CompareResult::OPERATION_CREATE) {
                $stmts[] = $this->buildCreateIndex($index, $compareResult->name);
            }
        }

        return array_values(array_filter($stmts));
    }

    public function rollback(TableCompareResult $compareResult): array
    {
        if ($compareResult->operation === CompareResult::OPERATION_CREATE) {
            return [$this->generateDropTable($compareResult->name)];
        }

        if ($compareResult->operation === CompareResult::OPERATION_DELETE) {
            $stmts = [$this->generateCreateTableFromRollback($compareResult)];
            foreach ($compareResult->indexes as $index) {
                if ($index->operation === CompareResult::OPERATION_DELETE) {
                    $stmts[] = $this->buildCreateIndex($index, $compareResult->name);
                }
            }

            return array_values(array_filter($stmts));
        }

        // Rollback of ALTER TABLE: undo CREATEs (drop them) first, then the alter, then undo DELETEs (recreate)
        $stmts = [];
        foreach ($compareResult->indexes as $index) {
            if ($index->operation === CompareResult::OPERATION_CREATE) {
                $stmts[] = 'DROP INDEX "' . $index->name . '"';
            }
        }
        $alter = $this->generateAlterTableRollback($compareResult);
        if ($alter !== '') {
            $stmts[] = $alter;
        }
        foreach ($compareResult->indexes as $index) {
            if ($index->operation === CompareResult::OPERATION_DELETE) {
                $stmts[] = $this->buildCreateIndex($index, $compareResult->name);
            }
        }

        return array_values(array_filter($stmts));
    }

    private function buildCreateIndex(IndexCompareResult $index, string $tableName): string
    {
        $uniqueKeyword = $index->isUnique ? 'UNIQUE ' : '';
        $concurrentKeyword = $index->isConcurrent ? 'CONCURRENTLY ' : '';
        $columns = implode(', ', array_map(fn ($col) => '"' . $col . '"', $index->columns));

        return sprintf(
            'CREATE %sINDEX %s"%s" ON "%s" (%s)',
            $uniqueKeyword,
            $concurrentKeyword,
            $index->name,
            $tableName,
            $columns
        );
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
        $alterParts = array_merge(
            $this->generateForeignKeyChanges($compareResult->foreignKeys, CompareResult::OPERATION_DELETE),
            $this->generateColumnChanges($compareResult->columns, false),
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
            $columnChanges = $isRollback
                ? $this->generateRollbackColumnChange($column)
                : $this->generateForwardColumnChange($column);

            array_push($changes, ...$columnChanges);
        }

        return array_filter($changes); // Remove empty strings
    }

    /**
     * Generates a forward column change (for migration up).
     */
    private function generateForwardColumnChange(ColumnCompareResult $column): array
    {
        if ($column->operation === CompareResult::OPERATION_DELETE) {
            return ['DROP "' . $column->name . '"'];
        }

        if ($column->operation === CompareResult::OPERATION_CREATE) {
            return ['ADD ' . $this->columnDefinition($column->name, $column->propertyData)];
        }

        return $this->generateModifyColumnChanges($column, $column->propertyData);
    }

    /**
     * Generates a rollback column change (for migration down).
     */
    private function generateRollbackColumnChange(ColumnCompareResult $column): array
    {
        if ($column->operation === CompareResult::OPERATION_CREATE) {
            // Undo ADD column -> DROP column
            return ['DROP "' . $column->name . '"'];
        }

        if ($column->operation === CompareResult::OPERATION_DELETE) {
            // Undo DROP column -> ADD column
            return ['ADD ' . $this->columnDefinition($column->name, $column->columnData)];
        }

        return $this->generateModifyColumnChanges($column, $column->columnData);
    }

    /**
     * @return string[]
     */
    private function generateModifyColumnChanges(ColumnCompareResult $column, PropertiesData $targetData): array
    {
        $changes = [];

        if (!$column->typeMatch || !$column->isLengthMatch) {
            $changes[] = $this->getModifyColumnSyntax($column->name, $targetData);
        }

        if (!$column->isNullableMatch && $targetData->isNullable !== null) {
            $changes[] = 'ALTER COLUMN "' . $column->name . '" ' . ($targetData->isNullable ? 'DROP NOT NULL' : 'SET NOT NULL');
        }

        if (!$column->isDefaultValueMatch) {
            if ($targetData->defaultValue === null) {
                $changes[] = 'ALTER COLUMN "' . $column->name . '" DROP DEFAULT';
            } else {
                $changes[] = 'ALTER COLUMN "' . $column->name . '" SET DEFAULT ' . $this->formatDefaultValue($targetData->defaultValue);
            }
        }

        return $changes;
    }

    private function formatDefaultValue(string $defaultValue): string
    {
        return "'" . str_replace("'", "''", $defaultValue) . "'";
    }

    protected function generateAlterTableRollback(TableCompareResult $compareResult): string
    {
        $alterParts = array_merge(
            $this->generateForeignKeyChanges($compareResult->foreignKeys, CompareResult::OPERATION_DELETE, true),
            $this->generateColumnChanges($compareResult->columns, true),
            $this->generateForeignKeyChanges($compareResult->foreignKeys, CompareResult::OPERATION_CREATE, true)
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
