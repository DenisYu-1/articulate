<?php

namespace Articulate\Modules\Database\SchemaReader;

use Articulate\Connection;
use Articulate\Exceptions\DatabaseSchemaException;
use Articulate\Modules\Database\SqliteTypeMapper;
use PDO;
use PDOException;

class SqliteSchemaReader implements DatabaseSchemaReaderInterface {
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return iterable<DatabaseColumn>
     * @throws DatabaseSchemaException
     */
    public function getTableColumns(string $tableName): array
    {
        $result = [];

        try {
            $stmt = $this->connection->executeQuery("PRAGMA table_info(`{$tableName}`)");
            $typeRegistry = new SqliteTypeMapper();

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $columnInfo) {
                $result[] = new DatabaseColumn(
                    $columnInfo['name'],
                    $columnInfo['type'],
                    !$columnInfo['notnull'],
                    $this->normalizeDefaultValue($columnInfo['dflt_value']),
                    $typeRegistry
                );
            }
        } catch (PDOException $e) {
            throw new DatabaseSchemaException(
                "Failed to retrieve columns for table '{$tableName}': " . $e->getMessage(),
                0,
                $e
            );
        }

        return $result;
    }

    public function getTables(): array
    {
        $stmt = $this->connection->executeQuery("SELECT name FROM sqlite_master WHERE type='table' AND name != 'migrations'");

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getTableIndexes(string $tableName)
    {
        $query = "SELECT name FROM sqlite_master WHERE type='index' AND tbl_name = ? AND name NOT LIKE 'sqlite_autoindex_%'";

        $statement = $this->connection->executeQuery($query, [$tableName]);
        $indexNames = $statement->fetchAll(PDO::FETCH_COLUMN);

        $indexes = [];

        foreach ($indexNames as $indexName) {
            $pragmaQuery = "PRAGMA index_info(`{$indexName}`)";
            $pragmaStmt = $this->connection->executeQuery($pragmaQuery);
            $columns = $pragmaStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($columns)) {
                continue;
            }

            // Check if index is unique
            $uniqueQuery = "PRAGMA index_list(`{$tableName}`)";
            $uniqueStmt = $this->connection->executeQuery($uniqueQuery);
            $indexList = $uniqueStmt->fetchAll(PDO::FETCH_ASSOC);

            $isUnique = false;
            foreach ($indexList as $indexInfo) {
                if ($indexInfo['name'] === $indexName) {
                    $isUnique = (bool) $indexInfo['unique'];
                    break;
                }
            }

            $indexes[$indexName] = [
                'columns' => array_column($columns, 'name'),
                'unique' => $isUnique,
            ];
        }

        return $indexes;
    }

    public function getTableForeignKeys(string $tableName): array
    {
        $statement = $this->connection->executeQuery("PRAGMA foreign_key_list(`{$tableName}`)");
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $foreignKeys = [];
        foreach ($rows as $row) {
            $constraintName = "fk_{$tableName}_{$row['from']}_{$row['table']}_{$row['to']}";

            $foreignKeys[$constraintName] = [
                'column' => $row['from'],
                'referencedTable' => $row['table'],
                'referencedColumn' => $row['to'],
            ];
        }

        return $foreignKeys;
    }

    private function normalizeDefaultValue(?string $defaultValue): ?string
    {
        if ($defaultValue === null) {
            return null;
        }

        // SQLite stores defaults with quotes, remove them for consistency
        if (preg_match("/^'(.+)'$/", $defaultValue, $matches)) {
            return $matches[1];
        }

        return $defaultValue;
    }
}
