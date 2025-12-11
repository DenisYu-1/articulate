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
            if (!empty($compareResult->primaryColumns)) {
                $parts[] = 'PRIMARY KEY (`' . implode('`, `', $compareResult->primaryColumns) . '`)';
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

        if (empty($alterParts)) {
            return '';
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
            $parts = [implode(', ', $columns)];
            if (!empty($compareResult->primaryColumns)) {
                $parts[] = 'PRIMARY KEY (`' . implode('`, `', $compareResult->primaryColumns) . '`)';
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
        foreach ($compareResult->columns as $column) {
            if ($column->operation === CompareResult::OPERATION_CREATE) {
                $alterParts[] = 'DROP COLUMN ' . $column->name;
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

        foreach ($compareResult->indexes as $index) {
            if ($index->operation === CompareResult::OPERATION_CREATE) {
                $alterParts[] = 'DROP INDEX `' . $index->name . '`';
            } elseif ($index->operation === CompareResult::OPERATION_DELETE) {
                $alterParts[] = $this->generateIndexSql($index, $compareResult->name);
            }
        }

        foreach ($compareResult->foreignKeys as $foreignKey) {
            if ($foreignKey->operation === CompareResult::OPERATION_CREATE) {
                $alterParts[] = 'DROP FOREIGN KEY `' . $foreignKey->name . '`';
            } elseif ($foreignKey->operation === CompareResult::OPERATION_DELETE) {
                $alterParts[] = $this->foreignKeyDefinition($foreignKey);
            }
        }

        if (empty($alterParts)) {
            return '';
        }

        return 'ALTER TABLE `' . $compareResult->name . '` ' . implode(', ', $alterParts);
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
        $parts = [];
        $parts[] = $name;
        $parts[] = $this->mapTypeLength($column);
        if (!$column->isNullable) {
            $parts[] = 'NOT NULL';
        }
        if ($column->defaultValue !== null) {
            $parts[] = 'DEFAULT "' . $column->defaultValue . '"';
        }
        return implode(' ', $parts);
    }

    private function generateIndexSql(IndexCompareResult $index, string $tableName): string
    {
        $indexType = $index->isUnique ? 'UNIQUE' : '';
        $columns = implode(', ', array_map(fn($col) => '`' . $col . '`', $index->columns));

        return sprintf(
            'ADD %s INDEX `%s` (%s)',
            $indexType,
            $index->name,
            $columns
        );
    }

    private function foreignKeyDefinition(ForeignKeyCompareResult $foreignKey, bool $withAdd = true): string
    {
        $template = $withAdd
            ? 'ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s`(`%s`)' 
            : 'CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s`(`%s`)';

        return sprintf(
            $template,
            $foreignKey->name,
            $foreignKey->column,
            $foreignKey->referencedTable,
            $foreignKey->referencedColumn,
        );
    }
}
