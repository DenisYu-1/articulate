<?php

namespace Articulate\Modules\EntityManager;

use InvalidArgumentException;
use Psr\Cache\CacheItemPoolInterface;

class SecondLevelCache {
    public function __construct(
        private readonly CacheItemPoolInterface $pool,
        private readonly int $defaultTtl = 3600,
    ) {
    }

    public function get(string $class, mixed $id): ?array
    {
        try {
            $item = $this->pool->getItem($this->generateKey($class, $id));

            return $item->isHit() ? $item->get() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function set(string $class, mixed $id, array $data): void
    {
        try {
            $item = $this->pool->getItem($this->generateKey($class, $id));
            $item->set($data)->expiresAfter($this->defaultTtl);
            $this->pool->save($item);
        } catch (\Throwable) {
            // Cache failure never breaks query execution
        }
    }

    public function evict(string $class, mixed $id): void
    {
        try {
            $this->pool->deleteItem($this->generateKey($class, $id));
        } catch (\Throwable) {
            // Cache failure never breaks query execution
        }
    }

    public function generateKey(string $class, mixed $id): string
    {
        return hash('sha256', $class . ':' . $this->normalizeId($id));
    }

    /**
     * Produce a deterministic, type-tagged string for an identifier so distinct
     * IDs never collide on the same key (e.g. int 1 vs string "1", or two value
     * objects that JSON-encode to the same shape). Composite keys (arrays) are
     * normalized recursively in a stable order.
     */
    private function normalizeId(mixed $id): string
    {
        if (is_int($id) || is_string($id)) {
            return (is_int($id) ? 'i:' : 's:') . $id;
        }

        if (is_bool($id)) {
            return 'b:' . ($id ? '1' : '0');
        }

        if (is_float($id)) {
            return 'd:' . $id;
        }

        if ($id instanceof \Stringable) {
            return 'o:' . $id::class . ':' . $id;
        }

        if (is_array($id)) {
            ksort($id);
            $parts = [];
            foreach ($id as $key => $value) {
                $parts[] = $key . '=' . $this->normalizeId($value);
            }

            return 'a:[' . implode(',', $parts) . ']';
        }

        throw new InvalidArgumentException(
            'Second-level cache identifier must be scalar, Stringable, or array; got ' . get_debug_type($id)
        );
    }
}
