<?php

namespace Articulate\Modules\Database\SchemaReader;

use Articulate\Connection;
use Articulate\Exceptions\DatabaseSchemaException;
use Articulate\Modules\Database\PostgresqlTypeMapper;
use PDO;
use PDOException;

class PostgresqlSchemaReader implements DatabaseSchemaReaderInterface {
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return DatabaseColumn[]
     * @throws DatabaseSchemaException
     */
    public function getTableColumns(string $tableName): array
    {
        $result = [];

        try {
            $query = '
                SELECT
                    column_name,
                    data_type,
                    is_nullable,
                    column_default,
                    character_maximum_length,
                    numeric_precision,
                    numeric_scale
                FROM information_schema.columns
                WHERE table_name = $1
                  AND table_schema = current_schema()
                ORDER BY ordinal_position
            ';

            $stmt = $this->connection->executeQuery($query, [$tableName]);
            $typeRegistry = new PostgresqlTypeMapper();

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $columnInfo) {
                // Build the full type string for PostgreSQL
                $type = $this->buildPostgresqlTypeString($columnInfo);

                $result[] = new DatabaseColumn(
                    $columnInfo['column_name'],
                    $type,
                    $columnInfo['is_nullable'] === 'YES',
                    $this->normalizeDefaultValue($columnInfo['column_default']),
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
        $query = "
            SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = current_schema()
              AND table_type = 'BASE TABLE'
              AND table_name != 'migrations'
        ";

        $stmt = $this->connection->executeQuery($query);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getTableIndexes(string $tableName)
    {
        $query = '
            SELECT
                i.indexname as index_name,
                a.attname as column_name,
                ix.indisunique as is_unique,
                ix.indisprimary as is_primary
            FROM pg_indexes i
            JOIN pg_class c ON c.relname = i.indexname
            JOIN pg_index ix ON ix.indexrelid = c.oid
            JOIN pg_attribute a ON a.attrelid = ix.indrelid AND a.attnum = ANY(ix.indkey)
            WHERE i.tablename = $1
              AND i.schemaname = current_schema()
              AND NOT ix.indisprimary
            ORDER BY i.indexname, a.attnum
        ';

        $statement = $this->connection->executeQuery($query, [$tableName]);
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
        $query = '
            SELECT
                tc.constraint_name,
                kcu.column_name,
                ccu.table_name AS referenced_table_name,
                ccu.column_name AS referenced_column_name
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
              ON tc.constraint_name = kcu.constraint_name
              AND tc.table_schema = kcu.table_schema
            JOIN information_schema.constraint_column_usage ccu
              ON ccu.constraint_name = tc.constraint_name
              AND ccu.table_schema = tc.table_schema
            WHERE tc.constraint_type = \'FOREIGN KEY\'
              AND tc.table_name = $1
              AND tc.table_schema = current_schema()
        ';

        $statement = $this->connection->executeQuery($query, [$tableName]);
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

    private function buildPostgresqlTypeString(array $columnInfo): string
    {
        $type = $columnInfo['data_type'];

        // Handle special PostgreSQL types
        switch (strtoupper($type)) {
            case 'CHARACTER VARYING':
                $type = 'VARCHAR';

                break;
            case 'CHARACTER':
                $type = 'CHAR';

                break;
            case 'DOUBLE PRECISION':
                $type = 'DOUBLE';

                break;
            case 'TIMESTAMP WITHOUT TIME ZONE':
                $type = 'TIMESTAMP';

                break;
            case 'TIMESTAMP WITH TIME ZONE':
                $type = 'TIMESTAMPTZ';

                break;
        }

        // Add length/precision information where available
        if ($columnInfo['character_maximum_length']) {
            $type .= '(' . $columnInfo['character_maximum_length'] . ')';
        } elseif ($columnInfo['numeric_precision']) {
            $precision = $columnInfo['numeric_precision'];
            $scale = $columnInfo['numeric_scale'] ?? 0;
            $type .= "({$precision},{$scale})";
        }

        return $type;
    }

    private function normalizeDefaultValue(?string $defaultValue): ?string
    {
        if ($defaultValue === null) {
            return null;
        }

        // Remove PostgreSQL-specific default value wrappers
        // Examples: 'default_value'::text, nextval('sequence_name'::regclass)
        if (preg_match("/^'(.+)'::/", $defaultValue, $matches)) {
            return $matches[1];
        }

        // Handle nextval() for sequences
        if (preg_match("/nextval\('([^']+)'/", $defaultValue, $matches)) {
            return "nextval('{$matches[1]}')";
        }

        return $defaultValue;
    }

    private function normalizeIndexRow(array $row): array
    {
        $normalized = array_change_key_case($row, CASE_LOWER);
        $indexName = $normalized['index_name'] ?? null;
        $columnName = $normalized['column_name'] ?? null;
        $isUnique = ($normalized['is_unique'] ?? false) || ($normalized['is_primary'] ?? false);

        return [$indexName, $columnName, $isUnique];
    }

    private function normalizeForeignKeyRow(array $row): array
    {
        $normalized = array_change_key_case($row, CASE_LOWER);
        $name = $normalized['constraint_name'] ?? null;
        $column = $normalized['column_name'] ?? null;
        $referencedTable = $normalized['referenced_table_name'] ?? null;
        $referencedColumn = $normalized['referenced_column_name'] ?? null;

        return [$name, $column, $referencedTable, $referencedColumn];
    }
}
