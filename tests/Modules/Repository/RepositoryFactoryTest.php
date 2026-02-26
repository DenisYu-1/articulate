<?php

namespace Articulate\Tests\Modules\Repository;

use Articulate\Connection;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\Repository\EntityRepository;
use Articulate\Modules\Repository\RepositoryFactory;
use PHPUnit\Framework\TestCase;

class RepositoryFactoryTest extends TestCase {
    private EntityManager $entityManager;

    private RepositoryFactory $factory;

    protected function setUp(): void
    {
        // Create a mock connection for testing
        $connection = $this->createMock(Connection::class);

        $this->entityManager = new EntityManager($connection);
        $this->factory = new RepositoryFactory($this->entityManager);
    }

    public function testFactoryCreation(): void
    {
        $this->assertInstanceOf(RepositoryFactory::class, $this->factory);
    }

    public function testGetRepositoryReturnsEntityRepositoryByDefault(): void
    {
        $repository = $this->factory->getRepository(TestEntity::class);
        $this->assertInstanceOf(EntityRepository::class, $repository);
    }

    public function testGetRepositoryCachesInstances(): void
    {
        $repository1 = $this->factory->getRepository(TestEntity::class);
        $repository2 = $this->factory->getRepository(TestEntity::class);

        $this->assertSame($repository1, $repository2);
    }

    public function testClearCache(): void
    {
        $repository1 = $this->factory->getRepository(TestEntity::class);
        $this->factory->clearCache();
        $repository2 = $this->factory->getRepository(TestEntity::class);

        $this->assertNotSame($repository1, $repository2);
    }

    public function testGetCachedRepositories(): void
    {
        $repository = $this->factory->getRepository(TestEntity::class);
        $cached = $this->factory->getCachedRepositories();

        $this->assertArrayHasKey(TestEntity::class, $cached);
        $this->assertSame($repository, $cached[TestEntity::class]);
    }
}
