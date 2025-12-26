<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Connection;
use Articulate\Modules\EntityManager\DeferredImplicitStrategy;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\EntityManager\EntityState;
use Articulate\Modules\EntityManager\UnitOfWork;
use Articulate\Modules\QueryBuilder\QueryBuilder;
use PHPUnit\Framework\TestCase;

class EntityManagerTest extends TestCase
{
    private EntityManager $entityManager;

    protected function setUp(): void
    {
        // Create a mock connection for testing
        $connection = $this->createMock(Connection::class);

        $this->entityManager = new EntityManager($connection);
    }

    public function testEntityManagerCreation(): void
    {
        $this->assertInstanceOf(EntityManager::class, $this->entityManager);
        $this->assertInstanceOf(UnitOfWork::class, $this->entityManager->getUnitOfWork());
    }

    public function testCreateUnitOfWork(): void
    {
        $unitOfWork = $this->entityManager->createUnitOfWork();

        $this->assertInstanceOf(UnitOfWork::class, $unitOfWork);
        $this->assertNotSame($this->entityManager->getUnitOfWork(), $unitOfWork);
    }

    public function testPersistAndFlush(): void
    {
        $entity = new class() {
            public int $id = 1;

            public string $name = 'test';
        };

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $unitOfWork = $this->entityManager->getUnitOfWork();
        $this->assertEquals(EntityState::MANAGED, $unitOfWork->getEntityState($entity));
    }

    public function testRemoveAndFlush(): void
    {
        $entity = new class() {
            public int $id = 1;
        };

        $this->entityManager->persist($entity);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();

        $unitOfWork = $this->entityManager->getUnitOfWork();
        $this->assertEquals(EntityState::REMOVED, $unitOfWork->getEntityState($entity));
    }

    public function testClear(): void
    {
        $entity = new class() {
            public int $id = 1;
        };

        $this->entityManager->persist($entity);

        $unitOfWork = $this->entityManager->getUnitOfWork();
        $this->assertEquals(EntityState::MANAGED, $unitOfWork->getEntityState($entity));

        $this->entityManager->clear();

        $this->assertEquals(EntityState::NEW, $unitOfWork->getEntityState($entity));
    }

    public function testFindReturnsNull(): void
    {
        // Since we don't have database connection or hydration implemented yet,
        // find should return null
        $result = $this->entityManager->find('TestEntity', 1);

        $this->assertNull($result);
    }

    public function testFindAllReturnsEmptyArray(): void
    {
        // Since we don't have database connection implemented yet,
        // findAll should return empty array
        $result = $this->entityManager->findAll('TestEntity');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetReferenceThrowsException(): void
    {
        // Since getReference is not implemented yet, it should throw an exception
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('getReference not yet implemented');

        $this->entityManager->getReference('TestEntity', 1);
    }

    public function testRefreshThrowsException(): void
    {
        // Since refresh is not implemented yet, it should throw an exception
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('refresh not yet implemented');

        $entity = new class() {
            public int $id = 1;
        };

        $this->entityManager->refresh($entity);
    }

    public function testTransactionalExecution(): void
    {
        $executed = false;
        $result = null;

        $callbackResult = $this->entityManager->transactional(function (EntityManager $em) use (&$executed, &$result) {
            $executed = true;
            $result = 'callback executed';

            return $result;
        });

        $this->assertTrue($executed);
        $this->assertEquals('callback executed', $callbackResult);
        $this->assertEquals('callback executed', $result);
    }

    public function testTransactionalRollbackOnException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Test exception');

        $this->entityManager->transactional(function (EntityManager $em) {
            throw new \Exception('Test exception');
        });
    }

    public function testBeginTransaction(): void
    {
        // Should not throw an exception
        $this->entityManager->beginTransaction();
        $this->assertTrue(true);
    }

    public function testCommit(): void
    {
        $this->entityManager->beginTransaction();

        // Should not throw an exception
        $this->entityManager->commit();
        $this->assertTrue(true);
    }

    public function testRollback(): void
    {
        $this->entityManager->beginTransaction();

        // Should not throw an exception
        $this->entityManager->rollback();
        $this->assertTrue(true);
    }

