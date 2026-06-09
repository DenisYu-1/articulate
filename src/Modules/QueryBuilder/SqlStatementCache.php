<?php

namespace Articulate\Modules\QueryBuilder;

use Psr\Cache\CacheItemPoolInterface;
use Throwable;

class SqlStatementCache {
    public function __construct(
        private readonly ?CacheItemPoolInterface $cachePool = null,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->cachePool !== null;
    }

    public function get(string $key): ?string
    {
        if ($this->cachePool === null) {
            return null;
        }

        try {
            $item = $this->cachePool->getItem($key);

            return $item->isHit() ? $item->get() : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function set(string $key, string $sql): void
    {
        if ($this->cachePool === null) {
            return;
        }

        try {
            $item = $this->cachePool->getItem($key);
            $item->set($sql);
            $this->cachePool->save($item);
        } catch (Throwable) {
        }
    }
}
