<?php

namespace Articulate\Tests\Modules\QueryBuilder;

use Articulate\Connection;
use Articulate\Modules\QueryBuilder\QueryBuilder;
use PHPUnit\Framework\TestCase;

class ResetMethodsTest extends TestCase {
    private QueryBuilder $qb;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->qb = new QueryBuilder($this->connection);
    }

    public function testResetWhere(): void
    {
        $qb = $this->qb
            ->select('*')
            ->from('users')
            ->where('active = ?', true)
            ->where('age > ?', 18)
            ->resetWhere()
            ->where('status = ?', 'pending');

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT * FROM users WHERE status = ?', $sql);
        $this->assertEquals(['pending'], $params);
    }

    public function testResetSelect(): void
    {
        $qb = $this->qb
            ->select('id', 'name', 'email')
            ->from('users')
            ->where('active = ?', true)
            ->resetSelect()
            ->select('id', 'status');

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT id, status FROM users WHERE active = ?', $sql);
        $this->assertEquals([true], $params);
    }

    public function testResetJoins(): void
    {
        $qb = $this->qb
            ->select('*')
            ->from('users', 'u')
            ->join('posts', 'posts.user_id = u.id')
            ->leftJoin('comments', 'comments.user_id = u.id')
            ->where('u.active = ?', true)
            ->resetJoins()
            ->join('orders', 'orders.user_id = u.id');

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT * FROM users u JOIN orders ON orders.user_id = u.id WHERE u.active = ?', $sql);
        $this->assertEquals([true], $params);
    }

    public function testResetOrderBy(): void
    {
        $qb = $this->qb
            ->select('*')
            ->from('users')
            ->orderBy('name', 'ASC')
            ->orderBy('created_at', 'DESC')
            ->resetOrderBy()
            ->orderBy('id', 'ASC');

        $sql = $qb->getSQL();

        $this->assertEquals('SELECT * FROM users ORDER BY id ASC', $sql);
    }

    public function testResetGroupBy(): void
    {
        $qb = $this->qb
            ->select('category', 'COUNT(*)')
            ->from('products')
            ->groupBy('category')
            ->groupBy('status')
            ->resetGroupBy()
            ->groupBy('brand');

        $sql = $qb->getSQL();

        $this->assertEquals('SELECT category, COUNT(*) FROM products GROUP BY brand', $sql);
    }

    // HAVING tests moved to Phase 2 (Aggregates/HAVING)

    public function testResetFull(): void
    {
        $qb = $this->qb
            ->select('id', 'name')
            ->from('users', 'u')
            ->join('posts', 'posts.user_id = u.id')
            ->where('u.active = ?', true)
            ->where('u.age > ?', 18)
            ->orderBy('u.name')
            ->groupBy('u.status')
            // HAVING not implemented in Phase 1
            ->limit(10)
            ->offset(20)
            ->reset()  // Reset everything
            ->select('*')
            ->from('products')
            ->where('price > ?', 100);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT * FROM products WHERE price > ?', $sql);
        $this->assertEquals([100], $params);
    }

    public function testResetMethodsAreChainable(): void
    {
        $qb = $this->qb
            ->select('id', 'name')
            ->from('users')
            ->where('active = ?', true)
            ->resetWhere()
            ->resetSelect()
            ->select('*')
            ->where('deleted_at IS NULL');

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT * FROM users WHERE deleted_at IS NULL', $sql);
        $this->assertEquals([], $params);
    }

    public function testResetAfterResetIdempotent(): void
    {
        $qb = $this->qb
            ->select('id')
            ->from('users')
            ->where('active = ?', true)
            ->resetWhere()
            ->resetWhere()  // Second reset should be harmless
            ->where('status = ?', 'pending');

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT id FROM users WHERE status = ?', $sql);
        $this->assertEquals(['pending'], $params);
    }

    public function testResetMethodsReturnSelf(): void
    {
        $qb = $this->qb;

        $this->assertSame($qb, $qb->reset());
        $this->assertSame($qb, $qb->resetWhere());
        $this->assertSame($qb, $qb->resetSelect());
        $this->assertSame($qb, $qb->resetJoins());
        $this->assertSame($qb, $qb->resetOrderBy());
        $this->assertSame($qb, $qb->resetGroupBy());
        // resetHaving() not implemented in Phase 1
    }

    public function testBuildQueryAfterReset(): void
    {
        // Test that building a complete query works after reset
        $qb = $this->qb
            ->select('id', 'name', 'email')
            ->from('users', 'u')
            ->join('posts', 'posts.user_id = u.id')
            ->where('u.active = ?', true)
            ->orderBy('u.name')
            ->limit(5)
            ->reset()
            ->select('p.id', 'p.title')
            ->from('posts', 'p')
            ->leftJoin('users', 'users.id = p.user_id')
            ->where('p.published = ?', true)
            ->orderBy('p.created_at', 'DESC')
            ->limit(20);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $expected = 'SELECT p.id, p.title FROM posts p LEFT JOIN users ON users.id = p.user_id WHERE p.published = ? ORDER BY p.created_at DESC LIMIT 20';
        $this->assertEquals($expected, $sql);
        $this->assertEquals([true], $params);
    }

    // DISTINCT tests moved to Phase 2 (Aggregates/HAVING)
}
