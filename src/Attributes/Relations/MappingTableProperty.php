<?php

namespace Articulate\Attributes\Relations;

class MappingTableProperty
{
    public function __construct(
        public readonly string $name,
        public readonly string $type = 'string',
        public readonly bool $nullable = false,
        public readonly ?int $length = null,
        public readonly ?string $defaultValue = null,
    ) {
    }
}
