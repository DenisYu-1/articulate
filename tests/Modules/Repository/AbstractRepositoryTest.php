<?php

namespace Articulate\Tests\Modules\Repository;

use Articulate\Attributes\Entity;
use Articulate\Connection;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\QueryBuilder\QueryBuilder;
use Articulate\Modules\Repository\AbstractRepository;
use PHPUnit\Framework\TestCase;

#[Entity]
class TestEntity {
    public int $id;

    public string $name;
}

class ConcreteRepository extends AbstractRepository {
    // Concrete implementation for testing
    public function getTestEntityClass(): string
    {
        return $this->entityClass;
    }

    public function getTestEntityManager(): EntityManager
    {
        return $this->entityManager;
    }

    public function createTestQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder();
    }
}

class AbstractRepositoryTest extends TestCase {
    private EntityManager $entityManager;

    private ConcreteRepository $repository;

    protected function setUp(): void
    {
        // Create a mock connection for testing
        $connection = $this->createMock(Connection::class);

        $this->entityManager = new EntityManager($connection);
        $this->repository = new ConcreteRepository($this->entityManager, TestEntity::class);
    }

    public function testRepositoryCreation(): void
    {
        $this->assertInstanceOf(ConcreteRepository::class, $this->repository);
        $this->assertInstanceOf(AbstractRepository::class, $this->repository);
    }

    public function testCreateQueryBuilder(): void
    {
        $qb = $this->repository->createTestQueryBuilder();
        $this->assertInstanceOf(QueryBuilder::class, $qb);
        $this->assertEquals(TestEntity::class, $qb->getEntityClass());
    }

    public function testGetEntityClass(): void
    {
        $this->assertEquals(TestEntity::class, $this->repository->getTestEntityClass());
    }

    public function testGetEntityManager(): void
    {
        $this->assertSame($this->entityManager, $this->repository->getTestEntityManager());
    }
}
