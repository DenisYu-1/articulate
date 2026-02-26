<?php

namespace Articulate\Tests\Modules\QueryBuilder;

use Articulate\Connection;
use Articulate\Modules\QueryBuilder\QueryBuilder;
use PHPUnit\Framework\TestCase;

class RawSqlTest extends TestCase {
    private QueryBuilder $qb;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->qb = new QueryBuilder($this->connection);
    }

    public function testRawReplacesEntireQuery(): void
    {
        $qb = $this->qb->raw('SELECT * FROM custom_table WHERE complex_condition = ?', 'value');

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT * FROM custom_table WHERE complex_condition = ?', $sql);
        $this->assertEquals(['value'], $params);
    }

    public function testRawWithMultipleParameters(): void
    {
        $qb = $this->qb->raw('SELECT * FROM users WHERE age BETWEEN ? AND ? AND status = ?', [18, 65, 'active']);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT * FROM users WHERE age BETWEEN ? AND ? AND status = ?', $sql);
        $this->assertEquals([18, 65, 'active'], $params);
    }

    public function testRawWithComplexQuery(): void
    {
        $complexSql = '
            WITH RECURSIVE category_tree AS (
                SELECT id, parent_id, name FROM categories WHERE parent_id IS NULL
                UNION ALL
                SELECT c.id, c.parent_id, c.name FROM categories c
                JOIN category_tree ct ON c.parent_id = ct.id
            )
            SELECT * FROM category_tree WHERE id = ?
        ';

        $qb = $this->qb->raw($complexSql, [5]);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals($complexSql, $sql);
        $this->assertEquals([5], $params);
    }

    public function testSelectRawAddsRawExpression(): void
    {
        $qb = $this->qb
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(price * quantity) as revenue')
            ->from('orders');

        $sql = $qb->getSQL();

        $this->assertEquals('SELECT COUNT(*) as total, SUM(price * quantity) as revenue FROM orders', $sql);
    }

    public function testSelectRawWithParameters(): void
    {
        $qb = $this->qb
            ->selectRaw('CASE WHEN status = ? THEN 1 ELSE 0 END as is_active', 'active')
            ->from('users');

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT CASE WHEN status = ? THEN 1 ELSE 0 END as is_active FROM users', $sql);
        $this->assertEquals(['active'], $params);
    }

    public function testWhereRawAddsRawCondition(): void
    {
        $qb = $this->qb
            ->select('*')
            ->from('products')
            ->whereRaw('price > ? AND category IN (?, ?)', [100, 'electronics', 'books']);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT * FROM products WHERE price > ? AND category IN (?, ?)', $sql);
        $this->assertEquals([100, 'electronics', 'books'], $params);
    }

    public function testWhereRawWithComplexExpression(): void
    {
        $qb = $this->qb
            ->select('*')
            ->from('orders')
            ->whereRaw('(status = ? OR status = ?) AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)', ['pending', 'processing', 7]);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $expected = 'SELECT * FROM orders WHERE (status = ? OR status = ?) AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';
        $this->assertEquals($expected, $sql);
        $this->assertEquals(['pending', 'processing', 7], $params);
    }

    public function testMixRawAndFluentMethods(): void
    {
        $qb = $this->qb
            ->select('id', 'name')
            ->selectRaw('JSON_EXTRACT(metadata, "$.tags") as tags')
            ->from('products')
            ->where('active = ?', true)
            ->whereRaw('JSON_CONTAINS(metadata, ?, "$.categories")', ['electronics'])
            ->orderBy('name');

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $expected = 'SELECT id, name, JSON_EXTRACT(metadata, "$.tags") as tags FROM products WHERE active = ? AND JSON_CONTAINS(metadata, ?, "$.categories") ORDER BY name ASC';
        $this->assertEquals($expected, $sql);
        $this->assertEquals([true, 'electronics'], $params);
    }

    public function testParameterOrderWithMixedRawAndFluent(): void
    {
        $qb = $this->qb
            ->select('*')
            ->from('users')
            ->where('active = ?', true)           // Parameter 1
            ->whereRaw('age > ?', 18)             // Parameter 2
            ->where('role = ?', 'admin')          // Parameter 3
            ->whereRaw('last_login > ?', '2023-01-01'); // Parameter 4

        $params = $qb->getParameters();

        // Parameters should be in the order they appear in the query
        $this->assertEquals([true, 18, 'admin', '2023-01-01'], $params);
    }

    public function testRawReturnsRawData(): void
    {
        $statement = $this->createMock(\PDOStatement::class);
        $statement->method('fetchAll')->willReturn([
            ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
        ]);

        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->willReturn($statement);

        // Raw queries return raw data, no hydration
        $qb = $this->qb->raw('SELECT * FROM users WHERE id = ?', [1]);

        $result = $qb->getResult();

        $this->assertEquals([['id' => 1, 'name' => 'John', 'email' => 'john@example.com']], $result);
    }

    public function testRawReturnsArrayWhenNoHydration(): void
    {
        $statement = $this->createMock(\PDOStatement::class);
        $statement->method('fetchAll')->willReturn([
            ['id' => 1, 'name' => 'John'],
        ]);

        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->willReturn($statement);

        $qb = $this->qb->raw('SELECT id, name FROM users');
        $result = $qb->getResult();

        $this->assertEquals([['id' => 1, 'name' => 'John']], $result);
    }

    public function testRawWithEmptyParameters(): void
    {
        $qb = $this->qb->raw('SELECT COUNT(*) FROM users');

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT COUNT(*) FROM users', $sql);
        $this->assertEquals([], $params);
    }

    public function testSqlInjectionPrevention(): void
    {
        // Test that raw queries still use parameterized queries
        $maliciousInput = "'; DROP TABLE users; --";

        $qb = $this->qb->raw('SELECT * FROM users WHERE name = ?', [$maliciousInput]);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        // SQL should not contain the malicious input directly
        $this->assertEquals('SELECT * FROM users WHERE name = ?', $sql);
        $this->assertEquals([$maliciousInput], $params);
        // The malicious input should be safely in the parameters array
    }

    public function testRawQueryWithLimitAndOffset(): void
    {
        // Test that raw queries can work with LIMIT/OFFSET if needed
        $qb = $this->qb
            ->raw('SELECT * FROM products ORDER BY price DESC')
            ->limit(10)
            ->offset(20);

        $sql = $qb->getSQL();

        // Raw query should be replaced entirely, so LIMIT/OFFSET from fluent API should not apply
        $this->assertEquals('SELECT * FROM products ORDER BY price DESC', $sql);
    }
}
