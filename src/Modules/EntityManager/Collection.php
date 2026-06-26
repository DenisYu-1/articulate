<?php

namespace Articulate\Modules\EntityManager;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * Collection class for managing OneToMany and ManyToMany relationships.
 * Provides array-like access with additional relationship management features.
 */
class Collection implements ArrayAccess, Countable, IteratorAggregate {
    protected array $items = [];

    protected bool $isDirty = false;

    /** @var object[] Items added since last markClean() — will be INSERTed on M2M sync */
    protected array $addedItems = [];

    /** @var object[] Items removed since last markClean() — will be DELETEd on M2M sync */
    protected array $removedItems = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
        $this->addedItems = $items;
    }

    /**
     * Add an item to the collection.
     */
    public function add(object $item): self
    {
        $this->items[] = $item;
        $this->addedItems[] = $item;
        $this->isDirty = true;

        return $this;
    }

    /**
     * Remove an item from the collection.
     */
    public function remove(object $item): self
    {
        $index = array_search($item, $this->items, true);
        if ($index !== false) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);
            $this->isDirty = true;

            $addedIdx = array_search($item, $this->addedItems, true);
            if ($addedIdx !== false) {
                array_splice($this->addedItems, $addedIdx, 1);
            } else {
                $this->removedItems[] = $item;
            }
        }

        return $this;
    }

    /**
     * Check if the collection contains an item.
     */
    public function contains(object $item): bool
    {
        return in_array($item, $this->items, true);
    }

    /**
     * Clear all items from the collection.
     */
    public function clear(): self
    {
        foreach ($this->items as $item) {
            $addedIdx = array_search($item, $this->addedItems, true);
            if ($addedIdx !== false) {
                array_splice($this->addedItems, $addedIdx, 1);
            } else {
                $this->removedItems[] = $item;
            }
        }
        $this->items = [];
        $this->isDirty = true;

        return $this;
    }

    /**
     * Get all items as array.
     */
    public function toArray(): array
    {
        return $this->items;
    }

    /**
     * Check if the collection has been modified.
     */
    public function isDirty(): bool
    {
        return $this->isDirty || !empty($this->addedItems) || !empty($this->removedItems);
    }

    /**
     * Mark the collection as clean (call after DB load or after flush).
     */
    public function markClean(): self
    {
        $this->isDirty = false;
        $this->addedItems = [];
        $this->removedItems = [];

        return $this;
    }

    /** @return object[] Items added since last markClean() */
    public function getAddedItems(): array
    {
        return $this->addedItems;
    }

    /** @return object[] Items removed since last markClean() */
    public function getRemovedItems(): array
    {
        return $this->removedItems;
    }

    /**
     * Filter the collection using a callback.
     */
    public function filter(callable $callback): self
    {
        return new self(array_filter($this->items, $callback));
    }

    /**
     * Map the collection using a callback.
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->items);
    }

    /**
     * Get the first item in the collection.
     */
    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }

    /**
     * Get the last item in the collection.
     */
    public function last(): mixed
    {
        if (empty($this->items)) {
            return null;
        }

        return end($this->items);
    }

    /**
     * Check if the collection is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Check if the collection is not empty.
     */
    public function isNotEmpty(): bool
    {
        return !empty($this->items);
    }

    // ArrayAccess implementation
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->add($value);
        } else {
            $this->items[$offset] = $value;
            $this->isDirty = true;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        if (array_key_exists($offset, $this->items)) {
            $this->remove($this->items[$offset]);
        }
    }

    // Countable implementation
    public function count(): int
    {
        return count($this->items);
    }

    // IteratorAggregate implementation
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }
}
