<?php

namespace Articulate\Modules\Database\SchemaComparator\Models;

class ForeignKeyCompareResult extends CompareResult {
    public function __construct(
        string $name,
        string $operation,
        public readonly string $column,
        public readonly string $referencedTable,
        public readonly string $referencedColumn = 'id',
        public readonly ?string $onDelete = null,
    ) {
        parent::__construct($name, $operation);
    }
}
