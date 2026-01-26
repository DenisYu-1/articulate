<?php

namespace Articulate\Tests\Modules\Repository\Criteria;

use Articulate\Attributes\Entity;
use Articulate\Connection;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\QueryBuilder\QueryBuilder;
use Articulate\Modules\Repository\AbstractRepository;
use Articulate\Modules\Repository\Criteria\AndCriteria;
use Articulate\Modules\Repository\Criteria\BetweenCriteria;
use Articulate\Modules\Repository\Criteria\CriteriaInterface;
use Articulate\Modules\Repository\Criteria\EqualsCriteria;
use Articulate\Modules\Repository\Criteria\GreaterThanCriteria;
use Articulate\Modules\Repository\Criteria\GreaterThanOrEqualCriteria;
use Articulate\Modules\Repository\Criteria\InCriteria;
use Articulate\Modules\Repository\Criteria\IsNotNullCriteria;
use Articulate\Modules\Repository\Criteria\IsNullCriteria;
use Articulate\Modules\Repository\Criteria\LessThanCriteria;
use Articulate\Modules\Repository\Criteria\LessThanOrEqualCriteria;
use Articulate\Modules\Repository\Criteria\LikeCriteria;
use Articulate\Modules\Repository\Criteria\NotBetweenCriteria;
use Articulate\Modules\Repository\Criteria\NotCriteria;
use Articulate\Modules\Repository\Criteria\NotEqualsCriteria;
use Articulate\Modules\Repository\Criteria\NotInCriteria;
use Articulate\Modules\Repository\Criteria\NotLikeCriteria;
use Articulate\Modules\Repository\Criteria\OrCriteria;
use PHPUnit\Framework\TestCase;

#[Entity]
class TestEntity {
    public int $id;
    public string $name;
    public int $age;
    public ?string $email;
    public bool $active;
}

class TestRepository extends AbstractRepository {
    // Test repository for criteria testing
}

class CriteriaTest extends TestCase {
    private QueryBuilder $qb;

    protected function setUp(): void
    {
        $connection = $this->createMock(Connection::class);
        $this->qb = new QueryBuilder($connection);
        $this->qb->from('test_entities');
    }

    public function testEqualsCriteria(): void
    {
        $criteria = new EqualsCriteria('name', 'John');
        $criteria->apply($this->qb);

        $this->assertStringContainsString('name = ?', $this->qb->getSQL());
        $this->assertEquals(['John'], $this->qb->getParameters());
    }

    public function testNotEqualsCriteria(): void
    {
        $criteria = new NotEqualsCriteria('status', 'inactive');
        $criteria->apply($this->qb);

        $this->assertStringContainsString('status != ?', $this->qb->getSQL());
        $this->assertEquals(['inactive'], $this->qb->getParameters());
    }

    public function testGreaterThanCriteria(): void
    {
        $criteria = new GreaterThanCriteria('age', 18);
        $criteria->apply($this->qb);

        $this->assertStringContainsString('age > ?', $this->qb->getSQL());
        $this->assertEquals([18], $this->qb->getParameters());
    }

    public function testGreaterThanOrEqualCriteria(): void
    {
        $criteria = new GreaterThanOrEqualCriteria('score', 85);
        $criteria->apply($this->qb);

        $this->assertStringContainsString('score >= ?', $this->qb->getSQL());
        $this->assertEquals([85], $this->qb->getParameters());
    }

    public function testLessThanCriteria(): void
    {
        $criteria = new LessThanCriteria('price', 100);
        $criteria->apply($this->qb);

        $this->assertStringContainsString('price < ?', $this->qb->getSQL());
        $this->assertEquals([100], $this->qb->getParameters());
    }

    public function testLessThanOrEqualCriteria(): void
    {
        $criteria = new LessThanOrEqualCriteria('quantity', 50);
        $criteria->apply($this->qb);

        $this->assertStringContainsString('quantity <= ?', $this->qb->getSQL());
        $this->assertEquals([50], $this->qb->getParameters());
    }

    public function testLikeCriteria(): void
    {
        $criteria = new LikeCriteria('name', 'John%');
        $criteria->apply($this->qb);

        $this->assertStringContainsString('name LIKE ?', $this->qb->getSQL());
        $this->assertEquals(['John%'], $this->qb->getParameters());
    }

