<?php

namespace Articulate\Tests\Modules\Repository\Integration;

use Articulate\Attributes\Entity;
use Articulate\Connection;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\Repository\AbstractRepository;
use Articulate\Modules\Repository\EntityRepository;
use Articulate\Modules\Repository\RepositoryInterface;
use PHPUnit\Framework\TestCase;

#[Entity(repositoryClass: CustomUserRepository::class)]
class UserWithCustomRepository {
    public int $id;

    public string $name;
}

#[Entity]
class UserWithoutRepository {
    public int $id;

    public string $email;
}

class CustomUserRepository extends AbstractRepository {
    public function findByName(string $name): ?UserWithCustomRepository
    {
        return $this->findOneBy(['name' => $name]);
    }
}

class EntityManagerRepositoryIntegrationTest extends TestCase {
    private EntityManager $entityManager;

    protected function setUp(): void
    {
        // Create a mock connection for testing
        $connection = $this->createMock(Connection::class);

        $this->entityManager = new EntityManager($connection);
    }

    public function testGetRepositoryReturnsRepositoryInterface(): void
    {
        $repository = $this->entityManager->getRepository(UserWithoutRepository::class);
        $this->assertInstanceOf(RepositoryInterface::class, $repository);
    }

    public function testGetRepositoryReturnsDefaultEntityRepositoryWhenNoCustomRepository(): void
    {
        $repository = $this->entityManager->getRepository(UserWithoutRepository::class);
        $this->assertInstanceOf(EntityRepository::class, $repository);
    }

    public function testGetRepositoryReturnsCustomRepositoryWhenSpecified(): void
    {
        $repository = $this->entityManager->getRepository(UserWithCustomRepository::class);
        $this->assertInstanceOf(CustomUserRepository::class, $repository);
    }

    public function testGetRepositoryCachesInstances(): void
    {
        $repository1 = $this->entityManager->getRepository(UserWithoutRepository::class);
        $repository2 = $this->entityManager->getRepository(UserWithoutRepository::class);

        $this->assertSame($repository1, $repository2);
    }

    public function testCustomRepositoryHasAccessToEntityManagerMethods(): void
    {
        $repository = $this->entityManager->getRepository(UserWithCustomRepository::class);
        $this->assertInstanceOf(CustomUserRepository::class, $repository);

        // Test that the custom method exists
        $this->assertTrue(method_exists($repository, 'findByName'));
    }
}
