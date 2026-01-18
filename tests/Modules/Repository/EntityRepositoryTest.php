<?php

namespace Articulate\Tests\Modules\Repository;

use Articulate\Connection;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\Repository\AbstractRepository;
use Articulate\Modules\Repository\EntityRepository;
use Articulate\Modules\Repository\RepositoryInterface;
use PHPUnit\Framework\TestCase;

class EntityRepositoryTest extends TestCase {
    private EntityManager $entityManager;

    private EntityRepository $repository;

    protected function setUp(): void
    {
        // Create a mock connection for testing
        $connection = $this->createMock(Connection::class);

        $this->entityManager = new EntityManager($connection);
        $this->repository = new EntityRepository($this->entityManager, TestEntity::class);
    }

    public function testRepositoryCreation(): void
    {
        $this->assertInstanceOf(EntityRepository::class, $this->repository);
        $this->assertInstanceOf(RepositoryInterface::class, $this->repository);
        $this->assertInstanceOf(AbstractRepository::class, $this->repository);
    }
}
