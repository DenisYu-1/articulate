<?php

namespace Norm\Modules\MigrationsGenerator;

use Norm\Modules\DatabaseSchemaComparator\Models\CompareResult;
use Norm\Modules\DatabaseSchemaComparator\Models\IndexCompareResult;
use Norm\Modules\DatabaseSchemaComparator\Models\PropertiesData;
use Norm\Modules\DatabaseSchemaComparator\Models\TableCompareResult;

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
            $query .= implode(', ', $columns) . ')';
            return $query;
        }

        $query = 'ALTER TABLE `' . $compareResult->name . '` ';
        $columns = [];
        foreach ($compareResult->columns as $column) {
            if ($column->operation === CompareResult::OPERATION_DELETE) {
                $columns[] = 'DROP ' . $column->name;
                continue;
            }
            $parts = [];
            if ($column->operation === CompareResult::OPERATION_CREATE) {
                $parts[] = 'ADD';
            } else {
                $parts[] = 'MODIFY';
            }
            $parts[] = $this->columnDefinition($column->name, $column->propertyData);
            $columns[] = implode(' ', $parts);
        }
        $query .= implode(', ', $columns);

        // Handle Index modifications (ADD, DROP)
        $indexSqlParts = [];
        foreach ($compareResult->indexes as $index) {
            if ($index->operation === CompareResult::OPERATION_CREATE) {
                $indexSqlParts[] = $this->generateIndexSql($index, $compareResult->name);
            } elseif ($index->operation === CompareResult::OPERATION_DELETE) {
                $indexSqlParts[] = 'DROP INDEX `' . $index->indexName . '`';
            }
        }

        // Combine column and index changes in the ALTER TABLE statement
        if (!empty($indexSqlParts)) {
            $query .= ', ' . implode(', ', $indexSqlParts);
        }

        return $query;
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

        if (!empty($indexSqlParts)) {
            $query .= ', ' . implode(', ', $indexSqlParts);
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
}
