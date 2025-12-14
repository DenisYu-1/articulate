<?php

namespace Articulate\Collection;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

class MappingCollection implements IteratorAggregate
{
    /**
     * @var MappingItem[]
     */
    private array $items = [];

    /**
     * @param MappingItem[] $items
     */
    public function __construct(iterable $items = [])
    {
        foreach ($items as $item) {
            $this->items[] = $item instanceof MappingItem ? $item : new MappingItem($item);
        }
    }

    /**
     * @return MappingItem[]
     */
    public function toArray(): array
    {
        return $this->items;
    }

    public function first(): ?MappingItem
    {
        return $this->items[0] ?? null;
    }

    public function pivotOf(object $entity): ?array
    {
        foreach ($this->items as $item) {
            if ($item->entity === $entity) {
                return $item->pivot;
            }
        }

        return null;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
