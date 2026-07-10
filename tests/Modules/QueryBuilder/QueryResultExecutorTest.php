<?php

namespace Articulate\Tests\Modules\QueryBuilder;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Connection;
use Articulate\Exceptions\TransactionRequiredException;
use Articulate\Modules\QueryBuilder\QueryResultCache;
use Articulate\Modules\QueryBuilder\QueryResultExecutor;
use Articulate\Schema\EntityMetadataRegistry;
use Articulate\Schema\HydratorInterface;
use Articulate\Schema\ManagedEntityStoreInterface;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

class QueryResultExecutorTest extends TestCase {
    private Connection $connection;

    private QueryResultCache $resultCache;

    private QueryResultExecutor $executor;

    protected function setUp(): void
    {
        $this->connection = $this->createStub(Connection::class);
        $this->resultCache = new QueryResultCache();
        $this->executor = new QueryResultExecutor($this->connection, $this->resultCache);
    }

    private function execute(
        string $sql = 'SELECT 1',
        array $params = [],
        ?string $entityClass = null,
        bool $lockForUpdate = false,
    ): mixed {
        return $this->executor->execute($sql, $params, $entityClass, $lockForUpdate, false, null, null, [], [], []);
    }

    private function stubQueryReturning(array $rows): void
    {
        $statement = $this->createStub(PDOStatement::class);
        $statement->method('fetchAll')->willReturn($rows);

        $this->connection->method('inTransaction')->willReturn(false);
        $this->connection->method('executeQuery')->willReturn($statement);
    }

    public function testThrowsTransactionRequiredExceptionWhenLockingOutsideTransaction(): void
    {
        $this->connection->method('inTransaction')->willReturn(false);

        $this->expectException(TransactionRequiredException::class);

        $this->execute(lockForUpdate: true);
    }

    public function testReturnsEmptyArrayWhenQueryYieldsNoResults(): void
    {
        $this->stubQueryReturning([]);

        $this->assertSame([], $this->execute());
    }

