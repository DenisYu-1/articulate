<?php

namespace Articulate\Schema;

class SchemaNaming {
    private function snakeCase(string $name): string
    {
        return strtolower(preg_replace('/\B([A-Z])/', '_$1', $name));
    }

    public function relationColumn(string $propertyName): string
    {
        return $this->snakeCase($propertyName) . '_id';
    }

    public function foreignKeyName(string $table, string $referencedTable, string $column): string
    {
        return sprintf('fk_%s_%s_%s', $table, $referencedTable, $column);
    }

    public function mappingTableName(string $ownerTable, string $targetTable): string
    {
        $parts = [$ownerTable, $targetTable];
        sort($parts, SORT_STRING);

        return $this->snakeCase(implode('_', $parts));
    }
}
