<?php

namespace Articulate\Collection;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

class MappingCollection implements IteratorAggregate, Countable {
    /** @var MappingItem[] */
    private array $items = [];

    /** @var MappingItem[] Items removed since last markClean(), kept to generate DELETEs on flush */
    private array $removedItems = [];

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

    public function add(object $item): self
    {
        $this->items[] = $item instanceof MappingItem ? $item : new MappingItem($item);

        return $this;
    }

    public function remove(object $entity): self
    {
        $this->items = array_values(array_filter(
            $this->items,
            function (MappingItem $i) use ($entity): bool {
                if ($i->entity !== $entity) {
                    return true;
                }
                if (!$i->isNew()) {
                    $this->removedItems[] = $i;
                }

                return false;
            }
        ));

        return $this;
    }

    public function itemFor(object $entity): ?MappingItem
    {
        foreach ($this->items as $item) {
            if ($item->entity === $entity) {
                return $item;
            }
        }

        return null;
    }

    /** @return MappingItem[] Items added since last markClean() — will be INSERTed */
    public function getNewItems(): array
    {
        return array_values(array_filter($this->items, fn (MappingItem $i) => $i->isNew()));
    }

    /** @return MappingItem[] Existing items whose pivot data changed — will be UPDATEd */
    public function getDirtyItems(): array
    {
        return array_values(array_filter($this->items, fn (MappingItem $i) => !$i->isNew() && $i->isDirty()));
    }

    /** @return MappingItem[] Items removed since last markClean() — will be DELETEd */
    public function getRemovedItems(): array
    {
        return $this->removedItems;
    }

    public function isDirty(): bool
    {
        if (!empty($this->removedItems)) {
            return true;
        }
        foreach ($this->items as $item) {
            if ($item->isNew() || $item->isDirty()) {
                return true;
            }
        }

        return false;
    }

    public function markClean(): self
    {
        foreach ($this->items as $item) {
            $item->markClean();
            $item->markPersisted();
        }
        $this->removedItems = [];

        return $this;
    }

    public function pivotOf(object $entity): ?array
    {
        foreach ($this->items as $item) {
            if ($item->entity === $entity) {
                return $item->pivot();
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
