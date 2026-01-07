<?php

namespace Articulate\Modules\Migrations\Generator;

use Articulate\Modules\Database\MySqlTypeMapper;
use Articulate\Modules\Database\SchemaComparator\Models\CompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\ForeignKeyCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\IndexCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\PropertiesData;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;

class MySqlMigrationGenerator extends AbstractMigrationGenerator implements MigrationGeneratorInterface {
    public function __construct(
        MySqlTypeMapper $typeRegistry
    ) {
        parent::__construct($typeRegistry);
    }

    public function getIdentifierQuote(): string
    {
        return '`';
    }

    public function generate(TableCompareResult $compareResult): string
    {
        if ($compareResult->operation === CompareResult::OPERATION_DELETE) {
            return $this->generateDropTable($compareResult->name);
        }

        if ($compareResult->operation === CompareResult::OPERATION_CREATE) {
            return $this->generateCreateTable($compareResult);
        }

        return $this->generateAlterTable($compareResult);
    }

    public function rollback(TableCompareResult $compareResult): string
    {
        if ($compareResult->operation === TableCompareResult::OPERATION_CREATE) {
            return $this->generateDropTable($compareResult->name);
        }

        if ($compareResult->operation === TableCompareResult::OPERATION_DELETE) {
            return $this->generateCreateTableFromRollback($compareResult);
        }

        return $this->generateAlterTableRollback($compareResult);
    }

    protected function generateDropTable(string $tableName): string
    {
        return 'DROP TABLE `' . $tableName . '`';
    }

    protected function columnDefinition(string $name, PropertiesData $column): string
    {
        $quote = $this->getIdentifierQuote();
        $parts = [];
        $parts[] = $quote . $name . $quote;
        $parts[] = $this->mapTypeLength($column);

        // Handle primary key generation strategies
        if ($column->isPrimaryKey && $column->generatorType) {
            $parts[] = $this->getPrimaryKeyGenerationSql($column->generatorType, $column->sequence);
        } elseif ($column->isAutoIncrement) {
            $parts[] = $this->getAutoIncrementSql();
        }

        if (!$column->isNullable) {
            $parts[] = 'NOT NULL';
        }
        if ($column->defaultValue !== null) {
            $parts[] = 'DEFAULT "' . $column->defaultValue . '"';
        }

        return implode(' ', $parts);
    }

    protected function foreignKeyDefinition(ForeignKeyCompareResult $foreignKey, bool $withAdd = true): string
    {
        $quote = $this->getIdentifierQuote();
        $template = 'CONSTRAINT ' . $quote . '%s' . $quote . ' FOREIGN KEY (' . $quote . '%s' . $quote . ') REFERENCES ' . $quote . '%s' . $quote . '(' . $quote . '%s' . $quote . ')';
        if ($withAdd) {
            $template = 'ADD ' . $template;
        }

        return sprintf(
            $template,
            $foreignKey->name,
            $foreignKey->column,
            $foreignKey->referencedTable,
            $foreignKey->referencedColumn,
        );
    }

    protected function generateIndexSql(IndexCompareResult $index, string $tableName, bool $withAdd = true): string
    {
        $quote = $this->getIdentifierQuote();
        $indexType = $index->isUnique ? 'UNIQUE ' : '';
        $columns = implode(', ', array_map(fn ($col) => $quote . $col . $quote, $index->columns));

        $addPrefix = $withAdd ? 'ADD ' : '';

        return sprintf(
            '%s%sINDEX %s%s%s (%s)',
            $addPrefix,
            $indexType,
            $quote,
            $index->name,
            $quote,
            $columns
        );
    }

    protected function mapTypeLength(?PropertiesData $propertyData): string
    {
        if (!$propertyData->type) {
            return 'TEXT'; // fallback for unknown types
        }

        $dbType = $this->typeRegistry->getDatabaseType($propertyData->type);

        // Handle string types with length
        if ($propertyData->type === 'string' && $propertyData->length) {
            return 'VARCHAR(' . $propertyData->length . ')';
        }

        return $dbType;
    }

