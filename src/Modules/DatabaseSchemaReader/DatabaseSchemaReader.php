<?php

namespace Articulate\Modules\DatabaseSchemaReader;

use Articulate\Connection;
use PDO;
use PDOException;

class DatabaseSchemaReader {
    public function __construct(private readonly Connection $connection) {}

    /**
     * @return iterable<DatabaseColumn>
     */
    public function getTableColumns(string $tableName): array
    {
        $result = [];
        try {
            $stmt = $this->connection->executeQuery("SHOW COLUMNS FROM `$tableName`");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $columnInfo) {
                $result[] = new DatabaseColumn($columnInfo['Field'], $columnInfo['Type'], $columnInfo['Null'] !== 'NO', $columnInfo['Default'],);
            }
        } catch (PDOException) {}
        return $result;
    }

    public function getTables(): array
    {
        $stmt = $this->connection->executeQuery("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name != 'migrations'");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getTableIndexes(string $tableName)
    {
        $query = $this->buildQueryForIndexes($tableName);

        $statement = $this->connection->executeQuery($query);
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);

        $indexes = [];

        foreach ($results as $row) {
            [$indexName, $columnName, $isUnique] = $this->normalizeIndexRow($row);
            if ($indexName === null || $columnName === null) {
                continue;
            }

            if (!isset($indexes[$indexName])) {
                $indexes[$indexName] = [
                    'columns' => [],
                    'unique' => $isUnique,
                ];
            }
            $indexes[$indexName]['columns'][] = $columnName;
        }

        return $indexes;
    }

    public function getTableForeignKeys(string $tableName): array
    {
        $query = "
            SELECT
                constraint_name,
                column_name,
                referenced_table_name,
                referenced_column_name
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE table_schema = DATABASE()
              AND table_name = :table
              AND referenced_table_name IS NOT NULL
        ";

        $statement = $this->connection->executeQuery($query, ['table' => $tableName]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $foreignKeys = [];
        foreach ($rows as $row) {
            [$name, $column, $referencedTable, $referencedColumn] = $this->normalizeForeignKeyRow($row);
            if ($name === null || $column === null || $referencedTable === null || $referencedColumn === null) {
                continue;
            }
            $foreignKeys[$name] = [
                'column' => $column,
                'referencedTable' => $referencedTable,
                'referencedColumn' => $referencedColumn,
            ];
        }

        return $foreignKeys;
    }

    /**
     * Builds a query to fetch index information based on the database platform.
     *
     * @param string $tableName
     * @return string
     */
    private function buildQueryForIndexes(string $tableName): string
    {
        // Assuming MySQL for now, can adjust for PostgreSQL or SQLite
        return "SHOW INDEXES FROM `$tableName`";
    }

    private function normalizeIndexRow(array $row): array
    {
        $normalized = array_change_key_case($row, CASE_LOWER);
        $indexName = $normalized['index_name'] ?? $normalized['key_name'] ?? null;
        $columnName = $normalized['column_name'] ?? $normalized['column'] ?? $normalized['columnname'] ?? null;
        $isUnique = $this->isUniqueIndex($normalized);

        return [$indexName, $columnName, $isUnique];
    }

    private function normalizeForeignKeyRow(array $row): array
    {
        $normalized = array_change_key_case($row, CASE_LOWER);
        $name = $normalized['constraint_name'] ?? $normalized['name'] ?? null;
        $column = $normalized['column_name'] ?? $normalized['column'] ?? null;
        $referencedTable = $normalized['referenced_table_name'] ?? $normalized['referencedtable'] ?? null;
        $referencedColumn = $normalized['referenced_column_name'] ?? $normalized['referencedcolumn'] ?? null;

        return [$name, $column, $referencedTable, $referencedColumn];
    }

    private function isUniqueIndex(array $normalized): bool
    {
        if (array_key_exists('non_unique', $normalized)) {
            return !(bool) $normalized['non_unique'];
        }
        if (array_key_exists('unique', $normalized)) {
            return (bool) $normalized['unique'];
        }
        if (array_key_exists('indisunique', $normalized)) {
            return (bool) $normalized['indisunique'];
        }

        return false;
    }
}
