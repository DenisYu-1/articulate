<?php

namespace Articulate\Tests\Modules\Repository\Criteria;

use Articulate\Attributes\Entity;
use Articulate\Connection;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\Repository\AbstractRepository;
use Articulate\Modules\Repository\Criteria\EqualsCriteria;
use Articulate\Modules\Repository\Criteria\GreaterThanCriteria;
use Articulate\Modules\Repository\Criteria\IsNotNullCriteria;
use PHPUnit\Framework\TestCase;

#[Entity]
class TestUser {
    public int $id;

    public string $name;

    public int $age;

    public bool $active;

    public ?string $email;
}

class UserRepository extends AbstractRepository {
    // Test repository extending AbstractRepository
}

class RepositoryCriteriaTest extends TestCase {
    private EntityManager $entityManager;

    private UserRepository $repository;

    protected function setUp(): void
    {
        $connection = $this->createMock(Connection::class);
        $this->entityManager = new EntityManager($connection);
        $this->repository = new UserRepository($this->entityManager, TestUser::class);
    }

    public function testRepositoryImplementsCriteriaMethods(): void
    {
        $this->assertTrue(method_exists($this->repository, 'findByCriteria'));
        $this->assertTrue(method_exists($this->repository, 'findOneByCriteria'));
        $this->assertTrue(method_exists($this->repository, 'countByCriteria'));
        $this->assertTrue(method_exists($this->repository, 'existsByCriteria'));
    }

    public function testFindByCriteriaReturnsArray(): void
    {
        $criteria = new EqualsCriteria('active', true);

        // Since we're using mocks, these methods will return empty arrays
        // but we can test that they don't throw exceptions and return correct types
        $result = $this->repository->findByCriteria($criteria);

        $this->assertIsArray($result);
    }

    public function testFindOneByCriteriaReturnsNullOrObject(): void
    {
        $criteria = new GreaterThanCriteria('age', 18);

        $result = $this->repository->findOneByCriteria($criteria);

        $this->assertTrue($result === null || is_object($result));
    }

    public function testCountByCriteriaReturnsInt(): void
    {
        $criteria = new IsNotNullCriteria('email');

        $result = $this->repository->countByCriteria($criteria);

        $this->assertIsInt($result);
    }

    public function testExistsByCriteriaReturnsBool(): void
    {
        $criteria = new EqualsCriteria('name', 'John');

        $result = $this->repository->existsByCriteria($criteria);

        $this->assertIsBool($result);
    }

    public function testCriteriaWithOrderingAndLimits(): void
    {
        $criteria = new EqualsCriteria('active', true);
        $orderBy = ['name' => 'ASC'];

        $result = $this->repository->findByCriteria($criteria, $orderBy, 10, 5);

        $this->assertIsArray($result);
    }
}