    public function testReturnsRawRowsWhenNoHydratorConfigured(): void
    {
        $rows = [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']];

        $this->stubQueryReturning($rows);

        $this->assertSame($rows, $this->execute());
    }

    public function testLockForUpdateSucceedsInsideActiveTransaction(): void
    {
        $rows = [['id' => 1]];
        $statement = $this->createStub(PDOStatement::class);
        $statement->method('fetchAll')->willReturn($rows);

        $this->connection->method('inTransaction')->willReturn(true);
        $this->connection->method('executeQuery')->willReturn($statement);

        $this->assertSame($rows, $this->execute(lockForUpdate: true));
    }

    public function testExpandsArrayParameterIntoMultiplePlaceholdersForInClause(): void
    {
        $rows = [['id' => 2]];
        $statement = $this->createStub(PDOStatement::class);
        $statement->method('fetchAll')->willReturn($rows);

        $connection = $this->createMock(Connection::class);
        $connection->method('inTransaction')->willReturn(false);
        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT * FROM t WHERE id IN (?,?,?)', [1, 2, 3])
            ->willReturn($statement);

        $executor = new QueryResultExecutor($connection, $this->resultCache);
        $executor->execute('SELECT * FROM t WHERE id IN (?)', [[1, 2, 3]], null, false, false, null, null, [], [], []);
    }

    public function testMixedScalarAndArrayParamsAreExpandedCorrectly(): void
    {
        $rows = [['id' => 1]];
        $statement = $this->createStub(PDOStatement::class);
        $statement->method('fetchAll')->willReturn($rows);

        $connection = $this->createMock(Connection::class);
        $connection->method('inTransaction')->willReturn(false);
        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT * FROM t WHERE status = ? AND id IN (?,?)', ['active', 10, 20])
            ->willReturn($statement);

        $executor = new QueryResultExecutor($connection, $this->resultCache);
        $executor->execute(
            'SELECT * FROM t WHERE status = ? AND id IN (?)',
            ['active', [10, 20]],
            null,
            false,
            false,
            null,
            null,
            [],
            [],
            []
        );
    }

    public function testReturnsCachedResultsWithoutHittingDatabase(): void
    {
        $cachedRows = [['id' => 99, 'name' => 'Cached']];

        $cacheItem = $this->createStub(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn($cachedRows);

        $cachePool = $this->createStub(CacheItemPoolInterface::class);
        $cachePool->method('getItem')->willReturn($cacheItem);

        $cache = new QueryResultCache($cachePool);
        $cache->enable(60, 'fixed-cache-key');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeQuery');

        $executor = new QueryResultExecutor($connection, $cache);
        $result = $executor->execute('SELECT 1', [], null, false, false, null, null, [], [], []);

        $this->assertSame($cachedRows, $result);
    }

    public function testStoresQueryResultsInCacheForSubsequentRequests(): void
    {
        $rows = [['id' => 1, 'name' => 'Alice']];
        $statement = $this->createStub(PDOStatement::class);
        $statement->method('fetchAll')->willReturn($rows);

        $connection = $this->createMock(Connection::class);
        $connection->method('inTransaction')->willReturn(false);
        $connection->expects($this->once())->method('executeQuery')->willReturn($statement);

        $storedData = null;
        $cacheItem = $this->createStub(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturnCallback(function () use (&$storedData) {
            return $storedData !== null;
        });
        $cacheItem->method('get')->willReturnCallback(function () use (&$storedData) {
            return $storedData;
        });
        $cacheItem->method('set')->willReturnCallback(function ($value) use (&$storedData, $cacheItem) {
            $storedData = $value;

            return $cacheItem;
        });
        $cacheItem->method('expiresAfter')->willReturn($cacheItem);

        $cachePool = $this->createStub(CacheItemPoolInterface::class);
        $cachePool->method('getItem')->willReturn($cacheItem);
        $cachePool->method('save')->willReturn(true);

        $cache = new QueryResultCache($cachePool);
        $cache->enable(60);

        $executor = new QueryResultExecutor($connection, $cache);

        $firstResult = $executor->execute('SELECT 1', [], null, false, false, null, null, [], [], []);
        $secondResult = $executor->execute('SELECT 1', [], null, false, false, null, null, [], [], []);

        $this->assertSame($rows, $firstResult);
        $this->assertSame($rows, $secondResult);
    }

    public function testReusesManagedEntityInsteadOfHydratingAgain(): void
    {
        $managedEntity = new QueryResultExecutorTestEntity();
        $managedEntity->id = 1;
        $managedEntity->name = 'Alice';

        $hydrator = $this->createMock(HydratorInterface::class);
        $hydrator->expects($this->never())->method('hydrate');

        $managedEntityStore = $this->createMock(ManagedEntityStoreInterface::class);
        $managedEntityStore->expects($this->once())
            ->method('tryGetById')
            ->with(QueryResultExecutorTestEntity::class, 1)
            ->willReturn($managedEntity);
        $managedEntityStore->expects($this->never())->method('registerManaged');

        $this->stubQueryReturning([['id' => 1, 'name' => 'Alice']]);

        $executor = new QueryResultExecutor(
            $this->connection,
            $this->resultCache,
            $hydrator,
            $managedEntityStore,
            new EntityMetadataRegistry()
        );

        $result = $executor->execute(
            'SELECT id, name FROM query_result_executor_test_entities',
            [],
            QueryResultExecutorTestEntity::class,
            false,
            false,
            null,
            null,
            [],
            [],
            []
        );

        $this->assertSame([$managedEntity], $result);
    }

    public function testRegistersHydratedEntityInManagedEntityStore(): void
    {
        $hydratedEntity = new QueryResultExecutorTestEntity();
        $hydratedEntity->id = 2;
        $hydratedEntity->name = 'Bob';

        $hydrator = $this->createMock(HydratorInterface::class);
        $hydrator->expects($this->once())
            ->method('hydrate')
            ->with(QueryResultExecutorTestEntity::class, ['id' => 2, 'name' => 'Bob'])
            ->willReturn($hydratedEntity);

        $managedEntityStore = $this->createMock(ManagedEntityStoreInterface::class);
        $managedEntityStore->expects($this->once())
            ->method('tryGetById')
            ->with(QueryResultExecutorTestEntity::class, 2)
            ->willReturn(null);
        $managedEntityStore->expects($this->once())
            ->method('registerManaged')
            ->with($hydratedEntity, ['id' => 2, 'name' => 'Bob']);

        $this->stubQueryReturning([['id' => 2, 'name' => 'Bob']]);

        $executor = new QueryResultExecutor(
            $this->connection,
            $this->resultCache,
            $hydrator,
            $managedEntityStore,
            new EntityMetadataRegistry()
        );

        $result = $executor->execute(
            'SELECT id, name FROM query_result_executor_test_entities',
            [],
            QueryResultExecutorTestEntity::class,
            false,
            false,
            null,
            null,
            [],
            [],
            []
        );

        $this->assertSame([$hydratedEntity], $result);
    }
}

#[Entity]
class QueryResultExecutorTestEntity {
    #[PrimaryKey]
    public int $id;

    #[Property]
    public string $name;
}
