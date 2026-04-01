<?php

namespace Articulate\Schema;

use Articulate\Utils\StringUtils;

class SchemaNaming {
    public function relationColumn(string $propertyName): string
    {
        return StringUtils::snakeCase($propertyName) . '_id';
    }

    public function foreignKeyName(string $table, string $referencedTable, string $column): string
    {
        return sprintf('fk_%s_%s_%s', $table, $referencedTable, $column);
    }

    public function mappingTableName(string $ownerTable, string $targetTable): string
    {
        $parts = [$ownerTable, $targetTable];
        sort($parts, SORT_STRING);

        return StringUtils::snakeCase(implode('_', $parts));
    }
}
