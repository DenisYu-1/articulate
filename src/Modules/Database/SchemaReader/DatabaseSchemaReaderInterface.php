<?php

namespace Articulate\Modules\Database\SchemaReader;

interface DatabaseSchemaReaderInterface {
    /**
     * @return DatabaseColumn[]
     */
    public function getTableColumns(string $tableName): array;

    public function getTables(): array;

    public function getTableIndexes(string $tableName);

    public function getTableForeignKeys(string $tableName): array;
}
