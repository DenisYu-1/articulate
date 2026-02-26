<?php

namespace Articulate\Tests\Modules\QueryBuilder;

use Articulate\Attributes\Entity;
use Articulate\Connection;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\QueryBuilder\QueryBuilder;
use Articulate\Tests\DatabaseTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

#[Entity]
class CacheTestEntity {
    public int $id;

    public string $name;
}

class ArrayCacheItem implements CacheItemInterface {
    private string $key;

    private mixed $value;

    private ?int $expiration = null;

    private bool $isHit = false;

    public function __construct(string $key)
    {
        $this->key = $key;
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
        if ($this->expiration !== null && $this->expiration < time()) {
            $this->isHit = false;

            return false;
        }

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
        $this->expiration = $expiration ? $expiration->getTimestamp() : null;

        return $this;
    }

    public function expiresAfter(int|\DateInterval|null $time): static
    {
        if ($time === null) {
            $this->expiration = null;
        } elseif (is_int($time)) {
            $this->expiration = time() + $time;
        } elseif ($time instanceof \DateInterval) {
            $this->expiration = (new \DateTime())->add($time)->getTimestamp();
        }

        return $this;
    }
}

class ArrayCache implements CacheItemPoolInterface {
    private array $items = [];

    public function getItem(string $key): CacheItemInterface
    {
        if (!isset($this->items[$key])) {
            $this->items[$key] = new ArrayCacheItem($key);
        }

        return $this->items[$key];
    }

    public function getItems(array $keys = []): iterable
    {
        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->getItem($key);
        }

        return $items;
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

class QueryBuilderCacheTest extends DatabaseTestCase {
    private QueryBuilder $qb;

    private Connection $connection;

    private ArrayCache $cache;

    private EntityManager $entityManager;

    #[DataProvider('databaseProvider')]
    public function testEnableResultCacheRequiresCachePool(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Result cache is not configured');

        $this->qb->enableResultCache(3600);
    }

    #[DataProvider('databaseProvider')]
    public function testEnableAndDisableResultCache(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->cache = new ArrayCache();
        $this->qb = new QueryBuilder($this->connection, null, null, $this->cache);

        // Set up a dummy table for testing
        $this->connection->executeQuery('DROP TABLE IF EXISTS test_cache_toggle');
        $this->connection->executeQuery('CREATE TABLE test_cache_toggle (id INT PRIMARY KEY)');

        $this->qb->from('test_cache_toggle')
            ->enableResultCache(3600, 'test_cache_id');
        $this->qb->disableResultCache();

        // After disabling cache, result should come from database not cache
        $result = $this->qb->getResult();
        $this->assertIsArray($result);
    }

    #[DataProvider('databaseProvider')]
    public function testCacheHit(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->cache = new ArrayCache();
        $this->entityManager = new EntityManager($this->connection, null, null, null, null, null, null, $this->cache);
        $this->qb = $this->entityManager->createQueryBuilder();

        $this->connection->executeQuery('DROP TABLE IF EXISTS test_cache_users');
        $this->connection->executeQuery('CREATE TABLE test_cache_users (id INT PRIMARY KEY, name VARCHAR(255))');
        $this->connection->executeQuery("INSERT INTO test_cache_users (id, name) VALUES (1, 'John')");
        $this->connection->executeQuery("INSERT INTO test_cache_users (id, name) VALUES (2, 'Jane')");

        $qb = $this->qb
            ->select('id', 'name')
            ->from('test_cache_users')
            ->where('id = ?', 1)
            ->enableResultCache(3600);

        $result1 = $qb->getResult();
        $this->assertCount(1, $result1);
        $this->assertEquals('John', $result1[0]['name']);

        $result2 = $qb->getResult();
        $this->assertCount(1, $result2);
        $this->assertEquals('John', $result2[0]['name']);
        $this->assertEquals($result1, $result2);
    }

    #[DataProvider('databaseProvider')]
    public function testCacheMiss(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->cache = new ArrayCache();
        $this->entityManager = new EntityManager($this->connection, null, null, null, null, null, null, $this->cache);
        $this->qb = $this->entityManager->createQueryBuilder();

        $this->connection->executeQuery('DROP TABLE IF EXISTS test_cache_miss');
        $this->connection->executeQuery('CREATE TABLE test_cache_miss (id INT PRIMARY KEY, name VARCHAR(255))');
        $this->connection->executeQuery("INSERT INTO test_cache_miss (id, name) VALUES (1, 'First')");

        $qb = $this->qb
            ->select('id', 'name')
            ->from('test_cache_miss')
            ->where('id = ?', 1)
            ->enableResultCache(3600);

        $result1 = $qb->getResult();
        $this->assertCount(1, $result1);
        $this->assertEquals('First', $result1[0]['name']);

        $this->connection->executeQuery("UPDATE test_cache_miss SET name = 'Updated' WHERE id = 1");

        $result2 = $qb->getResult();
        $this->assertCount(1, $result2);
        $this->assertEquals('First', $result2[0]['name']);
    }

