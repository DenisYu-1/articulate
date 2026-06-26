<?php

namespace Articulate\Tests\Modules\QueryBuilder;

use Articulate\Connection;
use Articulate\Modules\EntityManager\EntityCacheCoordinator;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\QueryBuilder\QueryResultCache;
use Articulate\Schema\EntityMetadataRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

class QueryResultInvalidationCacheItem implements CacheItemInterface {
    private mixed $value = null;

    private bool $isHit = false;

    public function __construct(
        private string $key,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->isHit ? $this->value : null;
    }

    public function isHit(): bool
    {
        return $this->isHit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;
        $this->isHit = true;

        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        return $this;
    }

    public function expiresAfter(int|\DateInterval|null $time): static
    {
        return $this;
    }
}

class QueryResultInvalidationCache implements CacheItemPoolInterface {
    /** @var array<string, QueryResultInvalidationCacheItem> */
    private array $items = [];

    public function getItem(string $key): CacheItemInterface
    {
        if (!isset($this->items[$key])) {
            $this->items[$key] = new QueryResultInvalidationCacheItem($key);
        }

        return $this->items[$key];
    }

    public function getItems(array $keys = []): iterable
    {
        foreach ($keys as $key) {
            yield $key => $this->getItem($key);
        }
    }

    public function hasItem(string $key): bool
    {
        return isset($this->items[$key]) && $this->items[$key]->isHit();
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

class QueryResultCacheInvalidationTest extends TestCase {
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createStub(Connection::class);
    }

    public function testGenerationChangeMakesOldKeyStale(): void
    {
        $pool = new QueryResultInvalidationCache();
        $cache = new QueryResultCache($pool);
        $cache->enable(3600);

        $key0 = $cache->generateCacheKey(null, 'SELECT 1', [], false, null, null, [], [], []);
        $cache->set($key0, [['id' => 1]]);
        $this->assertNotNull($cache->get($key0), 'Gen 0 key should be a cache hit');

        $cache->setGeneration(1);
        $key1 = $cache->generateCacheKey(null, 'SELECT 1', [], false, null, null, [], [], []);

        $this->assertNotSame($key0, $key1, 'Different generations must produce different cache keys');
        $this->assertNull($cache->get($key1), 'Gen 1 key should be a cache miss (old entry unreachable)');
    }

    public function testSharedPoolGenerationSeenBySecondEntityManager(): void
    {
        $pool = new QueryResultInvalidationCache();
        $em1 = new EntityManager($this->connection, null, null, null, null, null, null, $pool);
        $em2 = new EntityManager($this->connection, null, null, null, null, null, null, $pool);

        // Both EMs start at generation 0
        $qb1Before = $em1->createQueryBuilder();
        $qb2Before = $em2->createQueryBuilder();
        $this->assertSame(
            $this->readGeneration($qb1Before),
            $this->readGeneration($qb2Before),
            'Both EMs start at the same generation'
        );

        // Simulate flush: increment generation in the shared pool.
        (new EntityCacheCoordinator($pool, new EntityMetadataRegistry()))->incrementQueryCacheGeneration();

        // em2 must now read the incremented generation
        $qb2After = $em2->createQueryBuilder();
        $this->assertGreaterThan(
            $this->readGeneration($qb2Before),
            $this->readGeneration($qb2After),
            'em2 must observe the generation increment written by em1'
        );
    }

    public function testMultipleFlushesIncrementGenerationMonotonically(): void
    {
        $pool = new QueryResultInvalidationCache();
        $em = new EntityManager($this->connection, null, null, null, null, null, null, $pool);

        $gen0 = $this->readGeneration($em->createQueryBuilder());
        (new EntityCacheCoordinator($pool, new EntityMetadataRegistry()))->incrementQueryCacheGeneration();
        $gen1 = $this->readGeneration($em->createQueryBuilder());
        (new EntityCacheCoordinator($pool, new EntityMetadataRegistry()))->incrementQueryCacheGeneration();
        $gen2 = $this->readGeneration($em->createQueryBuilder());

        $this->assertGreaterThan($gen0, $gen1);
        $this->assertGreaterThan($gen1, $gen2);
    }

    private function readGeneration(object $queryBuilder): int
    {
        $ref = new \ReflectionClass($queryBuilder);
        $prop = $ref->getProperty('resultCache');
        $prop->setAccessible(true);
        $qrc = $prop->getValue($queryBuilder);

        $genProp = (new \ReflectionClass($qrc))->getProperty('generation');
        $genProp->setAccessible(true);

        return $genProp->getValue($qrc);
    }
}