    protected function generateCreateTable(TableCompareResult $compareResult): string
    {
        $query = 'CREATE TABLE `' . $compareResult->name . '` (';
        $columns = [];
        foreach ($compareResult->columns as $column) {
            $columns[] = $this->columnDefinition($column->name, $column->propertyData);
        }
        $parts = [implode(', ', $columns)];
        if (!empty($compareResult->primaryColumns)) {
            $quotedColumns = array_map(fn ($col) => '`' . $col . '`', $compareResult->primaryColumns);
            $parts[] = 'PRIMARY KEY (' . implode(', ', $quotedColumns) . ')';
        }
        foreach ($compareResult->indexes as $index) {
            if ($index->operation !== CompareResult::OPERATION_CREATE) {
                continue;
            }
            $parts[] = $this->generateIndexSql($index, $compareResult->name, false);
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
                $alterParts[] = 'DROP `' . $column->name . '`';

                continue;
            }
            $parts = [];
            if ($column->operation === CompareResult::OPERATION_CREATE) {
                $parts[] = 'ADD';
            } else {
                $parts[] = 'MODIFY';
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

        $algorithm = $this->shouldUseOnlineDDL($compareResult) ? ' ALGORITHM=INPLACE' : '';

        return 'ALTER TABLE `' . $compareResult->name . '`' . $algorithm . ' ' . implode(', ', $alterParts);
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
                $alterParts[] = 'DROP `' . $column->name . '`';

                continue;
            }
            $columnParts = [];
            if ($column->operation === CompareResult::OPERATION_DELETE) {
                $columnParts[] = 'ADD';
            } else {
                $columnParts[] = 'MODIFY';
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

        return 'ALTER TABLE `' . $compareResult->name . '` ' . implode(', ', $alterParts);
    }

    protected function generateCreateTableFromRollback(TableCompareResult $compareResult): string
    {
        $query = 'CREATE TABLE `' . $compareResult->name . '` (';
        $columns = [];
        foreach ($compareResult->columns as $column) {
            $columns[] = $this->columnDefinition($column->name, $column->columnData);
        }
        $parts = [implode(', ', $columns)];
        if (!empty($compareResult->primaryColumns)) {
            $quotedColumns = array_map(fn ($col) => '`' . $col . '`', $compareResult->primaryColumns);
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
            if ($index->operation === CompareResult::OPERATION_DELETE) {
                $query .= ', ' . $this->generateIndexSql($index, $compareResult->name);
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
        return 'DROP FOREIGN KEY `' . $constraintName . '`';
    }

    protected function getDropIndexSyntax(string $indexName): string
    {
        return 'DROP INDEX `' . $indexName . '`';
    }

    protected function shouldUseOnlineDDL(TableCompareResult $compareResult): bool
    {
        // Use online DDL when creating concurrent indexes
        foreach ($compareResult->indexes as $index) {
            if ($index->operation === CompareResult::OPERATION_CREATE && $index->isConcurrent) {
                return true;
            }
        }

        return false;
    }

    protected function getConcurrentIndexPrefix(): string
    {
        // MySQL doesn't have a direct CONCURRENTLY equivalent for CREATE INDEX
        // Online DDL is handled at the ALTER TABLE level with ALGORITHM=INPLACE
        return '';
    }

    protected function getPrimaryKeyGenerationSql(string $generatorType, ?string $sequence = null): string
    {
        return match ($generatorType) {
            'auto_increment', 'serial', 'bigserial' => 'AUTO_INCREMENT',
            default => '', // UUID, ULID, etc. don't need special SQL
        };
    }

    protected function getAutoIncrementSql(): string
    {
        return 'AUTO_INCREMENT';
    }

    protected function getModifyColumnSyntax(string $columnName, PropertiesData $column): string
    {
        return 'MODIFY `' . $columnName . '` ' . $this->columnDefinition($columnName, $column);
    }
}
