<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Modules\EntityManager\EntityCacheCoordinator;
use Articulate\Schema\EntityMetadataRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

class EntityCacheCoordinatorArrayItem implements CacheItemInterface {
    public function __construct(
        private string $key,
        private mixed $value = null,
        private bool $hit = false,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->hit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;
        $this->hit = true;

        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        return $this;
    }

    public function expiresAfter(\DateInterval|int|null $time): static
    {
        return $this;
    }
}

class EntityCacheCoordinatorArrayPool implements CacheItemPoolInterface {
    /** @var array<string, EntityCacheCoordinatorArrayItem> */
    private array $items = [];

    public function getItem(string $key): CacheItemInterface
    {
        return $this->items[$key] ?? new EntityCacheCoordinatorArrayItem($key);
    }

    public function getItems(array $keys = []): iterable
    {
        foreach ($keys as $key) {
            yield $key => $this->getItem($key);
        }
    }

    public function hasItem(string $key): bool
    {
        return isset($this->items[$key]);
    }

    public function clear(): bool
    {
        $this->items = [];

        return true;
    }

    public function deleteItem(string $key): bool
    {
        unset($this->items[$key]);

        return true;
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            unset($this->items[$key]);
        }

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        $this->items[$item->getKey()] = $item;

        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->save($item);
    }

    public function commit(): bool
    {
        return true;
    }
}

class EntityCacheCoordinatorTest extends TestCase {
    public function testSharedPoolGenerationSeenBySecondCoordinator(): void
    {
        $pool = new EntityCacheCoordinatorArrayPool();
        $coordinator1 = new EntityCacheCoordinator($pool, new EntityMetadataRegistry());
        $coordinator2 = new EntityCacheCoordinator($pool, new EntityMetadataRegistry());

        $this->assertSame(0, $coordinator1->readQueryCacheGeneration());
        $this->assertSame(0, $coordinator2->readQueryCacheGeneration());

        $coordinator1->incrementQueryCacheGeneration();

        $this->assertSame(1, $coordinator2->readQueryCacheGeneration());
    }

    public function testMultipleIncrementsAreMonotonic(): void
    {
        $pool = new EntityCacheCoordinatorArrayPool();
        $coordinator = new EntityCacheCoordinator($pool, new EntityMetadataRegistry());

        $gen0 = $coordinator->readQueryCacheGeneration();
        $coordinator->incrementQueryCacheGeneration();
        $gen1 = $coordinator->readQueryCacheGeneration();
        $coordinator->incrementQueryCacheGeneration();
        $gen2 = $coordinator->readQueryCacheGeneration();

        $this->assertGreaterThan($gen0, $gen1);
        $this->assertGreaterThan($gen1, $gen2);
    }
}
