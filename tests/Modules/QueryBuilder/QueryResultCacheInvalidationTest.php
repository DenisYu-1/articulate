<?php

namespace Articulate\Tests\Modules\QueryBuilder;

use Articulate\Connection;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\QueryBuilder\QueryResultCache;
use PHPUnit\Framework\TestCase;

class QueryResultCacheInvalidationTest extends TestCase {
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createStub(Connection::class);
    }

    public function testGenerationChangeMakesOldKeyStale(): void
    {
        $pool = new ArrayCache();
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
        $pool = new ArrayCache();
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

        // Simulate flush: increment generation in the shared pool via em1
        $this->callIncrement($em1);

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
        $pool = new ArrayCache();
        $em = new EntityManager($this->connection, null, null, null, null, null, null, $pool);

        $gen0 = $this->readGeneration($em->createQueryBuilder());
        $this->callIncrement($em);
        $gen1 = $this->readGeneration($em->createQueryBuilder());
        $this->callIncrement($em);
        $gen2 = $this->readGeneration($em->createQueryBuilder());

        $this->assertGreaterThan($gen0, $gen1);
        $this->assertGreaterThan($gen1, $gen2);
    }

    private function callIncrement(EntityManager $em): void
    {
        $ref = new \ReflectionClass($em);
        $method = $ref->getMethod('incrementQueryCacheGeneration');
        $method->setAccessible(true);
        $method->invoke($em);
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
