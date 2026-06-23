<?php

namespace Articulate\Collection;

class MappingItem {
    private array $pivot;

    private array $originalPivot;

    private bool $isNew;

    public function __construct(
        public readonly object $entity,
        array $pivot = [],
    ) {
        $this->pivot = $pivot;
        $this->originalPivot = $pivot;
        $this->isNew = true;
    }

    public static function fromDatabase(object $entity, array $pivot): self
    {
        $item = new self($entity, $pivot);
        $item->isNew = false;

        return $item;
    }

    public function pivot(): array
    {
        return $this->pivot;
    }

    public function pivotValue(string $name, mixed $default = null): mixed
    {
        return array_key_exists($name, $this->pivot) ? $this->pivot[$name] : $default;
    }

    public function setPivotValue(string $key, mixed $value): self
    {
        $this->pivot[$key] = $value;

        return $this;
    }

    /** @return array<string, mixed> Only keys whose value changed since last markClean() */
    public function getPivotChanges(): array
    {
        $changes = [];
        foreach ($this->pivot as $key => $value) {
            if (!array_key_exists($key, $this->originalPivot) || $this->originalPivot[$key] !== $value) {
                $changes[$key] = $value;
            }
        }

        return $changes;
    }

    public function isDirty(): bool
    {
        return !empty($this->getPivotChanges());
    }

    public function isNew(): bool
    {
        return $this->isNew;
    }

    public function markClean(): void
    {
        $this->originalPivot = $this->pivot;
    }

    public function markPersisted(): void
    {
        $this->isNew = false;
    }
}
