<?php

namespace Articulate\Tests\Modules\QueryBuilder;

use Articulate\Connection;
use Articulate\Modules\QueryBuilder\QueryBuilder;
use Articulate\Tests\DatabaseTestCase;

class WhereClauseTest extends DatabaseTestCase {
    private QueryBuilder $qb;

    private Connection $connection;

    /**
     * @dataProvider databaseProvider
     */
    public function testWhereAlwaysAddsConditions(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $qb = $this->qb
            ->select('*')
            ->from('users')
            ->where('status = ?', 'active')
            ->where('deleted_at IS NULL');

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT * FROM users WHERE status = ? AND deleted_at IS NULL', $sql);
        $this->assertEquals(['active'], $params);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testWhereWithMultipleCallsCreatesAndConditions(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);
        $qb = $this->qb
            ->select('id', 'name')
            ->from('users')
            ->where('active = ?', true)
            ->where('age > ?', 18)
            ->where('role IN (?)', ['admin', 'moderator']);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT id, name FROM users WHERE active = ? AND age > ? AND role IN (?)', $sql);
        $this->assertEquals([true, 18, ['admin', 'moderator']], $params);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testOrWhereCreatesOrConditions(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);
        $qb = $this->qb
            ->select('*')
            ->from('users')
            ->where('status = ?', 'active')
            ->orWhere('status = ?', 'pending');

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT * FROM users WHERE status = ? OR status = ?', $sql);
        $this->assertEquals(['active', 'pending'], $params);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testWhereInWithSingleValue(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);
        $qb = $this->qb
            ->select('id', 'name')
            ->from('users')
            ->whereIn('id', [5]);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT id, name FROM users WHERE id IN (?)', $sql);
        $this->assertEquals([[5]], $params);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testWhereInWithMultipleValues(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);
        $qb = $this->qb
            ->select('*')
            ->from('users')
            ->whereIn('role', ['admin', 'moderator', 'user']);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT * FROM users WHERE role IN (?)', $sql);
        $this->assertEquals([['admin', 'moderator', 'user']], $params);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testWhereInWithEmptyArray(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);
        $qb = $this->qb
            ->select('*')
            ->from('users')
            ->whereIn('id', []);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        // Empty IN clause should generate a condition that never matches
        $this->assertEquals('SELECT * FROM users WHERE 1 = 0', $sql);
        $this->assertEquals([], $params);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testWhereNotIn(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);
        $qb = $this->qb
            ->select('id', 'name')
            ->from('users')
            ->whereNotIn('status', ['banned', 'suspended']);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT id, name FROM users WHERE status NOT IN (?)', $sql);
        $this->assertEquals([['banned', 'suspended']], $params);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testWhereNotInWithEmptyArray(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);
        $qb = $this->qb
            ->select('*')
            ->from('users')
            ->whereNotIn('role', []);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        // Empty NOT IN clause should generate a condition that always matches
        $this->assertEquals('SELECT * FROM users WHERE 1 = 1', $sql);
        $this->assertEquals([], $params);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testWhereNull(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);
        $qb = $this->qb
            ->select('id', 'name')
            ->from('users')
            ->whereNull('deleted_at');

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT id, name FROM users WHERE deleted_at IS NULL', $sql);
        $this->assertEquals([], $params);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testWhereNotNull(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);
        $qb = $this->qb
            ->select('*')
            ->from('users')
            ->whereNotNull('email_verified_at');

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT * FROM users WHERE email_verified_at IS NOT NULL', $sql);
        $this->assertEquals([], $params);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testWhereBetween(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);
        $qb = $this->qb
            ->select('id', 'name')
            ->from('users')
            ->whereBetween('age', 18, 65);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT id, name FROM users WHERE age BETWEEN ? AND ?', $sql);
        $this->assertEquals([18, 65], $params);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testWhereBetweenWithEqualMinMax(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);
        $qb = $this->qb
            ->select('*')
            ->from('products')
            ->whereBetween('price', 100, 100);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT * FROM products WHERE price BETWEEN ? AND ?', $sql);
        $this->assertEquals([100, 100], $params);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testComplexWhereConditions(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);
        $qb = $this->qb
            ->select('u.id', 'u.name', 'u.email')
            ->from('users', 'u')
            ->where('u.active = ?', true)
            ->whereIn('u.role', ['admin', 'moderator'])
            ->whereNotNull('u.email_verified_at')
            ->whereBetween('u.created_at', '2023-01-01', '2023-12-31');

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $expected = 'SELECT u.id, u.name, u.email FROM users u WHERE u.active = ? AND u.role IN (?) AND u.email_verified_at IS NOT NULL AND u.created_at BETWEEN ? AND ?';
        $this->assertEquals($expected, $sql);
        $this->assertEquals([true, ['admin', 'moderator'], '2023-01-01', '2023-12-31'], $params);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testWhereWithSpecialCharacters(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);
        $qb = $this->qb
            ->select('*')
            ->from('users')
            ->where('`user-name` = ?', 'john_doe');

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT * FROM users WHERE `user-name` = ?', $sql);
        $this->assertEquals(['john_doe'], $params);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testWhereWithReservedKeywords(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);
        $qb = $this->qb
            ->select('*')
            ->from('users')
            ->where('`order` = ?', 'asc');

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT * FROM users WHERE `order` = ?', $sql);
        $this->assertEquals(['asc'], $params);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testWhereWithUnicodeCharacters(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);
        $qb = $this->qb
            ->select('*')
            ->from('users')
            ->where('name = ?', 'José María');

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT * FROM users WHERE name = ?', $sql);
        $this->assertEquals(['José María'], $params);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testWhereWithZeroValues(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);
        $qb = $this->qb
            ->select('*')
            ->from('products')
            ->where('price = ?', 0)
            ->where('quantity = ?', 0);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT * FROM products WHERE price = ? AND quantity = ?', $sql);
        $this->assertEquals([0, 0], $params);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testWhereWithBooleanValues(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);
        $qb = $this->qb
            ->select('*')
            ->from('users')
            ->where('active = ?', true)
            ->where('admin = ?', false);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT * FROM users WHERE active = ? AND admin = ?', $sql);
        $this->assertEquals([true, false], $params);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testWhereWithDateTimeObjects(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);
        $date = new \DateTime('2023-01-01 12:00:00');

        $qb = $this->qb
            ->select('*')
            ->from('posts')
            ->where('published_at > ?', $date);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT * FROM posts WHERE published_at > ?', $sql);
        $this->assertEquals([$date], $params);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testSqlInjectionPrevention(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);
        // Test that parameters prevent SQL injection
        $maliciousInput = "'; DROP TABLE users; --";

        $qb = $this->qb
            ->select('*')
            ->from('users')
            ->where('name = ?', $maliciousInput);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        // SQL should not contain the malicious input directly
        $this->assertEquals('SELECT * FROM users WHERE name = ?', $sql);
        $this->assertEquals([$maliciousInput], $params);
        // The malicious input should be safely in the parameters array
    }
}
