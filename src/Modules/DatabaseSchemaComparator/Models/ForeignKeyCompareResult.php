<?php

namespace Articulate\Modules\DatabaseSchemaComparator\Models;

class ForeignKeyCompareResult extends CompareResult
{
    public function __construct(
        string $name,
        string $operation,
        public readonly string $column,
        public readonly string $referencedTable,
        public readonly string $referencedColumn = 'id',
    ) {
        parent::__construct($name, $operation);
    }
}

