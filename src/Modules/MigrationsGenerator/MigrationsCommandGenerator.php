<?php

namespace Articulate\Modules\MigrationsGenerator;

use Articulate\Modules\DatabaseSchemaComparator\Models\CompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\ForeignKeyCompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\IndexCompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\PropertiesData;
use Articulate\Modules\DatabaseSchemaComparator\Models\TableCompareResult;

class MigrationsCommandGenerator
{
    public function __construct(
        private readonly string $databaseType = 'mysql'
    ) {}

    public function generate(TableCompareResult $compareResult): string
    {
        if ($compareResult->operation === CompareResult::OPERATION_DELETE) {
            $tableQuote = $this->getIdentifierQuote();
            return 'DROP TABLE ' . $tableQuote . $compareResult->name . $tableQuote;
        }

        if ($compareResult->operation === CompareResult::OPERATION_CREATE) {
            $tableQuote = $this->getIdentifierQuote();
            $query = 'CREATE TABLE ' . $tableQuote . $compareResult->name . $tableQuote . ' (';
            $columns = [];
            foreach ($compareResult->columns as $column) {
                $columns[] = $this->columnDefinition($column->name, $column->propertyData);
            }
            $parts = [implode(', ', $columns)];
            if (! empty($compareResult->primaryColumns)) {
                $quotedColumns = array_map(fn($col) => $tableQuote . $col . $tableQuote, $compareResult->primaryColumns);
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

        $alterParts = [];

        // For DROP operations, process foreign keys and indexes before columns
        // For ADD operations, process columns before indexes and foreign keys

        // First, handle foreign key deletions
        foreach ($compareResult->foreignKeys as $foreignKey) {
            if ($foreignKey->operation === CompareResult::OPERATION_DELETE) {
                $quote = $this->getIdentifierQuote();
                $dropType = $this->databaseType === 'mysql' ? 'FOREIGN KEY' : 'CONSTRAINT';
                $alterParts[] = 'DROP ' . $dropType . ' ' . $quote . $foreignKey->name . $quote;
            }
        }

        // Then, handle index deletions
        foreach ($compareResult->indexes as $index) {
            if ($index->operation === CompareResult::OPERATION_DELETE) {
                $quote = $this->getIdentifierQuote();
                $alterParts[] = 'DROP INDEX ' . $quote . $index->name . $quote;
            }
        }

        // Then, handle column operations
        foreach ($compareResult->columns as $column) {
            if ($column->operation === CompareResult::OPERATION_DELETE) {
                $quote = $this->getIdentifierQuote();
                $alterParts[] = 'DROP ' . $quote . $column->name . $quote;
                continue;
            }
            $parts = [];
            if ($column->operation === CompareResult::OPERATION_CREATE) {
                $parts[] = 'ADD';
            } else {
                $parts[] = $this->databaseType === 'mysql' ? 'MODIFY' : 'ALTER COLUMN';
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

        $tableQuote = $this->getIdentifierQuote();
        return 'ALTER TABLE ' . $tableQuote . $compareResult->name . $tableQuote . ' ' . implode(', ', $alterParts);
    }

    public function rollback(TableCompareResult $compareResult): string
    {
        if ($compareResult->operation === TableCompareResult::OPERATION_CREATE) {
            $tableQuote = $this->getIdentifierQuote();
            return 'DROP TABLE ' . $tableQuote . $compareResult->name . $tableQuote;
        }

        if ($compareResult->operation === TableCompareResult::OPERATION_DELETE) {
            $tableQuote = $this->getIdentifierQuote();
            $query = 'CREATE TABLE ' . $tableQuote . $compareResult->name . $tableQuote . ' (';
            $columns = [];
            foreach ($compareResult->columns as $column) {
                $columns[] = $this->columnDefinition($column->name, $column->columnData);
            }
            $parts = [implode(', ', $columns)];
            if (! empty($compareResult->primaryColumns)) {
                $quotedColumns = array_map(fn($col) => $tableQuote . $col . $tableQuote, $compareResult->primaryColumns);
                $parts[] = 'PRIMARY KEY (' . implode(', ', $quotedColumns) . ')';
            }
            foreach ($compareResult->foreignKeys as $foreignKey) {
                if ($foreignKey->operation !== CompareResult::OPERATION_DELETE) {
                    continue;
                }
                $parts[] = $this->foreignKeyDefinition($foreignKey, false);
            }
            $query .= implode(', ', $parts) . ')';
            foreach ($compareResult->indexes as $index) {
                $query .= ', ' . $this->generateIndexSql($index, $compareResult->name);
            }

            return $query;
        }

        $alterParts = [];

        // For rollback operations, we need to reverse the order of the forward migration
        // Since forward migration does: DROP FKs -> DROP indexes -> DROP columns -> ADD columns -> ADD indexes -> ADD FKs
        // Rollback should do: DROP FKs -> DROP indexes -> DROP columns -> ADD columns -> ADD indexes -> ADD FKs

        // First, handle foreign key deletions (for rollback of ADD FK operations)
        foreach ($compareResult->foreignKeys as $foreignKey) {
            if ($foreignKey->operation === CompareResult::OPERATION_CREATE) {
                $quote = $this->getIdentifierQuote();
                $dropType = $this->databaseType === 'mysql' ? 'FOREIGN KEY' : 'CONSTRAINT';
                $alterParts[] = 'DROP ' . $dropType . ' ' . $quote . $foreignKey->name . $quote;
            }
        }

        // Then, handle index deletions (for rollback of ADD INDEX operations)
        foreach ($compareResult->indexes as $index) {
            if ($index->operation === CompareResult::OPERATION_CREATE) {
                $quote = $this->getIdentifierQuote();
                $alterParts[] = 'DROP INDEX ' . $quote . $index->name . $quote;
            }
        }

        // Then, handle column operations
        foreach ($compareResult->columns as $column) {
            if ($column->operation === CompareResult::OPERATION_CREATE) {
                $quote = $this->getIdentifierQuote();
                $alterParts[] = 'DROP ' . $quote . $column->name . $quote;
                continue;
            }
            $columnParts = [];
            if ($column->operation === CompareResult::OPERATION_DELETE) {
                $columnParts[] = 'ADD';
            } else {
                $columnParts[] = $this->databaseType === 'mysql' ? 'MODIFY' : 'ALTER COLUMN';
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

        $tableQuote = $this->getIdentifierQuote();
        return 'ALTER TABLE ' . $tableQuote . $compareResult->name . $tableQuote . ' ' . implode(', ', $alterParts);
    }

    private function mapTypeLength(?PropertiesData $propertyData): string
    {
        if ($propertyData->type === 'string') {
            return 'VARCHAR' . '(' . ($propertyData->length ?? '255') . ')';
        }

        return $propertyData->type;
    }

    private function columnDefinition($name, PropertiesData $column)
    {
        $quote = $this->getIdentifierQuote();
        $parts = [];
        $parts[] = $quote . $name . $quote;
        $parts[] = $this->mapTypeLength($column);
        if (! $column->isNullable) {
            $parts[] = 'NOT NULL';
        }
        if ($column->defaultValue !== null) {
            $parts[] = 'DEFAULT "' . $column->defaultValue . '"';
        }

        return implode(' ', $parts);
    }

    private function generateIndexSql(IndexCompareResult $index, string $tableName): string
    {
        $quote = $this->getIdentifierQuote();
        $indexType = $index->isUnique ? 'UNIQUE ' : '';
        $columns = implode(', ', array_map(fn ($col) => $quote . $col . $quote, $index->columns));

        return sprintf(
            'ADD %sINDEX %s%s%s (%s)',
            $indexType,
            $quote,
            $index->name,
            $quote,
            $columns
        );
    }

    private function foreignKeyDefinition(ForeignKeyCompareResult $foreignKey, bool $withAdd = true): string
    {
        $quote = $this->getIdentifierQuote();
        $template = $withAdd
            ? 'ADD CONSTRAINT ' . $quote . '%s' . $quote . ' FOREIGN KEY (' . $quote . '%s' . $quote . ') REFERENCES ' . $quote . '%s' . $quote . '(' . $quote . '%s' . $quote . ')'
            : 'CONSTRAINT ' . $quote . '%s' . $quote . ' FOREIGN KEY (' . $quote . '%s' . $quote . ') REFERENCES ' . $quote . '%s' . $quote . '(' . $quote . '%s' . $quote . ')';

        return sprintf(
            $template,
            $foreignKey->name,
            $foreignKey->column,
            $foreignKey->referencedTable,
            $foreignKey->referencedColumn,
        );
    }

    private function getIdentifierQuote(): string
    {
        return match ($this->databaseType) {
            'mysql' => '`',
            'pgsql' => '"',
            'sqlite' => '"', // SQLite also uses double quotes for identifiers
            default => '`',
        };
    }
}
