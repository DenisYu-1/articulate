<?php

namespace Articulate\Tests\Modules\Repository\Criteria;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\AutoIncrement;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Connection;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\Repository\AbstractRepository;
use Articulate\Modules\Repository\Criteria\EqualsCriteria;
use Articulate\Modules\Repository\Criteria\GreaterThanCriteria;
use Articulate\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

#[Entity]
class CriteriaMember {
    #[PrimaryKey]
    #[AutoIncrement]
    #[Property]
    public ?int $id = null;

    #[Property]
    public string $name;

    #[Property]
    public int $age;

    #[Property]
    public bool $active;
}

class CriteriaMemberRepository extends AbstractRepository {
}

class RepositoryCriteriaDatabaseTest extends DatabaseTestCase {
    private CriteriaMemberRepository $repository;

    protected function setUpTestTables(Connection $connection, string $databaseName): bool
    {
        $connection->executeQuery('DROP TABLE IF EXISTS criteria_member');

        $sql = match ($databaseName) {
            'mysql' => 'CREATE TABLE criteria_member (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                age INT NOT NULL,
                active TINYINT(1) NOT NULL
            )',
            'pgsql' => 'CREATE TABLE criteria_member (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                age INT NOT NULL,
                active BOOLEAN NOT NULL
            )',
            default => throw new \InvalidArgumentException("Unknown database: {$databaseName}")
        };

        $connection->executeQuery($sql);

        return true;
    }

    protected function tearDownTestTables(Connection $connection, string $databaseName): void
    {
        $connection->executeQuery('DROP TABLE IF EXISTS criteria_member');
    }

    #[DataProvider('databaseProvider')]
    public function testFindByCriteriaAppliesCriteriaOrderLimitAndOffset(string $databaseName): void
    {
        $this->seed($databaseName);
        $criteria = new EqualsCriteria('active', true);

        $all = $this->repository->findByCriteria($criteria, ['age' => 'ASC']);
        $this->assertSame([20, 30, 40], array_map(fn ($m) => $m->age, $all));

        $desc = $this->repository->findByCriteria($criteria, ['age' => 'DESC']);
        $this->assertSame([40, 30, 20], array_map(fn ($m) => $m->age, $desc));

        $limited = $this->repository->findByCriteria($criteria, ['age' => 'ASC'], 2);
        $this->assertSame([20, 30], array_map(fn ($m) => $m->age, $limited));

        $offset = $this->repository->findByCriteria($criteria, ['age' => 'ASC'], 2, 1);
        $this->assertSame([30, 40], array_map(fn ($m) => $m->age, $offset));
    }

    #[DataProvider('databaseProvider')]
    public function testFindOneByCriteriaReturnsFirstOrderedMatchOrNull(string $databaseName): void
    {
        $this->seed($databaseName);

        $youngest = $this->repository->findOneByCriteria(new EqualsCriteria('active', true), ['age' => 'ASC']);
        $this->assertNotNull($youngest);
        $this->assertSame(20, $youngest->age);

        $oldest = $this->repository->findOneByCriteria(new EqualsCriteria('active', true), ['age' => 'DESC']);
        $this->assertNotNull($oldest);
        $this->assertSame(40, $oldest->age);

        $this->assertNull($this->repository->findOneByCriteria(new GreaterThanCriteria('age', 999)));
    }

    #[DataProvider('databaseProvider')]
    public function testCountByCriteriaReturnsMatchingRowCount(string $databaseName): void
    {
        $this->seed($databaseName);

        $this->assertSame(3, $this->repository->countByCriteria(new EqualsCriteria('active', true)));
        $this->assertSame(1, $this->repository->countByCriteria(new EqualsCriteria('active', false)));
        $this->assertSame(3, $this->repository->countByCriteria(new GreaterThanCriteria('age', 25)));
        $this->assertSame(1, $this->repository->countByCriteria(new GreaterThanCriteria('age', 45)));
        $this->assertSame(0, $this->repository->countByCriteria(new GreaterThanCriteria('age', 999)));
    }

    #[DataProvider('databaseProvider')]
    public function testExistsByCriteriaReflectsCount(string $databaseName): void
    {
        $this->seed($databaseName);

        $this->assertTrue($this->repository->existsByCriteria(new EqualsCriteria('active', true)));
        $this->assertFalse($this->repository->existsByCriteria(new GreaterThanCriteria('age', 999)));
    }

    #[DataProvider('databaseProvider')]
    public function testFindWithCursorPaginatesForwardWithoutOverlap(string $databaseName): void
    {
        $this->seed($databaseName);

        $first = $this->repository->findWithCursor(null, 2, ['id' => 'ASC']);
        $firstIds = array_map(fn ($m) => $m->id, $first->getItems());
        $this->assertCount(2, $firstIds);
        $this->assertTrue($first->hasNext());

        $second = $this->repository->findWithCursor($first->getNextCursor(), 2, ['id' => 'ASC']);
        $secondIds = array_map(fn ($m) => $m->id, $second->getItems());
        $this->assertCount(2, $secondIds);
        $this->assertSame([], array_intersect($firstIds, $secondIds));
    }

    #[DataProvider('databaseProvider')]
    public function testFindWithCursorByCriteriaFiltersAndPaginates(string $databaseName): void
    {
        $this->seed($databaseName);
        $criteria = new EqualsCriteria('active', true);

        $first = $this->repository->findWithCursorByCriteria($criteria, null, 2, ['id' => 'ASC']);
        $firstItems = $first->getItems();
        $this->assertCount(2, $firstItems);
        foreach ($firstItems as $item) {
            $this->assertTrue($item->active);
        }

        $second = $this->repository->findWithCursorByCriteria($criteria, $first->getNextCursor(), 2, ['id' => 'ASC']);
        $secondItems = $second->getItems();
        $this->assertCount(1, $secondItems);
        $this->assertTrue($secondItems[0]->active);
        $this->assertSame(
            [],
            array_intersect(
                array_map(fn ($m) => $m->id, $firstItems),
                array_map(fn ($m) => $m->id, $secondItems)
            )
        );
    }

    private function seed(string $databaseName): void
    {
        $connection = $this->getConnection($databaseName);
        $this->setCurrentDatabase($connection, $databaseName);
        $this->repository = new CriteriaMemberRepository(new EntityManager($connection), CriteriaMember::class);

        $active = $databaseName === 'pgsql' ? 'true' : '1';
        $inactive = $databaseName === 'pgsql' ? 'false' : '0';

        $connection->executeQuery(
            "INSERT INTO criteria_member (name, age, active) VALUES
                ('Ann', 20, {$active}),
                ('Bob', 30, {$active}),
                ('Cid', 40, {$active}),
                ('Dan', 50, {$inactive})"
        );
    }
}
