<?php

namespace Articulate\Modules\MigrationsGenerator;

use Articulate\Modules\DatabaseSchemaComparator\Models\CompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\ForeignKeyCompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\IndexCompareResult;
use Articulate\Modules\DatabaseSchemaComparator\Models\PropertiesData;
use Articulate\Modules\DatabaseSchemaComparator\Models\TableCompareResult;

class MigrationsCommandGenerator {

    public function generate(TableCompareResult $compareResult): string
    {
        if ($compareResult->operation === CompareResult::OPERATION_DELETE) {
            return 'DROP TABLE `' . $compareResult->name . '`';
        }

        if ($compareResult->operation === CompareResult::OPERATION_CREATE) {
            $query = 'CREATE TABLE `' . $compareResult->name . '` (';
            $columns = [];
            foreach ($compareResult->columns as $column) {
                $columns[] = $this->columnDefinition($column->name, $column->propertyData);
            }
            $parts = [implode(', ', $columns)];
            foreach ($compareResult->foreignKeys as $foreignKey) {
                if ($foreignKey->operation !== CompareResult::OPERATION_CREATE) {
                    continue;
                }
                $parts[] = $this->foreignKeyDefinition($foreignKey);
            }
            $query .= implode(', ', $parts) . ')';
            return $query;
        }

        $alterParts = [];
        foreach ($compareResult->columns as $column) {
            if ($column->operation === CompareResult::OPERATION_DELETE) {
                $alterParts[] = 'DROP ' . $column->name;
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

        // Handle Index modifications (ADD, DROP)
        foreach ($compareResult->indexes as $index) {
            if ($index->operation === CompareResult::OPERATION_CREATE) {
                $alterParts[] = $this->generateIndexSql($index, $compareResult->name);
            } elseif ($index->operation === CompareResult::OPERATION_DELETE) {
                $alterParts[] = 'DROP INDEX `' . $index->name . '`';
            }
        }

        foreach ($compareResult->foreignKeys as $foreignKey) {
            if ($foreignKey->operation === CompareResult::OPERATION_CREATE) {
                $alterParts[] = $this->foreignKeyDefinition($foreignKey);
            } elseif ($foreignKey->operation === CompareResult::OPERATION_DELETE) {
                $alterParts[] = 'DROP FOREIGN KEY `' . $foreignKey->name . '`';
            }
        }

        return 'ALTER TABLE `' . $compareResult->name . '` ' . implode(', ', $alterParts);
    }

    public function rollback(TableCompareResult $compareResult): string
    {
        if ($compareResult->operation === TableCompareResult::OPERATION_CREATE) {
            return 'DROP TABLE `' . $compareResult->name . '`';
        }

        if ($compareResult->operation === TableCompareResult::OPERATION_DELETE) {
            $query = 'CREATE TABLE `' . $compareResult->name . '` (';
            $columns = [];
            foreach ($compareResult->columns as $column) {
                $columns[] = $this->columnDefinition($column->name, $column->columnData);
            }
            $query .= implode(', ', $columns) . ')';
            foreach ($compareResult->indexes as $index) {
                $query .= ', ' . $this->generateIndexSql($index, $compareResult->name);
            }
            return $query;
        }

        $query = 'ALTER TABLE `' . $compareResult->name . '` ';
        $columns = [];
        foreach ($compareResult->columns as $column) {
            if ($column->operation === CompareResult::OPERATION_CREATE) {
                $columns[] = 'DROP COLUMN ' . $column->name;
                continue;
            }
            $parts = [];
            if ($column->operation === CompareResult::OPERATION_DELETE) {
                $parts[] = 'ADD';
            } else {
                $parts[] = 'MODIFY';
            }
            $parts[] = $this->columnDefinition($column->name, $column->columnData);
            $columns[] = implode(' ', $parts);
        }
        $query .= implode(', ', $columns);

        // Add index rollback (DROP for created indexes, ADD for deleted ones)
        $indexSqlParts = [];
        foreach ($compareResult->indexes as $index) {
            if ($index->operation === CompareResult::OPERATION_CREATE) {
                $indexSqlParts[] = 'DROP INDEX `' . $index->name . '`';
            } elseif ($index->operation === CompareResult::OPERATION_DELETE) {
                $indexSqlParts[] = $this->generateIndexSql($index, $compareResult->name);
            }
        }

        $foreignKeySqlParts = [];
        foreach ($compareResult->foreignKeys as $foreignKey) {
            if ($foreignKey->operation === CompareResult::OPERATION_CREATE) {
                $foreignKeySqlParts[] = 'DROP FOREIGN KEY `' . $foreignKey->name . '`';
            } elseif ($foreignKey->operation === CompareResult::OPERATION_DELETE) {
                $foreignKeySqlParts[] = $this->foreignKeyDefinition($foreignKey);
            }
        }

        $extraParts = array_filter([
            !empty($indexSqlParts) ? implode(', ', $indexSqlParts) : null,
            !empty($foreignKeySqlParts) ? implode(', ', $foreignKeySqlParts) : null,
        ]);

        if (!empty($extraParts)) {
            $query .= ', ' . implode(', ', $extraParts);
        }

        return $query;
    }

    private function mapTypeLength(?PropertiesData $propertyData): string
    {
        if ($propertyData->type === 'string') {
            return 'VARCHAR' . '('.($propertyData->length ?? '255').')';
        }
        return $propertyData->type;
    }

    private function columnDefinition($name, PropertiesData $column)
    {
        $parts[] = $name;
        $parts[] = $this->mapTypeLength($column);
        if (!$column->isNullable) {
            $parts[] = 'NOT NULL';
        }
        if ($column->defaultValue) {
            $parts[] = 'DEFAULT "' . $column->defaultValue . '"';
        }
        return implode(' ', $parts);
    }

    private function generateIndexSql(IndexCompareResult $index, string $tableName): string
    {
        // Assuming a generic index creation statement. Modify based on the type of index (e.g., UNIQUE, FULLTEXT)
        $indexType = $index->isUnique ? 'UNIQUE' : '';

        // Create the index definition
        $columns = implode(', ', array_map(fn($col) => '`' . $col . '`', $index->columns));

        return sprintf(
            'ADD %s INDEX `%s` (%s)',
            $indexType,
            $index->name,
            $columns
        );
    }

    private function foreignKeyDefinition(ForeignKeyCompareResult $foreignKey): string
    {
        return sprintf(
            'ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s`(`%s`)',
            $foreignKey->name,
            $foreignKey->column,
            $foreignKey->referencedTable,
            $foreignKey->referencedColumn,
        );
    }
}
