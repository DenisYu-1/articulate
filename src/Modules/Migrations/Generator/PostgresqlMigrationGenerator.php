<?php

namespace Articulate\Modules\Migrations\Generator;

use Articulate\Modules\Database\PostgresqlTypeMapper;
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

        // First, handle foreign key deletions
        foreach ($compareResult->foreignKeys as $foreignKey) {
            if ($foreignKey->operation === CompareResult::OPERATION_DELETE) {
                $alterParts[] = $this->getDropForeignKeySyntax($foreignKey->name);
            }
        }

        // Then, handle index deletions
        foreach ($compareResult->indexes as $index) {
            if ($index->operation === CompareResult::OPERATION_DELETE) {
                $alterParts[] = $this->getDropIndexSyntax($index->name);
            }
        }

        // Then, handle column operations
        foreach ($compareResult->columns as $column) {
            if ($column->operation === CompareResult::OPERATION_DELETE) {
                $alterParts[] = 'DROP "' . $column->name . '"';

                continue;
            }
            $parts = [];
            if ($column->operation === CompareResult::OPERATION_CREATE) {
                $parts[] = 'ADD';
            } else {
                $parts[] = 'ALTER COLUMN';
            }
            $parts[] = $this->columnDefinition($column->name, $column->propertyData);
            $alterParts[] = implode(' ', $parts);
        }

        // Finally, handle additions: indexes then foreign keys
        foreach ($compareResult->indexes as $index) {
            if ($index->operation === CompareResult::OPERATION_CREATE) {
                $alterParts[] = $this->generateIndexSql($index, $compareResult->name);
            }
        }

        foreach ($compareResult->foreignKeys as $foreignKey) {
            if ($foreignKey->operation === CompareResult::OPERATION_CREATE) {
                $alterParts[] = $this->foreignKeyDefinition($foreignKey);
            }
        }

        if (empty($alterParts)) {
            return '';
        }

        return 'ALTER TABLE "' . $compareResult->name . '" ' . implode(', ', $alterParts);
    }

    protected function generateAlterTableRollback(TableCompareResult $compareResult): string
    {
        $alterParts = [];

        // First, handle foreign key deletions (for rollback of ADD FK operations)
        foreach ($compareResult->foreignKeys as $foreignKey) {
            if ($foreignKey->operation === CompareResult::OPERATION_CREATE) {
                $alterParts[] = $this->getDropForeignKeySyntax($foreignKey->name);
            }
        }

        // Then, handle index deletions (for rollback of ADD INDEX operations)
        foreach ($compareResult->indexes as $index) {
            if ($index->operation === CompareResult::OPERATION_CREATE) {
                $alterParts[] = $this->getDropIndexSyntax($index->name);
            }
        }

        // Then, handle column operations
        foreach ($compareResult->columns as $column) {
            if ($column->operation === CompareResult::OPERATION_CREATE) {
                $alterParts[] = 'DROP "' . $column->name . '"';

                continue;
            }
            $columnParts = [];
            if ($column->operation === CompareResult::OPERATION_DELETE) {
                $columnParts[] = 'ADD';
            } else {
                $columnParts[] = 'ALTER COLUMN';
            }
            $columnParts[] = $this->columnDefinition($column->name, $column->columnData);
            $alterParts[] = implode(' ', $columnParts);
        }

        // Finally, handle additions: indexes then foreign keys (for rollback of DROP operations)
        foreach ($compareResult->indexes as $index) {
            if ($index->operation === CompareResult::OPERATION_DELETE) {
                $alterParts[] = $this->generateIndexSql($index, $compareResult->name);
            }
        }

        foreach ($compareResult->foreignKeys as $foreignKey) {
            if ($foreignKey->operation === CompareResult::OPERATION_DELETE) {
                $alterParts[] = $this->foreignKeyDefinition($foreignKey);
            }
        }

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
}
