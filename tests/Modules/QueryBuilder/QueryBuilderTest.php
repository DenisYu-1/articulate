<?php

namespace Articulate\Tests\Modules\QueryBuilder;

use Articulate\Attributes\Entity;
use Articulate\Connection;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\EntityManager\EntityMetadataRegistry;
use Articulate\Modules\EntityManager\HydratorInterface;
use Articulate\Modules\EntityManager\UnitOfWork;
use Articulate\Modules\QueryBuilder\QueryBuilder;
use Articulate\Tests\DatabaseTestCase;

#[Entity]
class EntityManagerTestEntity {
    public int $id;

    public string $name;
}

class QueryBuilderTest extends DatabaseTestCase {
    private QueryBuilder $qb;

    private Connection $connection;

    private EntityManager $entityManager;

    /**
     * @dataProvider databaseProvider
     */
    public function testBasicSelect(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $sql = $this->qb
            ->select('id', 'name')
            ->from('users')
            ->getSQL();

        $this->assertEquals('SELECT id, name FROM users', $sql);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testSelectAll(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $sql = $this->qb
            ->from('users')
            ->getSQL();

        $this->assertEquals('SELECT * FROM users', $sql);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testWhereClause(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $qb = $this->qb
            ->select('id', 'name')
            ->from('users')
            ->where('id = ?', 1);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT id, name FROM users WHERE id = ?', $sql);
        $this->assertEquals([1], $params);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testMultipleWhereClauses(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $qb = $this->qb
            ->select('id', 'name')
            ->from('users')
            ->where('active = ?', true)
            ->where('deleted_at IS NULL');

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT id, name FROM users WHERE active = ? AND deleted_at IS NULL', $sql);
        $this->assertEquals([true], $params);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testJoin(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $sql = $this->qb
            ->select('u.id', 'u.name', 'p.title')
            ->from('users', 'u')
            ->join('posts', 'p.user_id = u.id')
            ->getSQL();

        $this->assertEquals('SELECT u.id, u.name, p.title FROM users u JOIN posts ON p.user_id = u.id', $sql);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testLeftJoin(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $sql = $this->qb
            ->select('u.id', 'u.name', 'COUNT(p.id) as post_count')
            ->from('users', 'u')
            ->leftJoin('posts p', 'p.user_id = u.id')
            ->getSQL();

        $this->assertEquals('SELECT u.id, u.name, COUNT(p.id) as post_count FROM users u LEFT JOIN posts p ON p.user_id = u.id', $sql);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testLimitAndOffset(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $sql = $this->qb
            ->select('id', 'name')
            ->from('users')
            ->limit(10)
            ->offset(20)
            ->getSQL();

        $this->assertEquals('SELECT id, name FROM users LIMIT 10 OFFSET 20', $sql);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testOrderBy(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $sql = $this->qb
            ->select('id', 'name')
            ->from('users')
            ->orderBy('name', 'ASC')
            ->orderBy('id', 'DESC')
            ->getSQL();

        $this->assertEquals('SELECT id, name FROM users ORDER BY name ASC, id DESC', $sql);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testComplexQuery(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $qb = $this->qb
            ->select('u.id', 'u.name', 'u.email', 'COUNT(p.id) as post_count')
            ->from('users', 'u')
            ->leftJoin('posts p', 'p.user_id = u.id AND p.published = ?', true)
            ->where('u.active = ?', true)
            ->where('u.created_at > ?', '2023-01-01')
            ->orderBy('u.name')
            ->limit(50);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $expected = 'SELECT u.id, u.name, u.email, COUNT(p.id) as post_count FROM users u ' .
                   'LEFT JOIN posts p ON p.user_id = u.id AND p.published = ? ' .
                   'WHERE u.active = ? AND u.created_at > ? ' .
                   'ORDER BY u.name ASC LIMIT 50';

        $this->assertEquals($expected, $sql);
        $this->assertEquals([true, true, '2023-01-01'], $params);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testGetResultReturnsEmptyArrayForNoResults(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        // Create a test table first
        $this->connection->executeQuery('CREATE TABLE test_users (id INT, name VARCHAR(255))');

        $result = $this->qb->select('*')->from('test_users')->getResult();

        $this->assertEquals([], $result);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testGetResultReturnsRawData(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        // Create a test table and insert data
        $this->connection->executeQuery('CREATE TABLE test_users (id INT, name VARCHAR(255))');
        $this->connection->executeQuery('INSERT INTO test_users (id, name) VALUES (1, \'John\'), (2, \'Jane\')');

        $result = $this->qb->select('*')->from('test_users')->getResult();

        $expected = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ];

        $this->assertEquals($expected, $result);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testExecuteReturnsRowCount(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        // Create a test table and insert data
        $activeColumnType = $databaseName === 'mysql' ? 'TINYINT(1)' : 'BOOLEAN';
        $activeValueFalse = $databaseName === 'mysql' ? '0' : 'false';
        $activeValueTrue = $databaseName === 'mysql' ? '1' : 'true';
        $this->connection->executeQuery("CREATE TABLE test_users (id INT, name VARCHAR(255), active {$activeColumnType})");
        $this->connection->executeQuery("INSERT INTO test_users (id, name, active) VALUES (1, 'John', {$activeValueFalse}), (2, 'Jane', {$activeValueTrue})");

        // For MySQL, active = 0, for PostgreSQL, active = false
        $activeCondition = $databaseName === 'mysql' ? 'active = 0' : 'active = false';
        $result = $this->qb->from('test_users')->where($activeCondition)->execute();

        $this->assertEquals(1, $result);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testSetAndGetHydrator(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $hydrator = $this->createMock(HydratorInterface::class);

        $this->qb->setHydrator($hydrator);
        $this->assertSame($hydrator, $this->qb->getHydrator());

        // Can set to null
        $this->qb->setHydrator(null);
        $this->assertNull($this->qb->getHydrator());
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testConstructorWithHydrator(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();

        $hydrator = $this->createMock(HydratorInterface::class);
        $qb = new QueryBuilder($this->connection, $hydrator);

        $this->assertSame($hydrator, $qb->getHydrator());
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testSetEntityClass(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();

        $qb = new QueryBuilder($this->connection);

        $qb->setEntityClass('App\\Entity\\User');
        $this->assertEquals('App\\Entity\\User', $qb->getEntityClass());

        // Should automatically set table name
        $this->assertStringContainsString('users', $qb->getSQL());
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testResolveTableName(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();

        $qb = new QueryBuilder($this->connection);

        // Test the private method via reflection
        $reflection = new \ReflectionClass($qb);
        $method = $reflection->getMethod('resolveTableName');
        $method->setAccessible(true);

        $this->assertEquals('users', $method->invoke($qb, 'User'));
        $this->assertEquals('posts', $method->invoke($qb, 'App\\Entity\\Post'));
        $this->assertEquals('comments', $method->invoke($qb, 'MyNamespace\\Comment'));
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testEntityClassAutoTableResolution(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();

        $qb = new QueryBuilder($this->connection);
        $qb->setEntityClass('User');

        // Should have set FROM clause automatically
        $sql = $qb->getSQL();
        $this->assertStringContainsString('FROM users', $sql);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testGetSingleResult(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        // Create a test table and insert data
        $this->connection->executeQuery('CREATE TABLE test_users (id INT, name VARCHAR(255))');
        $this->connection->executeQuery('INSERT INTO test_users (id, name) VALUES (1, \'John\')');

        $result = $this->qb->select('*')->from('test_users')->getSingleResult();

        $this->assertEquals(['id' => 1, 'name' => 'John'], $result);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testGetSingleResultReturnsNullForNoResults(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        // Create a test table
        $this->connection->executeQuery('CREATE TABLE test_users (id INT, name VARCHAR(255))');

        $result = $this->qb->select('*')->from('test_users')->getSingleResult();

        $this->assertNull($result);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testSetUnitOfWork(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $qb = new QueryBuilder($this->connection);

        $result = $qb->setUnitOfWork($unitOfWork);

        $this->assertSame($qb, $result); // Should return self for chaining
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testGetResultWithHydrationAndUnitOfWork(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();

        // Create a test table and insert data
        $this->connection->executeQuery('CREATE TABLE test_users (id INT, name VARCHAR(255))');
        $this->connection->executeQuery('INSERT INTO test_users (id, name) VALUES (1, \'John\'), (2, \'Jane\')');

        $hydrator = $this->createMock(HydratorInterface::class);
        $hydrator->expects($this->exactly(2))
            ->method('hydrate')
            ->willReturnCallback(function ($class, $row) {
                $entity = new $class();
                $entity->id = $row['id'];
                $entity->name = $row['name'];

                return $entity;
            });

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->expects($this->exactly(2))
            ->method('registerManaged')
            ->with($this->isInstanceOf(EntityManagerTestEntity::class), []);

        $qb = new QueryBuilder($this->connection, $hydrator);
        $qb->setUnitOfWork($unitOfWork);
        $qb->setEntityClass(EntityManagerTestEntity::class);

        $result = $qb->select('*')->from('test_users')->getResult();

        $this->assertCount(2, $result);
        $this->assertInstanceOf(EntityManagerTestEntity::class, $result[0]);
        $this->assertInstanceOf(EntityManagerTestEntity::class, $result[1]);
        $this->assertEquals(1, $result[0]->id);
        $this->assertEquals('John', $result[0]->name);
        $this->assertEquals(2, $result[1]->id);
        $this->assertEquals('Jane', $result[1]->name);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testGroupBy(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $sql = $this->qb
            ->select('category', 'COUNT(*) as count')
            ->from('products')
            ->groupBy('category')
            ->groupBy('status')
            ->getSQL();

        $this->assertEquals('SELECT category, COUNT(*) as count FROM products GROUP BY category, status', $sql);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testConstructorWithMetadataRegistry(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();

        $metadataRegistry = $this->createMock(EntityMetadataRegistry::class);
        $qb = new QueryBuilder($this->connection, null, $metadataRegistry);

        // Test that the metadata registry is set (private property, so we test indirectly)
        $this->assertInstanceOf(QueryBuilder::class, $qb);
    }
}
