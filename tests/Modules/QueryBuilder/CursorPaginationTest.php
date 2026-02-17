<?php

namespace Articulate\Tests\Modules\QueryBuilder;

use Articulate\Connection;
use Articulate\Exceptions\CursorPaginationException;
use Articulate\Modules\QueryBuilder\Cursor;
use Articulate\Modules\QueryBuilder\CursorCodec;
use Articulate\Modules\QueryBuilder\CursorDirection;
use Articulate\Modules\QueryBuilder\QueryBuilder;
use Articulate\Tests\DatabaseTestCase;

class CursorPaginationTest extends DatabaseTestCase {
    private QueryBuilder $qb;

    private Connection $connection;

    /**
     * @dataProvider databaseProvider
     */
    public function testCursorPaginationRequiresOrderBy(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $this->connection->executeQuery('DROP TABLE IF EXISTS test_items');
        $this->connection->executeQuery('DROP TABLE IF EXISTS test_items');
        $this->connection->executeQuery('CREATE TABLE test_items (id INT PRIMARY KEY, name VARCHAR(255))');

        $this->qb->from('test_items')
            ->cursorLimit(10);

        $this->expectException(CursorPaginationException::class);
        $this->expectExceptionMessage('ORDER BY clause is required for cursor pagination');

        $this->qb->getCursorPaginatedResult();
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testCursorPaginationMaxTwoOrderByColumns(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $this->connection->executeQuery('DROP TABLE IF EXISTS test_items');
        $this->connection->executeQuery('CREATE TABLE test_items (id INT PRIMARY KEY, name VARCHAR(255), created_at TIMESTAMP)');

        $this->qb->from('test_items')
            ->orderBy('id', 'ASC')
            ->orderBy('name', 'ASC')
            ->orderBy('created_at', 'ASC')
            ->cursorLimit(10);

        $this->expectException(CursorPaginationException::class);
        $this->expectExceptionMessage('Cursor pagination supports maximum 2 ORDER BY columns');

        $this->qb->getCursorPaginatedResult();
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testCursorPaginationSingleColumnAsc(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $this->connection->executeQuery('DROP TABLE IF EXISTS test_items');
        $this->connection->executeQuery('CREATE TABLE test_items (id INT PRIMARY KEY, name VARCHAR(255))');
        $this->connection->executeQuery("INSERT INTO test_items (id, name) VALUES (1, 'Item 1'), (2, 'Item 2'), (3, 'Item 3'), (4, 'Item 4'), (5, 'Item 5')");

        $this->qb->from('test_items')
            ->orderBy('id', 'ASC')
            ->cursorLimit(2);

        $paginator = $this->qb->getCursorPaginatedResult();

        $items = $paginator->getItems();
        $this->assertCount(2, $items);
        $this->assertEquals(1, $items[0]['id']);
        $this->assertEquals(2, $items[1]['id']);
        $this->assertNotNull($paginator->getNextCursor());
        $this->assertFalse($paginator->hasPrev());

        $nextCursor = $paginator->getNextCursor();
        $this->assertNotNull($nextCursor);

        $qb2 = new QueryBuilder($this->connection);
        $qb2->from('test_items')
            ->orderBy('id', 'ASC')
            ->cursor($nextCursor, CursorDirection::NEXT)
            ->cursorLimit(2);

        $paginator2 = $qb2->getCursorPaginatedResult();
        $items2 = $paginator2->getItems();
        $this->assertCount(2, $items2);
        $this->assertEquals(3, $items2[0]['id']);
        $this->assertEquals(4, $items2[1]['id']);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testCursorPaginationSingleColumnDesc(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $this->connection->executeQuery('DROP TABLE IF EXISTS test_items');
        $this->connection->executeQuery('CREATE TABLE test_items (id INT PRIMARY KEY, name VARCHAR(255))');
        $this->connection->executeQuery("INSERT INTO test_items (id, name) VALUES (1, 'Item 1'), (2, 'Item 2'), (3, 'Item 3'), (4, 'Item 4'), (5, 'Item 5')");

        $this->qb->from('test_items')
            ->orderBy('id', 'DESC')
            ->cursorLimit(2);

        $paginator = $this->qb->getCursorPaginatedResult();

        $items = $paginator->getItems();
        $this->assertCount(2, $items);
        $this->assertEquals(5, $items[0]['id']);
        $this->assertEquals(4, $items[1]['id']);
        $this->assertNotNull($paginator->getNextCursor());

        $nextCursor = $paginator->getNextCursor();
        $this->assertNotNull($nextCursor);

        $qb2 = new QueryBuilder($this->connection);
        $qb2->from('test_items')
            ->orderBy('id', 'DESC')
            ->cursor($nextCursor, CursorDirection::NEXT)
            ->cursorLimit(2);

        $paginator2 = $qb2->getCursorPaginatedResult();
        $items2 = $paginator2->getItems();
        $this->assertCount(2, $items2);
        $this->assertEquals(3, $items2[0]['id']);
        $this->assertEquals(2, $items2[1]['id']);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testCursorPaginationTwoColumns(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $this->connection->executeQuery('DROP TABLE IF EXISTS test_items');
        $this->connection->executeQuery('CREATE TABLE test_items (id INT PRIMARY KEY, category VARCHAR(255), name VARCHAR(255))');
        $this->connection->executeQuery("INSERT INTO test_items (id, category, name) VALUES 
            (1, 'A', 'Item 1'), 
            (2, 'A', 'Item 2'), 
            (3, 'B', 'Item 3'), 
            (4, 'B', 'Item 4'), 
            (5, 'C', 'Item 5')");

        $this->qb->from('test_items')
            ->orderBy('category', 'ASC')
            ->orderBy('id', 'ASC')
            ->cursorLimit(2);

        $paginator = $this->qb->getCursorPaginatedResult();

        $items = $paginator->getItems();
        $this->assertCount(2, $items);
        $this->assertEquals('A', $items[0]['category']);
        $this->assertEquals(1, $items[0]['id']);
        $this->assertEquals('A', $items[1]['category']);
        $this->assertEquals(2, $items[1]['id']);
        $this->assertNotNull($paginator->getNextCursor());

        $nextCursor = $paginator->getNextCursor();
        $this->assertNotNull($nextCursor);

        $qb2 = new QueryBuilder($this->connection);
        $qb2->from('test_items')
            ->orderBy('category', 'ASC')
            ->orderBy('id', 'ASC')
            ->cursor($nextCursor, CursorDirection::NEXT)
            ->cursorLimit(2);

        $paginator2 = $qb2->getCursorPaginatedResult();
        $items2 = $paginator2->getItems();
        $this->assertCount(2, $items2);
        $this->assertEquals('B', $items2[0]['category']);
        $this->assertEquals(3, $items2[0]['id']);
        $this->assertEquals('B', $items2[1]['category']);
        $this->assertEquals(4, $items2[1]['id']);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testCursorPaginationWithWhereClause(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $this->connection->executeQuery('DROP TABLE IF EXISTS test_items');
        $this->connection->executeQuery('CREATE TABLE test_items (id INT PRIMARY KEY, status VARCHAR(255), name VARCHAR(255))');
        $this->connection->executeQuery("INSERT INTO test_items (id, status, name) VALUES 
            (1, 'active', 'Item 1'), 
            (2, 'active', 'Item 2'), 
            (3, 'active', 'Item 3'), 
            (4, 'inactive', 'Item 4'), 
            (5, 'active', 'Item 5')");

        $this->qb->from('test_items')
            ->where('status = ?', 'active')
            ->orderBy('id', 'ASC')
            ->cursorLimit(2);

        $paginator = $this->qb->getCursorPaginatedResult();

        $items = $paginator->getItems();
        $this->assertCount(2, $items);
        $this->assertEquals(1, $items[0]['id']);
        $this->assertEquals(2, $items[1]['id']);
        $this->assertEquals('active', $items[0]['status']);
        $this->assertEquals('active', $items[1]['status']);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testCursorPaginationLastPage(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $this->connection->executeQuery('DROP TABLE IF EXISTS test_items');
        $this->connection->executeQuery('CREATE TABLE test_items (id INT PRIMARY KEY, name VARCHAR(255))');
        $this->connection->executeQuery("INSERT INTO test_items (id, name) VALUES (1, 'Item 1'), (2, 'Item 2'), (3, 'Item 3')");

        $this->qb->from('test_items')
            ->orderBy('id', 'ASC')
            ->cursorLimit(2);

        $paginator = $this->qb->getCursorPaginatedResult();
        $this->assertTrue($paginator->hasNext());

        $nextCursor = $paginator->getNextCursor();
        $this->assertNotNull($nextCursor);

        $qb2 = new QueryBuilder($this->connection);
        $qb2->from('test_items')
            ->orderBy('id', 'ASC')
            ->cursor($nextCursor, CursorDirection::NEXT)
            ->cursorLimit(2);

        $paginator2 = $qb2->getCursorPaginatedResult();
        $items2 = $paginator2->getItems();
        $this->assertCount(1, $items2);
        $this->assertEquals(3, $items2[0]['id']);
        $this->assertFalse($paginator2->hasNext());
        $this->assertNull($paginator2->getNextCursor());
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testCursorCodecEncodeDecode(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $codec = new CursorCodec();

        $cursor = new Cursor([1, 'test'], CursorDirection::NEXT);
        $token = $codec->encode($cursor);
        $this->assertNotEmpty($token);

        $decoded = $codec->decode($token);
        $this->assertEquals([1, 'test'], $decoded->getValues());
        $this->assertEquals(CursorDirection::NEXT, $decoded->getDirection());
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testCursorPaginationRequiresCursorLimit(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();
        $this->qb = new QueryBuilder($this->connection);

        $this->connection->executeQuery('DROP TABLE IF EXISTS test_items');
        $this->connection->executeQuery('CREATE TABLE test_items (id INT PRIMARY KEY, name VARCHAR(255))');

        $this->qb->from('test_items')
            ->orderBy('id', 'ASC');

        $this->expectException(CursorPaginationException::class);
        $this->expectExceptionMessage('cursorLimit() must be called before getCursorPaginatedResult()');

        $this->qb->getCursorPaginatedResult();
    }
}
