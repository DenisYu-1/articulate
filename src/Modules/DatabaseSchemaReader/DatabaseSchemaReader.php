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
        $results = $statement->fetchAll();

        $indexes = [];

        foreach ($results as $row) {
            $indexName = $row['index_name'];
            $columnName = $row['column_name'];

            if (!isset($indexes[$indexName])) {
                $indexes[$indexName] = [];
            }
            $indexes[$indexName][] = $columnName;
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
            $foreignKeys[$row['constraint_name']] = [
                'column' => $row['column_name'],
                'referencedTable' => $row['referenced_table_name'],
                'referencedColumn' => $row['referenced_column_name'],
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
}