    public function testNotLikeCriteria(): void
    {
        $criteria = new NotLikeCriteria('email', '%@test.com');
        $criteria->apply($this->qb);

        $this->assertStringContainsString('email NOT LIKE ?', $this->qb->getSQL());
        $this->assertEquals(['%@test.com'], $this->qb->getParameters());
    }

    public function testIsNullCriteria(): void
    {
        $criteria = new IsNullCriteria('deleted_at');
        $criteria->apply($this->qb);

        $this->assertStringContainsString('deleted_at IS NULL', $this->qb->getSQL());
        $this->assertEmpty($this->qb->getParameters());
    }

    public function testIsNotNullCriteria(): void
    {
        $criteria = new IsNotNullCriteria('updated_at');
        $criteria->apply($this->qb);

        $this->assertStringContainsString('updated_at IS NOT NULL', $this->qb->getSQL());
        $this->assertEmpty($this->qb->getParameters());
    }

    public function testBetweenCriteria(): void
    {
        $criteria = new BetweenCriteria('age', 18, 65);
        $criteria->apply($this->qb);

        $this->assertStringContainsString('age BETWEEN ? AND ?', $this->qb->getSQL());
        $this->assertEquals([18, 65], $this->qb->getParameters());
    }

    public function testNotBetweenCriteria(): void
    {
        $criteria = new NotBetweenCriteria('price', 10, 100);
        $criteria->apply($this->qb);

        $this->assertStringContainsString('price NOT BETWEEN ? AND ?', $this->qb->getSQL());
        $this->assertEquals([10, 100], $this->qb->getParameters());
    }

    public function testInCriteria(): void
    {
        $criteria = new InCriteria('status', ['active', 'pending']);
        $criteria->apply($this->qb);

        $this->assertStringContainsString('status IN (?)', $this->qb->getSQL());
        $this->assertEquals([['active', 'pending']], $this->qb->getParameters());
    }

    public function testNotInCriteria(): void
    {
        $criteria = new NotInCriteria('role', ['admin', 'superuser']);
        $criteria->apply($this->qb);

        $this->assertStringContainsString('role NOT IN (?)', $this->qb->getSQL());
        $this->assertEquals([['admin', 'superuser']], $this->qb->getParameters());
    }

    public function testAndCriteria(): void
    {
        $criteria1 = new EqualsCriteria('active', true);
        $criteria2 = new GreaterThanCriteria('age', 18);
        $andCriteria = new AndCriteria([$criteria1, $criteria2]);

        $andCriteria->apply($this->qb);

        $sql = $this->qb->getSQL();
        $this->assertStringContainsString('WHERE', $sql);
        // The AND criteria should create grouped conditions
        $this->assertStringContainsString('active = ?', $sql);
        $this->assertStringContainsString('age > ?', $sql);
    }

    public function testOrCriteria(): void
    {
        $criteria1 = new EqualsCriteria('status', 'active');
        $criteria2 = new EqualsCriteria('status', 'pending');
        $orCriteria = new OrCriteria([$criteria1, $criteria2]);

        $orCriteria->apply($this->qb);

        $sql = $this->qb->getSQL();
        $this->assertEquals('SELECT * FROM test_entities WHERE (status = ?) OR (status = ?)', $sql);
        $this->assertEquals(['active', 'pending'], $this->qb->getParameters());
    }

    public function testNotCriteria(): void
    {
        $criteria = new NotCriteria(new EqualsCriteria('status', 'active'));

        $criteria->apply($this->qb);

        $this->assertEquals('SELECT * FROM test_entities WHERE NOT (status = ?)', $this->qb->getSQL());
        $this->assertEquals(['active'], $this->qb->getParameters());
    }

    public function testCriteriaChaining(): void
    {
        $qb = new QueryBuilder($this->createMock(Connection::class));
        $qb->from('users');

        $criteria = new AndCriteria([
            new EqualsCriteria('active', true),
            new GreaterThanCriteria('age', 18),
            new LikeCriteria('name', 'John%')
        ]);

        $criteria->apply($qb);

        $sql = $qb->getSQL();
        $this->assertStringContainsString('active = ?', $sql);
        $this->assertStringContainsString('age > ?', $sql);
        $this->assertStringContainsString('name LIKE ?', $sql);
    }
}