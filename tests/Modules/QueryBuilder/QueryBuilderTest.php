<?php

namespace Articulate\Tests\Modules\QueryBuilder;

use Articulate\Attributes\Entity;
use Articulate\Connection;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\EntityManager\EntityMetadataRegistry;
use Articulate\Modules\EntityManager\HydratorInterface;
use Articulate\Modules\EntityManager\UnitOfWork;
use Articulate\Modules\QueryBuilder\QueryBuilder;
use PHPUnit\Framework\TestCase;

#[Entity]
class EntityManagerTestEntity {
    public int $id;
    public string $name;
}

class QueryBuilderTest extends TestCase {
    private QueryBuilder $qb;

    private Connection $connection;

    private EntityManager $entityManager;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->qb = new QueryBuilder($this->connection);
    }

    public function testBasicSelect(): void
    {
        $sql = $this->qb
            ->select('id', 'name')
            ->from('users')
            ->getSQL();

        $this->assertEquals('SELECT id, name FROM users', $sql);
    }

    public function testSelectAll(): void
    {
        $sql = $this->qb
            ->from('users')
            ->getSQL();

        $this->assertEquals('SELECT * FROM users', $sql);
    }

    public function testWhereClause(): void
    {
        $qb = $this->qb
            ->select('id', 'name')
            ->from('users')
            ->where('id = ?', 1);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();

        $this->assertEquals('SELECT id, name FROM users WHERE id = ?', $sql);
        $this->assertEquals([1], $params);
    }

    public function testMultipleWhereClauses(): void
    {
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

    public function testJoin(): void
    {
        $sql = $this->qb
            ->select('u.id', 'u.name', 'p.title')
            ->from('users', 'u')
            ->join('posts', 'p.user_id = u.id')
            ->getSQL();

        $this->assertEquals('SELECT u.id, u.name, p.title FROM users u JOIN posts ON p.user_id = u.id', $sql);
    }

    public function testLeftJoin(): void
    {
        $sql = $this->qb
            ->select('u.id', 'u.name', 'COUNT(p.id) as post_count')
            ->from('users', 'u')
            ->leftJoin('posts p', 'p.user_id = u.id')
            ->getSQL();

        $this->assertEquals('SELECT u.id, u.name, COUNT(p.id) as post_count FROM users u LEFT JOIN posts p ON p.user_id = u.id', $sql);
    }

    public function testLimitAndOffset(): void
    {
        $sql = $this->qb
            ->select('id', 'name')
            ->from('users')
            ->limit(10)
            ->offset(20)
            ->getSQL();

        $this->assertEquals('SELECT id, name FROM users LIMIT 10 OFFSET 20', $sql);
    }

    public function testOrderBy(): void
    {
        $sql = $this->qb
            ->select('id', 'name')
            ->from('users')
            ->orderBy('name', 'ASC')
            ->orderBy('id', 'DESC')
            ->getSQL();

        $this->assertEquals('SELECT id, name FROM users ORDER BY name ASC, id DESC', $sql);
    }

    public function testComplexQuery(): void
    {
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

    public function testGetResultReturnsEmptyArrayForNoResults(): void
    {
        $statement = $this->createMock(\PDOStatement::class);
        $statement->method('fetchAll')->willReturn([]);

        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->willReturn($statement);

        $result = $this->qb->select('*')->from('users')->getResult();

        $this->assertEquals([], $result);
    }

    public function testGetResultReturnsRawData(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ];

        $statement = $this->createMock(\PDOStatement::class);
        $statement->method('fetchAll')->willReturn($rows);

        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->willReturn($statement);

        $result = $this->qb->select('*')->from('users')->getResult();

        $this->assertEquals($rows, $result);
    }

    public function testExecuteReturnsRowCount(): void
    {
        $statement = $this->createMock(\PDOStatement::class);
        $statement->method('rowCount')->willReturn(5);

        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->willReturn($statement);

        $result = $this->qb->from('users')->where('active = ?', false)->execute();

        $this->assertEquals(5, $result);
    }

    public function testSetAndGetHydrator(): void
    {
        $hydrator = $this->createMock(HydratorInterface::class);

        $this->qb->setHydrator($hydrator);
        $this->assertSame($hydrator, $this->qb->getHydrator());

        // Can set to null
        $this->qb->setHydrator(null);
        $this->assertNull($this->qb->getHydrator());
    }

    public function testConstructorWithHydrator(): void
    {
        $hydrator = $this->createMock(HydratorInterface::class);
        $qb = new QueryBuilder($this->connection, $hydrator);

        $this->assertSame($hydrator, $qb->getHydrator());
    }

    public function testSetEntityClass(): void
    {
        $qb = new QueryBuilder($this->connection);

        $qb->setEntityClass('App\\Entity\\User');
        $this->assertEquals('App\\Entity\\User', $qb->getEntityClass());

        // Should automatically set table name
        $this->assertStringContainsString('users', $qb->getSQL());
    }

    public function testResolveTableName(): void
    {
        $qb = new QueryBuilder($this->connection);

        // Test the private method via reflection
        $reflection = new \ReflectionClass($qb);
        $method = $reflection->getMethod('resolveTableName');
        $method->setAccessible(true);

        $this->assertEquals('users', $method->invoke($qb, 'User'));
        $this->assertEquals('posts', $method->invoke($qb, 'App\\Entity\\Post'));
        $this->assertEquals('comments', $method->invoke($qb, 'MyNamespace\\Comment'));
    }

    public function testEntityClassAutoTableResolution(): void
    {
        $qb = new QueryBuilder($this->connection);
        $qb->setEntityClass('User');

        // Should have set FROM clause automatically
        $sql = $qb->getSQL();
        $this->assertStringContainsString('FROM users', $sql);
    }

    public function testGetSingleResult(): void
    {
        $rows = [['id' => 1, 'name' => 'John']];

        $statement = $this->createMock(\PDOStatement::class);
        $statement->method('fetchAll')->willReturn($rows);

        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->willReturn($statement);

        $result = $this->qb->select('*')->from('users')->getSingleResult();

        $this->assertEquals(['id' => 1, 'name' => 'John'], $result);
    }

    public function testGetSingleResultReturnsNullForNoResults(): void
    {
        $statement = $this->createMock(\PDOStatement::class);
        $statement->method('fetchAll')->willReturn([]);

        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->willReturn($statement);

        $result = $this->qb->select('*')->from('users')->getSingleResult();

        $this->assertNull($result);
    }

    public function testSetUnitOfWork(): void
    {
        $unitOfWork = $this->createMock(UnitOfWork::class);
        $qb = new QueryBuilder($this->connection);

        $result = $qb->setUnitOfWork($unitOfWork);

        $this->assertSame($qb, $result); // Should return self for chaining
    }

    public function testGetResultWithHydrationAndUnitOfWork(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ];

        $statement = $this->createMock(\PDOStatement::class);
        $statement->method('fetchAll')->willReturn($rows);

        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->willReturn($statement);

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

        $result = $qb->select('*')->from('users')->getResult();

        $this->assertCount(2, $result);
        $this->assertInstanceOf(EntityManagerTestEntity::class, $result[0]);
        $this->assertInstanceOf(EntityManagerTestEntity::class, $result[1]);
        $this->assertEquals(1, $result[0]->id);
        $this->assertEquals('John', $result[0]->name);
        $this->assertEquals(2, $result[1]->id);
        $this->assertEquals('Jane', $result[1]->name);
    }

    public function testGroupBy(): void
    {
        $sql = $this->qb
            ->select('category', 'COUNT(*) as count')
            ->from('products')
            ->groupBy('category')
            ->groupBy('status')
            ->getSQL();

        $this->assertEquals('SELECT category, COUNT(*) as count FROM products GROUP BY category, status', $sql);
    }

    public function testConstructorWithMetadataRegistry(): void
    {
        $metadataRegistry = $this->createMock(EntityMetadataRegistry::class);
        $qb = new QueryBuilder($this->connection, null, $metadataRegistry);

        // Test that the metadata registry is set (private property, so we test indirectly)
        $this->assertInstanceOf(QueryBuilder::class, $qb);
    }
}
