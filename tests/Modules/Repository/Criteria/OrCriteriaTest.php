<?php

namespace Articulate\Tests\Modules\Repository\Criteria;

use Articulate\Connection;
use Articulate\Modules\QueryBuilder\QueryBuilder;
use Articulate\Modules\Repository\Criteria\AndCriteria;
use Articulate\Modules\Repository\Criteria\EqualsCriteria;
use Articulate\Modules\Repository\Criteria\OrCriteria;
use PHPUnit\Framework\TestCase;

class OrCriteriaTest extends TestCase {
    private function createQueryBuilder(): QueryBuilder
    {
        $connection = $this->createStub(Connection::class);

        return (new QueryBuilder($connection))->from('test_table');
    }

    public function testOrCriteriaAppliesCorrectly(): void
    {
        $qb = $this->createQueryBuilder();

        $criteria = new OrCriteria([
            new EqualsCriteria('status', 'active'),
            new EqualsCriteria('status', 'pending'),
        ]);
        $qb->apply($criteria);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertStringContainsString('status = ?', $sql);
        $this->assertStringContainsString('OR', $sql);
        $this->assertSame(['active', 'pending'], $params);
    }

    public function testOrCriteriaWithEmptyList(): void
    {
        $qb = $this->createQueryBuilder();

        $criteria = new OrCriteria([]);
        $qb->apply($criteria);

        $sql = $qb->getSQL();

        $this->assertStringNotContainsString('WHERE', $sql);
        $this->assertSame([], $qb->getParameters());
    }

    public function testOrCriteriaInsideAndCriteria(): void
    {
        $qb = $this->createQueryBuilder();

        $criteria = new AndCriteria([
            new EqualsCriteria('type', 'user'),
            new OrCriteria([
                new EqualsCriteria('role', 'admin'),
                new EqualsCriteria('role', 'moderator'),
            ]),
        ]);
        $qb->apply($criteria);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertStringContainsString('type = ?', $sql);
        $this->assertStringContainsString('OR', $sql);
        $this->assertStringContainsString('role = ?', $sql);
        $this->assertSame(['user', 'admin', 'moderator'], $params);

        $whereClause = substr($sql, strpos($sql, 'WHERE') + 6);
        $this->assertStringContainsString('AND', $whereClause);
        $this->assertStringContainsString('OR', $whereClause);
    }
}
