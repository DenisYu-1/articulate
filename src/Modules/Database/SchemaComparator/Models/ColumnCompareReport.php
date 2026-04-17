<?php

namespace Articulate\Modules\Database\SchemaComparator\Models;

readonly class ColumnCompareReport {
    /**
     * @param ColumnCompareResult[] $results
     * @param string[] $warnings Human-readable messages for NOT NULL / no-default columns not mapped in any entity
     */
    public function __construct(
        public array $results,
        public array $warnings,
    ) {
    }
}
