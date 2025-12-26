<?php

namespace Articulate\Modules\Database\SchemaComparator\Models;

class IndexCompareResult extends CompareResult
{
    public function __construct(
        string $name,
        string $operation,
        public readonly array $columns,
        public readonly bool $isUnique,
        public readonly bool $isConcurrent = false,
    ) {
        parent::__construct($name, $operation);
    }
}
