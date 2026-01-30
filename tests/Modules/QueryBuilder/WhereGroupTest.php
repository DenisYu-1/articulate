<?php

namespace Articulate\Tests\Modules\QueryBuilder;

use Articulate\Connection;
use Articulate\Modules\QueryBuilder\QueryBuilder;
use Articulate\Modules\Repository\Criteria\AndCriteria;
use Articulate\Modules\Repository\Criteria\BetweenCriteria;
use Articulate\Modules\Repository\Criteria\EqualsCriteria;
use Articulate\Modules\Repository\Criteria\GreaterThanCriteria;
use Articulate\Modules\Repository\Criteria\GreaterThanOrEqualCriteria;
use Articulate\Modules\Repository\Criteria\GroupCriteria;
use Articulate\Modules\Repository\Criteria\InCriteria;
use Articulate\Modules\Repository\Criteria\IsNotNullCriteria;
use Articulate\Modules\Repository\Criteria\IsNullCriteria;
use Articulate\Modules\Repository\Criteria\LessThanCriteria;
use Articulate\Modules\Repository\Criteria\LessThanOrEqualCriteria;
use Articulate\Modules\Repository\Criteria\LikeCriteria;
use Articulate\Modules\Repository\Criteria\NotInCriteria;
use PHPUnit\Framework\TestCase;

