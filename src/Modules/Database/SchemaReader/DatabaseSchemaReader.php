<?php

namespace Articulate\Modules\Database\SchemaReader;

use Articulate\Connection;

/**
 * Factory-based DatabaseSchemaReader that delegates to database-specific implementations.
 *
 * @deprecated Use SchemaReaderFactory::create() instead to get the appropriate implementation.
 */
class DatabaseSchemaReader implements DatabaseSchemaReaderInterface {
    private DatabaseSchemaReaderInterface $implementation;

    public function __construct(Connection $connection)
    {
        try {
            $this->implementation = SchemaReaderFactory::create($connection);
        } catch (\InvalidArgumentException $e) {
            // Fallback to MySQL implementation for backward compatibility during transition
            $this->implementation = new MySqlSchemaReader($connection);
        }
    }

    /**
     * @return iterable<DatabaseColumn>
     */
    public function getTableColumns(string $tableName): array
    {
        return $this->implementation->getTableColumns($tableName);
    }

    public function getTables(): array
    {
        return $this->implementation->getTables();
    }

    public function getTableIndexes(string $tableName)
    {
        return $this->implementation->getTableIndexes($tableName);
    }

    public function getTableForeignKeys(string $tableName): array
    {
        return $this->implementation->getTableForeignKeys($tableName);
    }
}
