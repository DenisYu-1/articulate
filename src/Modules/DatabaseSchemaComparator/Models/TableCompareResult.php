<?php

namespace Articulate\Modules\DatabaseSchemaComparator\Models;

class TableCompareResult extends CompareResult{

    /**
     * @param ColumnCompareResult[] $columns
     * @param IndexCompareResult[] $indexes
     */
    public function __construct(
        string $name,
        string $operation,
        public array $columns = [],
        public array $indexes = [],
        public array $primaryColumns = [],
    ) {
        parent::__construct($name, $operation);
    }
}
