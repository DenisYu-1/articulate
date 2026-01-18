<?php

namespace Articulate\Tests\Modules\QueryBuilder;

use Articulate\Connection;
use Articulate\Modules\QueryBuilder\QueryBuilder;
use PHPUnit\Framework\TestCase;

class HavingClauseTest extends TestCase {
    private QueryBuilder $qb;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->qb = new QueryBuilder($this->connection);
    }

    public function testHavingWithAggregateFunction(): void
    {
        $qb = $this->qb
            ->select('category')
            ->count('*', 'total_products')
            ->from('products')
            ->groupBy('category')
            ->having('total_products > ?', 5);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $expected = 'SELECT category, COUNT(*) as total_products FROM products GROUP BY category HAVING total_products > ?';
        $this->assertEquals($expected, $sql);
        $this->assertEquals([5], $params);
    }

    public function testMultipleHavingCalls(): void
    {
        $qb = $this->qb
            ->select('category')
            ->count('*', 'total_products')
            ->sum('price', 'total_value')
            ->from('products')
            ->groupBy('category')
            ->having('total_products > ?', 5)
            ->having('total_value < ?', 1000);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $expected = 'SELECT category, COUNT(*) as total_products, SUM(price) as total_value FROM products GROUP BY category HAVING total_products > ? AND total_value < ?';
        $this->assertEquals($expected, $sql);
        $this->assertEquals([5, 1000], $params);
    }

    public function testOrHaving(): void
    {
        $qb = $this->qb
            ->select('category')
            ->count('*', 'total_products')
            ->from('products')
            ->groupBy('category')
            ->having('total_products > ?', 10)
            ->orHaving('total_products = ?', 0);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $expected = 'SELECT category, COUNT(*) as total_products FROM products GROUP BY category HAVING total_products > ? OR total_products = ?';
        $this->assertEquals($expected, $sql);
        $this->assertEquals([10, 0], $params);
    }

    public function testHavingWithoutGroupBy(): void
    {
        $qb = $this->qb
            ->count('*', 'total_products')
            ->from('products')
            ->having('total_products > ?', 100);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT COUNT(*) as total_products FROM products HAVING total_products > ?', $sql);
        $this->assertEquals([100], $params);
    }

    public function testComplexHavingWithSubqueryReference(): void
    {
        $qb = $this->qb
            ->select('category')
            ->count('*', 'total_products')
            ->max('price', 'max_price')
            ->from('products')
            ->groupBy('category')
            ->having('total_products >= ?', 10)
            ->having('max_price <= ?', 500);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $expected = 'SELECT category, COUNT(*) as total_products, MAX(price) as max_price FROM products GROUP BY category HAVING total_products >= ? AND max_price <= ?';
        $this->assertEquals($expected, $sql);
        $this->assertEquals([10, 500], $params);
    }

    public function testHavingWithMathematicalExpressions(): void
    {
        $qb = $this->qb
            ->select('category')
            ->sum('price', 'total_value')
            ->count('*', 'product_count')
            ->from('products')
            ->groupBy('category')
            ->having('total_value / product_count > ?', 50);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $expected = 'SELECT category, SUM(price) as total_value, COUNT(*) as product_count FROM products GROUP BY category HAVING total_value / product_count > ?';
        $this->assertEquals($expected, $sql);
        $this->assertEquals([50], $params);
    }

    public function testHavingWithBetween(): void
    {
        $qb = $this->qb
            ->select('category')
            ->avg('rating', 'avg_rating')
            ->from('products')
            ->groupBy('category')
            ->having('avg_rating BETWEEN ? AND ?', 3.5, 5.0);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $expected = 'SELECT category, AVG(rating) as avg_rating FROM products GROUP BY category HAVING avg_rating BETWEEN ? AND ?';
        $this->assertEquals($expected, $sql);
        $this->assertEquals([3.5, 5.0], $params);
    }

    public function testHavingWithNullChecks(): void
    {
        $qb = $this->qb
            ->select('category')
            ->count('*', 'total_products')
            ->from('products')
            ->groupBy('category')
            ->having('total_products IS NOT NULL');

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $expected = 'SELECT category, COUNT(*) as total_products FROM products GROUP BY category HAVING total_products IS NOT NULL';
        $this->assertEquals($expected, $sql);
        $this->assertEquals([], $params);
    }

    public function testMixedHavingAndOrHaving(): void
    {
        $qb = $this->qb
            ->select('category')
            ->count('*', 'total_products')
            ->sum('price', 'total_value')
            ->from('products')
            ->groupBy('category')
            ->having('total_products > ?', 5)
            ->having('total_value > ?', 100)
            ->orHaving('category = ?', 'premium');

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $expected = 'SELECT category, COUNT(*) as total_products, SUM(price) as total_value FROM products GROUP BY category HAVING total_products > ? AND total_value > ? OR category = ?';
        $this->assertEquals($expected, $sql);
        $this->assertEquals([5, 100, 'premium'], $params);
    }

    public function testHavingParameterOrder(): void
    {
        $qb = $this->qb
            ->select('category')
            ->count('*', 'count1')
            ->count('id', 'count2')
            ->from('products')
            ->where('active = ?', true)
            ->groupBy('category')
            ->having('count1 > ?', 10)
            ->having('count2 < ?', 50);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        // WHERE parameter comes first, then HAVING parameters
        $expected = 'SELECT category, COUNT(*) as count1, COUNT(id) as count2 FROM products WHERE active = ? GROUP BY category HAVING count1 > ? AND count2 < ?';
        $this->assertEquals($expected, $sql);
        $this->assertEquals([true, 10, 50], $params);
    }
}