    public function testMultipleUnitOfWorks(): void
    {
        $entity1 = new class() {
            public int $id = 1;

            public string $name = 'entity1';
        };

        $entity2 = new class() {
            public int $id = 2;

            public string $name = 'entity2';
        };

        // Create two different UnitOfWork instances
        $uow1 = $this->entityManager->createUnitOfWork();
        $uow2 = $this->entityManager->createUnitOfWork();

        // Persist entities in different UnitOfWork instances
        $uow1->persist($entity1);
        $uow2->persist($entity2);

        // Check that entities are managed in their respective UnitOfWork instances
        $this->assertEquals(EntityState::MANAGED, $uow1->getEntityState($entity1));
        $this->assertEquals(EntityState::MANAGED, $uow2->getEntityState($entity2));

        // Check that default UnitOfWork doesn't have these entities
        $defaultUow = $this->entityManager->getUnitOfWork();
        $this->assertEquals(EntityState::NEW, $defaultUow->getEntityState($entity1));
        $this->assertEquals(EntityState::NEW, $defaultUow->getEntityState($entity2));
    }

    public function testFlushAllUnitOfWorks(): void
    {
        $entity = new class() {
            public int $id = 1;
        };

        // Create a scoped UnitOfWork and persist an entity
        $scopedUow = $this->entityManager->createUnitOfWork();
        $scopedUow->persist($entity);

        // Flush should commit all UnitOfWork instances
        $this->entityManager->flush();

        // The entity should still be managed in its UnitOfWork
        $this->assertEquals(EntityState::MANAGED, $scopedUow->getEntityState($entity));
    }

    public function testClearAllUnitOfWorks(): void
    {
        $entity = new class() {
            public int $id = 1;
        };

        // Create a scoped UnitOfWork and persist an entity
        $scopedUow = $this->entityManager->createUnitOfWork();
        $scopedUow->persist($entity);

        $this->assertEquals(EntityState::MANAGED, $scopedUow->getEntityState($entity));

        // Clear should reset all UnitOfWork instances
        $this->entityManager->clear();

        // The scoped UnitOfWork should be gone, and default should be reset
        $newDefaultUow = $this->entityManager->getUnitOfWork();
        $this->assertEquals(EntityState::NEW, $newDefaultUow->getEntityState($entity));
        $this->assertNotSame($scopedUow, $newDefaultUow);
    }

    public function testCustomChangeTrackingStrategy(): void
    {
        $customStrategy = new DeferredImplicitStrategy();
        $connection = $this->createMock(Connection::class);

        $em = new EntityManager($connection, $customStrategy);

        $this->assertInstanceOf(EntityManager::class, $em);

        $entity = new class() {
            public int $id = 1;
        };

        $em->persist($entity);
        $em->flush();

        $this->assertTrue(true);
    }

    public function testHydratorAccess(): void
    {
        $hydrator = $this->entityManager->getHydrator();
        $this->assertInstanceOf(\Articulate\Modules\EntityManager\HydratorInterface::class, $hydrator);

        // Test setting a custom hydrator
        $customHydrator = $this->createMock(\Articulate\Modules\EntityManager\HydratorInterface::class);
        $this->entityManager->setHydrator($customHydrator);

        $this->assertSame($customHydrator, $this->entityManager->getHydrator());
    }

    public function testCreateQueryBuilder(): void
    {
        $qb = $this->entityManager->createQueryBuilder();

        $this->assertInstanceOf(QueryBuilder::class, $qb);

        // Test that the query builder can build a simple query
        $sql = $qb->select('id', 'name')->from('users')->getSQL();
        $this->assertEquals('SELECT id, name FROM users', $sql);
    }

    public function testCreateQueryBuilderWithEntityClass(): void
    {
        $qb = $this->entityManager->createQueryBuilder('User');

        $this->assertInstanceOf(QueryBuilder::class, $qb);
        $this->assertEquals('User', $qb->getEntityClass());

        // Should have table automatically resolved
        $sql = $qb->getSQL();
        $this->assertStringContainsString('FROM users', $sql);
    }

    public function testGetQueryBuilder(): void
    {
        $qb = $this->entityManager->getQueryBuilder();

        $this->assertInstanceOf(QueryBuilder::class, $qb);

        // Should return the same instance
        $qb2 = $this->entityManager->getQueryBuilder();
        $this->assertSame($qb, $qb2);
    }

    public function testQueryBuilderWithHydrator(): void
    {
        $qb = $this->entityManager->createQueryBuilder();

        // QueryBuilder should have hydrator set
        $hydrator = $qb->getHydrator();
        $this->assertInstanceOf(\Articulate\Modules\EntityManager\HydratorInterface::class, $hydrator);

        // Main query builder should also have hydrator
        $mainQb = $this->entityManager->getQueryBuilder();
        $this->assertSame($hydrator, $mainQb->getHydrator());
    }
}
