<?php

namespace Articulate\Modules\QueryBuilder;

use InvalidArgumentException;
use Psr\Cache\CacheItemPoolInterface;
use Throwable;

class QueryResultCache {
    private ?CacheItemPoolInterface $cachePool;

    private ?int $lifetime = null;

    private ?string $cacheId = null;

    public function __construct(?CacheItemPoolInterface $cachePool = null)
    {
        $this->cachePool = $cachePool;
    }

    public function enable(int $lifetime, ?string $cacheId = null): void
    {
        if ($this->cachePool === null) {
            throw new InvalidArgumentException('Result cache is not configured. Set CacheItemPoolInterface in EntityManager constructor.');
        }

        if ($lifetime <= 0) {
            throw new InvalidArgumentException('Cache lifetime must be a positive integer (seconds).');
        }

        $this->lifetime = $lifetime;
        $this->cacheId = $cacheId;
    }

    public function disable(): void
    {
        $this->lifetime = null;
        $this->cacheId = null;
    }

    public function isEnabled(): bool
    {
        return $this->cachePool !== null && $this->lifetime !== null;
    }

    public function getCacheId(): ?string
    {
        return $this->cacheId;
    }

    public function getLifetime(): ?int
    {
        return $this->lifetime;
    }

    public function getCachePool(): ?CacheItemPoolInterface
    {
        return $this->cachePool;
    }

    public function get(string $cacheKey): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        try {
            $cacheItem = $this->cachePool->getItem($cacheKey);

            if ($cacheItem->isHit()) {
                return $cacheItem->get();
            }
        } catch (Throwable $e) {
        }

        return null;
    }

    public function set(string $cacheKey, array $data): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        try {
            $cacheItem = $this->cachePool->getItem($cacheKey);
            $cacheItem->set($data);
            $cacheItem->expiresAfter($this->lifetime);
            $this->cachePool->save($cacheItem);
        } catch (Throwable $e) {
        }
    }

    public function generateCacheKey(
        ?string $entityClass,
        string $sql,
        array $params,
        bool $distinct,
        ?int $limit,
        ?int $offset,
        array $orderBy,
        array $groupBy,
        array $having
    ): string {
        $cacheData = [
            'sql' => $sql,
            'params' => $this->normalizeParamsForCacheKey($params),
            'entityClass' => $entityClass,
            'distinct' => $distinct,
            'limit' => $limit,
            'offset' => $offset,
            'orderBy' => $orderBy,
            'groupBy' => $groupBy,
            'having' => $having,
        ];

        return hash('sha256', json_encode($cacheData));
    }

    private function normalizeParamsForCacheKey(array $params): array
    {
        return array_map(function ($value) {
            if (is_object($value)) {
                return 'object:' . get_class($value) . ':' . spl_object_id($value);
            }
            if (is_resource($value)) {
                return 'resource:' . get_resource_type($value);
            }
            if (is_array($value)) {
                return $this->normalizeParamsForCacheKey($value);
            }

            return $value;
        }, $params);
    }
}
