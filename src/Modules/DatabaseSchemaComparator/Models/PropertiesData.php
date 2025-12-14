<?php

namespace Articulate\Modules\DatabaseSchemaComparator\Models;

class PropertiesData
{
    public function __construct(
        public readonly ?string $type = null,
        public readonly ?bool $isNullable = null,
        public readonly ?string $defaultValue = null,
        public readonly ?int $length = null,
    ) {
    }
}