class WhereGroupTest extends TestCase {
    private QueryBuilder $qb;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->qb = new QueryBuilder($this->connection);
    }

    public function testSimpleWhereGroup(): void
    {
        $qb = $this->qb
            ->select('*')
            ->from('users')
            ->where('status = ?', 'active')
            ->where(function($q) {
                $q->apply(new AndCriteria([
                    new EqualsCriteria('role', 'admin'),
                    (new LikeCriteria('permissions', '%superuser%'))->or(),
                ]));
            });

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT * FROM users WHERE status = ? AND (role = ? OR permissions LIKE ?)', $sql);
        $this->assertEquals(['active', 'admin', '%superuser%'], $params);
    }

    public function testWhereGroupAfterWhere(): void
    {
        $qb = $this->qb
            ->select('id', 'name')
            ->from('users')
            ->where('active = ?', true)
            ->where(function($q) {
                $q->apply(new AndCriteria([
                    new GreaterThanOrEqualCriteria('age', 18),
                    new LessThanOrEqualCriteria('age', 65),
                ]));
            });

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT id, name FROM users WHERE active = ? AND (age >= ? AND age <= ?)', $sql);
        $this->assertEquals([true, 18, 65], $params);
    }

    public function testMultipleWhereGroups(): void
    {
        $qb = $this->qb
            ->select('*')
            ->from('products')
            ->where(function($q) {
                $q->apply(new AndCriteria([
                    new EqualsCriteria('category', 'electronics'),
                    (new EqualsCriteria('category', 'books'))->or(),
                ]));
            })
            ->where(function($q) {
                $q->apply(new AndCriteria([
                    new GreaterThanCriteria('price', 10),
                    new LessThanCriteria('price', 100),
                ]));
            });

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $expected = 'SELECT * FROM products WHERE (category = ? OR category = ?) AND (price > ? AND price < ?)';
        $this->assertEquals($expected, $sql);
        $this->assertEquals(['electronics', 'books', 10, 100], $params);
    }

    public function testNestedWhereGroups(): void
    {
        $qb = $this->qb
            ->select('id', 'name', 'email')
            ->from('users')
            ->where(function($q) {
                $q->apply(new AndCriteria([
                    new EqualsCriteria('status', 'active'),
                    new GroupCriteria(
                        new AndCriteria([
                            new EqualsCriteria('role', 'admin'),
                            (new EqualsCriteria('role', 'moderator'))->or(),
                        ])
                    ),
                ]));
            });

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $expected = 'SELECT id, name, email FROM users WHERE (status = ? AND (role = ? OR role = ?))';
        $this->assertEquals($expected, $sql);
        $this->assertEquals(['active', 'admin', 'moderator'], $params);
    }

    public function testDeeplyNestedWhereGroups(): void
    {
        $qb = $this->qb
            ->select('*')
            ->from('complex_table')
            ->where(function($q) {
                $q->apply(new AndCriteria([
                    new EqualsCriteria('a', 1),
                    new GroupCriteria(
                        new AndCriteria([
                            new EqualsCriteria('b', 2),
                            (new EqualsCriteria('b', 3))->or(),
                            new GroupCriteria(
                                new AndCriteria([
                                    new EqualsCriteria('c', 4),
                                    (new EqualsCriteria('c', 5))->or(),
                                ])
                            ),
                        ])
                    ),
                ]));
            });

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $expected = 'SELECT * FROM complex_table WHERE (a = ? AND (b = ? OR b = ? AND (c = ? OR c = ?)))';
        $this->assertEquals($expected, $sql);
        $this->assertEquals([1, 2, 3, 4, 5], $params);
    }

    public function testWhereGroupWithOrConnector(): void
    {
        $qb = $this->qb
            ->select('*')
            ->from('users')
            ->where('deleted_at IS NULL')
            ->where(function($q) {
                $q->apply(new AndCriteria([
                    new EqualsCriteria('role', 'admin'),
                    (new EqualsCriteria('role', 'moderator'))->or(),
                ]));
            });

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT * FROM users WHERE deleted_at IS NULL AND (role = ? OR role = ?)', $sql);
        $this->assertEquals(['admin', 'moderator'], $params);
    }

    public function testWhereGroupWithInClauses(): void
    {
        $qb = $this->qb
            ->select('id', 'name')
            ->from('users')
            ->where(function($q) {
                $q->apply(new AndCriteria([
                    new InCriteria('role', ['admin', 'moderator']),
                    new NotInCriteria('status', ['banned', 'suspended']),
                ]));
            });

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $expected = 'SELECT id, name FROM users WHERE (role IN (?) AND status NOT IN (?))';
        $this->assertEquals($expected, $sql);
        $this->assertEquals([['admin', 'moderator'], ['banned', 'suspended']], $params);
    }

    public function testWhereGroupWithNullChecks(): void
    {
        $qb = $this->qb
            ->select('*')
            ->from('posts')
            ->where(function($q) {
                $q->apply(new AndCriteria([
                    new IsNotNullCriteria('published_at'),
                    new IsNullCriteria('deleted_at'),
                ]));
            });

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT * FROM posts WHERE (published_at IS NOT NULL AND deleted_at IS NULL)', $sql);
        $this->assertEquals([], $params);
    }

    public function testWhereGroupWithBetween(): void
    {
        $qb = $this->qb
            ->select('id', 'title', 'price')
            ->from('products')
            ->where(function($q) {
                $q->apply(new AndCriteria([
                    new BetweenCriteria(field: 'price', min: 10, max: 100),
                    new BetweenCriteria(field: 'created_at', min: '2023-01-01', max: '2023-12-31'),
                ]));
            });

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $expected = 'SELECT id, title, price FROM products WHERE (price BETWEEN ? AND ? AND created_at BETWEEN ? AND ?)';
        $this->assertEquals($expected, $sql);
        $this->assertEquals([10, 100, '2023-01-01', '2023-12-31'], $params);
    }

    public function testWhereGroupMixedWithDirectWhere(): void
    {
        $qb = $this->qb
            ->select('*')
            ->from('users')
            ->where('active = ?', true)
            ->where(function($q) {
                $q->apply(new AndCriteria([
                    new GreaterThanOrEqualCriteria('age', 18),
                    (new EqualsCriteria('parental_consent', true))->or(),
                ]));
            })
            ->where('country = ?', 'US');

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $expected = 'SELECT * FROM users WHERE active = ? AND (age >= ? OR parental_consent = ?) AND country = ?';
        $this->assertEquals($expected, $sql);
        $this->assertEquals([true, 18, true, 'US'], $params);
    }

    public function testEmptyWhereGroup(): void
    {
        // This tests what happens if the callback doesn't add any conditions
        $qb = $this->qb
            ->select('*')
            ->from('users')
            ->where('active = ?', true)
            ->where(function($q) {
                $q->apply(new AndCriteria([]));
            });

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        // Should still work, just ignore the empty group
        $this->assertEquals('SELECT * FROM users WHERE active = ?', $sql);
        $this->assertEquals([true], $params);
    }

    public function testParameterOrderInNestedGroups(): void
    {
        $qb = $this->qb
            ->select('id', 'name')
            ->from('users')
            ->where('active = ?', true)
            ->where(function($q) {
                $q->apply(new AndCriteria([
                    new GreaterThanCriteria('age', 21),
                    new GroupCriteria(
                        new AndCriteria([
                            new EqualsCriteria('country', 'US'),
                            (new EqualsCriteria('country', 'CA'))->or(),
                        ])
                    ),
                    new EqualsCriteria('verified', true),
                ]));
            })
            ->where('created_at > ?', '2023-01-01');

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $expected = 'SELECT id, name FROM users WHERE active = ? AND (age > ? AND (country = ? OR country = ?) AND verified = ?) AND created_at > ?';
        $this->assertEquals($expected, $sql);
        $this->assertEquals([true, 21, 'US', 'CA', true, '2023-01-01'], $params);
    }
}
