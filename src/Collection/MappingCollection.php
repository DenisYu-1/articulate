<?php

namespace Articulate\Collection;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

class MappingCollection implements IteratorAggregate, Countable {
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
            if ($item instanceof MappingItem) {
                $this->items[] = $item;
            } elseif (is_object($item)) {
                $this->items[] = new MappingItem($item);
            } else {
                throw new \InvalidArgumentException(sprintf(
                    'MappingCollection items must be objects, %s given',
                    get_debug_type($item)
                ));
            }
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

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
