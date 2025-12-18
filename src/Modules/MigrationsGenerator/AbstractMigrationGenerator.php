<?php

namespace Articulate\Modules\MigrationsGenerator;

use Articulate\Modules\DatabaseSchemaComparator\Models\CompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\ForeignKeyCompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\IndexCompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\PropertiesData;
use Articulate\Modules\DatabaseSchemaComparator\Models\TableCompareResult;
use Articulate\Utils\TypeRegistry;

abstract class AbstractMigrationGenerator implements MigrationGeneratorStrategy
{
    public function __construct(
        private readonly TypeRegistry $typeRegistry = new TypeRegistry()
    ) {
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

    abstract protected function generateDropTable(string $tableName): string;

    abstract protected function generateCreateTable(TableCompareResult $compareResult): string;

    abstract protected function generateAlterTable(TableCompareResult $compareResult): string;

    abstract protected function generateAlterTableRollback(TableCompareResult $compareResult): string;

    abstract protected function generateCreateTableFromRollback(TableCompareResult $compareResult): string;

    protected function columnDefinition(string $name, PropertiesData $column): string
    {
        $quote = $this->getIdentifierQuote();
        $parts = [];
        $parts[] = $quote . $name . $quote;
        $parts[] = $this->mapTypeLength($column);
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
        $template = $this->getForeignKeyKeyword() . ' ' . $quote . '%s' . $quote . ' FOREIGN KEY (' . $quote . '%s' . $quote . ') REFERENCES ' . $quote . '%s' . $quote . '(' . $quote . '%s' . $quote . ')';
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

    private function mapTypeLength(?PropertiesData $propertyData): string
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

    abstract protected function getForeignKeyKeyword(): string;

    abstract protected function getDropForeignKeySyntax(string $constraintName): string;

    abstract protected function getDropIndexSyntax(string $indexName): string;

    abstract protected function getModifyColumnSyntax(string $columnName, PropertiesData $column): string;
}
