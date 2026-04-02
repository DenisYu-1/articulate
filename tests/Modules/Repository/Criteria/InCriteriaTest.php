<?php

namespace Articulate\Tests\Modules\Repository\Criteria;

use Articulate\Connection;
use Articulate\Modules\QueryBuilder\QueryBuilder;
use Articulate\Modules\Repository\Criteria\InCriteria;
use Articulate\Modules\Repository\Criteria\NotInCriteria;
use PHPUnit\Framework\TestCase;

class InCriteriaTest extends TestCase {
    private QueryBuilder $qb;

    protected function setUp(): void
    {
        $connection = $this->createStub(Connection::class);
        $this->qb = new QueryBuilder($connection);
        $this->qb->from('test_table');
    }

    public function testInCriteriaWithValues(): void
    {
        $criteria = new InCriteria('status', ['active', 'pending', 'review']);
        $criteria->apply($this->qb);

        $this->assertStringContainsString('status IN (?)', $this->qb->getSQL());
        $this->assertEquals([['active', 'pending', 'review']], $this->qb->getParameters());
    }

    public function testInCriteriaWithEmptyArray(): void
    {
        $criteria = new InCriteria('status', []);
        $criteria->apply($this->qb);

        $sql = $this->qb->getSQL();
        $this->assertStringContainsString('1 = 0', $sql);
        $this->assertEmpty($this->qb->getParameters());
    }

    public function testNotInCriteriaWithValues(): void
    {
        $criteria = new NotInCriteria('role', ['admin', 'superuser']);
        $criteria->apply($this->qb);

        $this->assertStringContainsString('role NOT IN (?)', $this->qb->getSQL());
        $this->assertEquals([['admin', 'superuser']], $this->qb->getParameters());
    }
}
