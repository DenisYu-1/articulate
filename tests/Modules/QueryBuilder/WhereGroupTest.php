<?php

namespace Articulate\Tests\Modules\QueryBuilder;

use Articulate\Connection;
use Articulate\Modules\QueryBuilder\QueryBuilder;
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
            ->whereGroup(function ($qb) {
                $qb->where('role = ?', 'admin')
                   ->orWhere('permissions LIKE ?', '%superuser%');
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
            ->whereGroup(function ($qb) {
                $qb->where('age >= ?', 18)
                   ->where('age <= ?', 65);
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
            ->whereGroup(function ($qb) {
                $qb->where('category = ?', 'electronics')
                   ->orWhere('category = ?', 'books');
            })
            ->whereGroup(function ($qb) {
                $qb->where('price > ?', 10)
                   ->where('price < ?', 100);
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
            ->whereGroup(function ($qb) {
                $qb->where('status = ?', 'active')
                   ->whereGroup(function ($qb) {
                       $qb->where('role = ?', 'admin')
                          ->orWhere('role = ?', 'moderator');
                   });
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
            ->whereGroup(function ($qb) {
                $qb->where('a = ?', 1)
                   ->whereGroup(function ($qb) {
                       $qb->where('b = ?', 2)
                          ->orWhere('b = ?', 3)
                          ->whereGroup(function ($qb) {
                              $qb->where('c = ?', 4)
                                 ->orWhere('c = ?', 5);
                          });
                   });
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
            ->whereGroup(function ($qb) {
                $qb->where('role = ?', 'admin')
                   ->orWhere('role = ?', 'moderator');
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
            ->whereGroup(function ($qb) {
                $qb->whereIn('role', ['admin', 'moderator'])
                   ->whereNotIn('status', ['banned', 'suspended']);
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
            ->whereGroup(function ($qb) {
                $qb->whereNotNull('published_at')
                   ->whereNull('deleted_at');
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
            ->whereGroup(function ($qb) {
                $qb->whereBetween('price', 10, 100)
                   ->whereBetween('created_at', '2023-01-01', '2023-12-31');
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
            ->whereGroup(function ($qb) {
                $qb->where('age >= ?', 18)
                   ->orWhere('parental_consent = ?', true);
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
            ->whereGroup(function ($qb) {
                // Empty callback - no conditions added
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
            ->whereGroup(function ($qb) {
                $qb->where('age > ?', 21)
                   ->whereGroup(function ($qb) {
                       $qb->where('country = ?', 'US')
                          ->orWhere('country = ?', 'CA');
                   })
                   ->where('verified = ?', true);
            })
            ->where('created_at > ?', '2023-01-01');

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $expected = 'SELECT id, name FROM users WHERE active = ? AND (age > ? AND (country = ? OR country = ?) AND verified = ?) AND created_at > ?';
        $this->assertEquals($expected, $sql);
        $this->assertEquals([true, 21, 'US', 'CA', true, '2023-01-01'], $params);
    }
}
