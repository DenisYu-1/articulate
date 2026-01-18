<?php

namespace Articulate\Tests\Modules\QueryBuilder;

use Articulate\Connection;
use Articulate\Modules\QueryBuilder\QueryBuilder;
use PHPUnit\Framework\TestCase;

class AggregateTest extends TestCase {
    private QueryBuilder $qb;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->qb = new QueryBuilder($this->connection);
    }

    public function testCountWithColumnName(): void
    {
        $qb = $this->qb
            ->select('category')
            ->count('id')
            ->from('products')
            ->groupBy('category');

        $sql = $qb->getSQL();

        $this->assertEquals('SELECT category, COUNT(id) FROM products GROUP BY category', $sql);
    }

    public function testCountWithStar(): void
    {
        $qb = $this->qb
            ->select('category')
            ->count('*')
            ->from('products')
            ->groupBy('category');

        $sql = $qb->getSQL();

        $this->assertEquals('SELECT category, COUNT(*) FROM products GROUP BY category', $sql);
    }

    public function testCountWithAlias(): void
    {
        $qb = $this->qb
            ->select('category')
            ->count('id', 'total_count')
            ->from('products')
            ->groupBy('category');

        $sql = $qb->getSQL();

        $this->assertEquals('SELECT category, COUNT(id) as total_count FROM products GROUP BY category', $sql);
    }

    public function testCountDefaultStar(): void
    {
        $qb = $this->qb
            ->select('category')
            ->count()  // Should default to *
            ->from('products')
            ->groupBy('category');

        $sql = $qb->getSQL();

        $this->assertEquals('SELECT category, COUNT(*) FROM products GROUP BY category', $sql);
    }

    public function testSum(): void
    {
        $qb = $this->qb
            ->select('category')
            ->sum('price')
            ->from('products')
            ->groupBy('category');

        $sql = $qb->getSQL();

        $this->assertEquals('SELECT category, SUM(price) FROM products GROUP BY category', $sql);
    }

    public function testSumWithAlias(): void
    {
        $qb = $this->qb
            ->select('category')
            ->sum('price', 'total_price')
            ->from('products')
            ->groupBy('category');

        $sql = $qb->getSQL();

        $this->assertEquals('SELECT category, SUM(price) as total_price FROM products GROUP BY category', $sql);
    }

    public function testAvg(): void
    {
        $qb = $this->qb
            ->select('category')
            ->avg('rating')
            ->from('products')
            ->groupBy('category');

        $sql = $qb->getSQL();

        $this->assertEquals('SELECT category, AVG(rating) FROM products GROUP BY category', $sql);
    }

    public function testAvgWithAlias(): void
    {
        $qb = $this->qb
            ->select('category')
            ->avg('rating', 'avg_rating')
            ->from('products')
            ->groupBy('category');

        $sql = $qb->getSQL();

        $this->assertEquals('SELECT category, AVG(rating) as avg_rating FROM products GROUP BY category', $sql);
    }

    public function testMax(): void
    {
        $qb = $this->qb
            ->select('category')
            ->max('price')
            ->from('products')
            ->groupBy('category');

        $sql = $qb->getSQL();

        $this->assertEquals('SELECT category, MAX(price) FROM products GROUP BY category', $sql);
    }

    public function testMaxWithAlias(): void
    {
        $qb = $this->qb
            ->select('category')
            ->max('price', 'highest_price')
            ->from('products')
            ->groupBy('category');

        $sql = $qb->getSQL();

        $this->assertEquals('SELECT category, MAX(price) as highest_price FROM products GROUP BY category', $sql);
    }

    public function testMin(): void
    {
        $qb = $this->qb
            ->select('category')
            ->min('price')
            ->from('products')
            ->groupBy('category');

        $sql = $qb->getSQL();

        $this->assertEquals('SELECT category, MIN(price) FROM products GROUP BY category', $sql);
    }

    public function testMinWithAlias(): void
    {
        $qb = $this->qb
            ->select('category')
            ->min('price', 'lowest_price')
            ->from('products')
            ->groupBy('category');

        $sql = $qb->getSQL();

        $this->assertEquals('SELECT category, MIN(price) as lowest_price FROM products GROUP BY category', $sql);
    }

    public function testMultipleAggregates(): void
    {
        $qb = $this->qb
            ->select('category')
            ->count('*', 'total_products')
            ->sum('price', 'total_value')
            ->avg('rating', 'avg_rating')
            ->max('price', 'max_price')
            ->min('price', 'min_price')
            ->from('products')
            ->groupBy('category');

        $sql = $qb->getSQL();

        $expected = 'SELECT category, COUNT(*) as total_products, SUM(price) as total_value, AVG(rating) as avg_rating, MAX(price) as max_price, MIN(price) as min_price FROM products GROUP BY category';
        $this->assertEquals($expected, $sql);
    }

    public function testDistinct(): void
    {
        $qb = $this->qb
            ->distinct()
            ->select('category')
            ->from('products');

        $sql = $qb->getSQL();

        $this->assertEquals('SELECT DISTINCT category FROM products', $sql);
    }

    public function testDistinctWithMultipleColumns(): void
    {
        $qb = $this->qb
            ->distinct()
            ->select('category', 'brand')
            ->from('products');

        $sql = $qb->getSQL();

        $this->assertEquals('SELECT DISTINCT category, brand FROM products', $sql);
    }

    public function testAggregatesWithoutGroupBy(): void
    {
        $qb = $this->qb
            ->count('*', 'total_products')
            ->sum('price', 'total_value')
            ->from('products');

        $sql = $qb->getSQL();

        $this->assertEquals('SELECT COUNT(*) as total_products, SUM(price) as total_value FROM products', $sql);
    }

    public function testAggregatesWithWhere(): void
    {
        $qb = $this->qb
            ->select('category')
            ->count('*', 'active_products')
            ->from('products')
            ->where('active = ?', true)
            ->groupBy('category');

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT category, COUNT(*) as active_products FROM products WHERE active = ? GROUP BY category', $sql);
        $this->assertEquals([true], $params);
    }
}