    #[DataProvider('databaseProvider')]
    public function testCustomCacheId(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->cache = new ArrayCache();
        $this->entityManager = new EntityManager($this->connection, null, null, null, null, null, null, $this->cache);
        $this->qb = $this->entityManager->createQueryBuilder();

        $this->connection->executeQuery('DROP TABLE IF EXISTS test_custom_cache');
        $this->connection->executeQuery('CREATE TABLE test_custom_cache (id INT PRIMARY KEY, name VARCHAR(255))');
        $this->connection->executeQuery("INSERT INTO test_custom_cache (id, name) VALUES (1, 'Test')");

        $qb1 = $this->entityManager->createQueryBuilder()
            ->select('id', 'name')
            ->from('test_custom_cache')
            ->enableResultCache(3600, 'custom_key_1');

        $qb2 = $this->entityManager->createQueryBuilder()
            ->select('id', 'name')
            ->from('test_custom_cache')
            ->enableResultCache(3600, 'custom_key_2');

        $result1 = $qb1->getResult();
        $result2 = $qb2->getResult();

        $this->assertEquals($result1, $result2);
        $this->assertTrue($this->cache->hasItem('custom_key_1'));
        $this->assertTrue($this->cache->hasItem('custom_key_2'));
    }

    #[DataProvider('databaseProvider')]
    public function testAutoGeneratedCacheKey(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->cache = new ArrayCache();
        $this->entityManager = new EntityManager($this->connection, null, null, null, null, null, null, $this->cache);
        $this->qb = $this->entityManager->createQueryBuilder();

        $this->connection->executeQuery('DROP TABLE IF EXISTS test_auto_key');
        $this->connection->executeQuery('CREATE TABLE test_auto_key (id INT PRIMARY KEY, name VARCHAR(255))');
        $this->connection->executeQuery("INSERT INTO test_auto_key (id, name) VALUES (1, 'Test')");

        $qb1 = $this->qb
            ->select('id', 'name')
            ->from('test_auto_key')
            ->where('id = ?', 1)
            ->enableResultCache(3600);

        $qb2 = $this->qb
            ->select('id', 'name')
            ->from('test_auto_key')
            ->where('id = ?', 1)
            ->enableResultCache(3600);

        $result1 = $qb1->getResult();
        $result2 = $qb2->getResult();

        $this->assertEquals($result1, $result2);
    }

    #[DataProvider('databaseProvider')]
    public function testCacheWithDifferentParameters(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->cache = new ArrayCache();
        $this->entityManager = new EntityManager($this->connection, null, null, null, null, null, null, $this->cache);
        $this->qb = $this->entityManager->createQueryBuilder();

        $this->connection->executeQuery('DROP TABLE IF EXISTS test_diff_params');
        $this->connection->executeQuery('CREATE TABLE test_diff_params (id INT PRIMARY KEY, name VARCHAR(255))');
        $this->connection->executeQuery("INSERT INTO test_diff_params (id, name) VALUES (1, 'One')");
        $this->connection->executeQuery("INSERT INTO test_diff_params (id, name) VALUES (2, 'Two')");

        $qb1 = $this->entityManager->createQueryBuilder()
            ->select('id', 'name')
            ->from('test_diff_params')
            ->where('id = ?', 1)
            ->enableResultCache(3600);

        $qb2 = $this->entityManager->createQueryBuilder()
            ->select('id', 'name')
            ->from('test_diff_params')
            ->where('id = ?', 2)
            ->enableResultCache(3600);

        $result1 = $qb1->getResult();
        $result2 = $qb2->getResult();

        $this->assertCount(1, $result1);
        $this->assertCount(1, $result2);
        $this->assertEquals('One', $result1[0]['name']);
        $this->assertEquals('Two', $result2[0]['name']);
    }

    #[DataProvider('databaseProvider')]
    public function testCacheWithEntityHydration(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->cache = new ArrayCache();
        $this->entityManager = new EntityManager($this->connection, null, null, null, null, null, null, $this->cache);

        $this->connection->executeQuery('DROP TABLE IF EXISTS cache_test_entities');
        $this->connection->executeQuery('CREATE TABLE cache_test_entities (id INT PRIMARY KEY, name VARCHAR(255))');
        $this->connection->executeQuery("INSERT INTO cache_test_entities (id, name) VALUES (1, 'Cached Entity')");

        $qb = $this->entityManager->createQueryBuilder(CacheTestEntity::class);
        $qb->from('cache_test_entities');
        $qb->enableResultCache(3600);

        $result1 = $qb->getResult();
        $this->assertCount(1, $result1);
        $this->assertInstanceOf(CacheTestEntity::class, $result1[0]);
        $this->assertEquals('Cached Entity', $result1[0]->name);

        $result2 = $qb->getResult();
        $this->assertCount(1, $result2);
        $this->assertInstanceOf(CacheTestEntity::class, $result2[0]);
        $this->assertEquals('Cached Entity', $result2[0]->name);
    }

