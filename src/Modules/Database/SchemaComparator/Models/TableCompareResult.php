<?php

namespace Articulate\Modules\Database\SchemaComparator\Models;

class TableCompareResult extends CompareResult
{
    /**
     * @param ColumnCompareResult[] $columns
     * @param IndexCompareResult[] $indexes
     * @param ForeignKeyCompareResult[] $foreignKeys
     */
    public function __construct(
        string $name,
        string $operation,
        public array $columns = [],
        public array $indexes = [],
        public array $foreignKeys = [],
        public array $primaryColumns = [],
    ) {
        parent::__construct($name, $operation);
    }
}
