<?php

namespace Articulate\Attributes\Relations;

class MappingTable {
    /**
     * @param MappingTableProperty[] $properties
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly array $properties = [],
    ) {
    }
}
