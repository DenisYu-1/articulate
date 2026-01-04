<?php

namespace Articulate\Modules\Database\SchemaComparator\Models;

class CompareResult {
    public const OPERATION_CREATE = 'create';

    public const OPERATION_UPDATE = 'update';

    public const OPERATION_DELETE = 'delete';

    public function __construct(
        public readonly string $name,
        public readonly string $operation,
    ) {
    }
}
