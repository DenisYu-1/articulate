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
class Collection implements ArrayAccess, Countable, IteratorAggregate
{
    private array $items = [];

    private bool $isDirty = false;

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Add an item to the collection.
     */
    public function add(object $item): self
    {
        $this->items[] = $item;
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
            $this->items = array_values($this->items); // Re-index
            $this->isDirty = true;
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
        return $this->isDirty;
    }

    /**
     * Mark the collection as clean.
     */
    public function markClean(): self
    {
        $this->isDirty = false;

        return $this;
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
        return end($this->items) ?: null;
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
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
        $this->isDirty = true;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
        $this->items = array_values($this->items); // Re-index
        $this->isDirty = true;
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
