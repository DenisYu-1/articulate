<?php

namespace Articulate\Modules\EntityManager;

use ArrayIterator;

/**
 * A Collection that defers database loading until the content is actually needed.
 *
 * Optimisations over a plain Collection:
 *  - count() / isEmpty() / isNotEmpty() use a COUNT(*) query instead of fetching all rows.
 *  - add() / remove() before initialization are buffered and replayed on first full load,
 *    so unsaved objects can be appended without triggering a SELECT.
 */
class LazyCollection extends Collection {
    private bool $initialized = false;

    /** @var object[] Additions buffered before the collection is initialized */
    private array $pendingAdditions = [];

    /** @var object[] Removals buffered before the collection is initialized */
    private array $pendingRemovals = [];

    /**
     * @param \Closure     $loader       Returns array<object> on invocation — the full set of related entities.
     * @param \Closure|null $countLoader Returns int on invocation — COUNT(*) without fetching rows.
     */
    public function __construct(
        private \Closure $loader,
        private ?\Closure $countLoader = null,
    ) {
        parent::__construct([]);
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->items = ($this->loader)();

        // replay buffered removals
        foreach ($this->pendingRemovals as $item) {
            $idx = array_search($item, $this->items, true);
            if ($idx !== false) {
                unset($this->items[$idx]);
            }
        }
        $this->items = array_values($this->items);

        // replay buffered additions
        foreach ($this->pendingAdditions as $item) {
            $this->items[] = $item;
        }

        $this->pendingAdditions = [];
        $this->pendingRemovals  = [];
        $this->initialized      = true;
    }

    // ── count / empty checks — avoid full load when countLoader is available ──

    public function count(): int
    {
        if (!$this->initialized && $this->countLoader !== null) {
            return ($this->countLoader)()
                + count($this->pendingAdditions)
                - count($this->pendingRemovals);
        }

        $this->initialize();

        return parent::count();
    }

    public function isEmpty(): bool
    {
        if (!$this->initialized && $this->countLoader !== null) {
            return $this->count() === 0;
        }

        $this->initialize();

        return parent::isEmpty();
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    // ── mutations that can be buffered before initialization ──────────────────

    public function add(object $item): self
    {
        if (!$this->initialized) {
            $this->pendingAdditions[] = $item;
            $this->isDirty            = true;

            return $this;
        }

        parent::add($item);

        return $this;
    }

    public function remove(object $item): self
    {
        if (!$this->initialized) {
            $this->pendingRemovals[] = $item;
            $this->isDirty           = true;

            return $this;
        }

        parent::remove($item);

        return $this;
    }

    // ── everything else triggers full initialization ──────────────────────────

    public function toArray(): array
    {
        $this->initialize();

        return parent::toArray();
    }

    public function contains(object $item): bool
    {
        $this->initialize();

        return parent::contains($item);
    }

    public function first(): mixed
    {
        $this->initialize();

        return parent::first();
    }

    public function last(): mixed
    {
        $this->initialize();

        return parent::last();
    }

    public function filter(callable $callback): self
    {
        $this->initialize();
        $filtered = array_values(array_filter($this->items, $callback));

        return new self(static fn () => $filtered);
    }

    public function map(callable $callback): array
    {
        $this->initialize();

        return parent::map($callback);
    }

    public function getIterator(): ArrayIterator
    {
        $this->initialize();

        return parent::getIterator();
    }

    public function offsetExists(mixed $offset): bool
    {
        $this->initialize();

        return parent::offsetExists($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        $this->initialize();

        return parent::offsetGet($offset);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->initialize();
        parent::offsetUnset($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        // null offset = append, buffer without full load
        if (!$this->initialized && $offset === null) {
            $this->add($value);

            return;
        }

        $this->initialize();
        parent::offsetSet($offset, $value);
    }
}