    #[DataProvider('databaseProvider')]
    public function testCacheExpiration(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->cache = new ArrayCache();
        $this->entityManager = new EntityManager($this->connection, null, null, null, null, null, null, $this->cache);
        $this->qb = $this->entityManager->createQueryBuilder();

        $this->connection->executeQuery('DROP TABLE IF EXISTS test_expiration');
        $this->connection->executeQuery('CREATE TABLE test_expiration (id INT PRIMARY KEY, name VARCHAR(255))');
        $this->connection->executeQuery("INSERT INTO test_expiration (id, name) VALUES (1, 'Original')");

        $qb = $this->qb
            ->select('id', 'name')
            ->from('test_expiration')
            ->where('id = ?', 1)
            ->enableResultCache(1);

        $result1 = $qb->getResult();
        $this->assertEquals('Original', $result1[0]['name']);

        sleep(2);

        $this->connection->executeQuery("UPDATE test_expiration SET name = 'Updated' WHERE id = 1");

        $result2 = $qb->getResult();
        $this->assertEquals('Updated', $result2[0]['name']);
    }

    #[DataProvider('databaseProvider')]
    public function testSubQueryBuilderDoesNotInheritCache(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->cache = new ArrayCache();
        $this->qb = new QueryBuilder($this->connection, null, null, $this->cache);

        $this->qb->enableResultCache(3600);
        $subQuery = $this->qb->createSubQueryBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Result cache is not configured');

        $subQuery->enableResultCache(3600);
    }

    #[DataProvider('databaseProvider')]
    public function testLockedQueriesAreNeverCached(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->cache = new ArrayCache();
        $this->entityManager = new EntityManager($this->connection, null, null, null, null, null, null, $this->cache);
        $this->qb = $this->entityManager->createQueryBuilder();

        $this->connection->executeQuery('DROP TABLE IF EXISTS test_locked_cache');
        $this->connection->executeQuery('CREATE TABLE test_locked_cache (id INT PRIMARY KEY, value VARCHAR(255))');
        $this->connection->executeQuery("INSERT INTO test_locked_cache (id, value) VALUES (1, 'original')");

        $this->connection->beginTransaction();

        $qb = $this->qb
            ->select('id', 'value')
            ->from('test_locked_cache')
            ->where('id = ?', 1)
            ->lock()
            ->enableResultCache(3600, 'locked_query_key');

        $result1 = $qb->getResult();
        $this->assertEquals('original', $result1[0]['value']);

        $this->assertFalse($this->cache->hasItem('locked_query_key'));

        $this->connection->executeQuery("UPDATE test_locked_cache SET value = 'updated' WHERE id = 1");

        $result2 = $qb->getResult();
        $this->assertEquals('updated', $result2[0]['value']);

        $this->connection->commit();
    }

    #[DataProvider('databaseProvider')]
    public function testGetSingleResultDoesNotMutateQueryBuilder(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->cache = new ArrayCache();
        $this->entityManager = new EntityManager($this->connection, null, null, null, null, null, null, $this->cache);
        $this->qb = $this->entityManager->createQueryBuilder();

        $this->connection->executeQuery('DROP TABLE IF EXISTS test_mutation');
        $this->connection->executeQuery('CREATE TABLE test_mutation (id INT PRIMARY KEY, name VARCHAR(255))');
        $this->connection->executeQuery("INSERT INTO test_mutation (id, name) VALUES (1, 'One')");
        $this->connection->executeQuery("INSERT INTO test_mutation (id, name) VALUES (2, 'Two')");

        $qb = $this->qb
            ->select('id', 'name')
            ->from('test_mutation')
            ->enableResultCache(3600);

        $single = $qb->getSingleResult();
        $this->assertEquals('One', $single['name']);

        $all = $qb->getResult();
        $this->assertCount(2, $all);
    }

    #[DataProvider('databaseProvider')]
    public function testInvalidLifetimeThrowsException(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->cache = new ArrayCache();
        $this->qb = new QueryBuilder($this->connection, null, null, $this->cache);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cache lifetime must be a positive integer');

        $this->qb->enableResultCache(0);
    }

    #[DataProvider('databaseProvider')]
    public function testNegativeLifetimeThrowsException(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->cache = new ArrayCache();
        $this->qb = new QueryBuilder($this->connection, null, null, $this->cache);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cache lifetime must be a positive integer');

        $this->qb->enableResultCache(-100);
    }
}
