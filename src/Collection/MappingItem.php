<?php

namespace Articulate\Collection;

class MappingItem {
    public function __construct(
        public readonly object $entity,
        public readonly array $pivot = [],
    ) {
    }

    public function pivot(): array
    {
        return $this->pivot;
    }

    public function pivotValue(string $name, mixed $default = null): mixed
    {
        return array_key_exists($name, $this->pivot) ? $this->pivot[$name] : $default;
    }
}
