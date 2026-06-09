<?php

namespace Articulate\Tests\Modules\QueryBuilder;

use Articulate\Connection;
use Articulate\Exceptions\CursorPaginationException;
use Articulate\Modules\QueryBuilder\Cursor;
use Articulate\Modules\QueryBuilder\CursorCodec;
use Articulate\Modules\QueryBuilder\CursorDirection;
use Articulate\Modules\QueryBuilder\QueryBuilder;
use Articulate\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class CursorPaginationTest extends DatabaseTestCase {
    private QueryBuilder $qb;

    private Connection $connection;

    #[DataProvider('databaseProvider')]
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

    #[DataProvider('databaseProvider')]
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

    #[DataProvider('databaseProvider')]
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
            ->cursor($nextCursor)
            ->cursorLimit(2);

        $paginator2 = $qb2->getCursorPaginatedResult();
        $items2 = $paginator2->getItems();
        $this->assertCount(2, $items2);
        $this->assertEquals(3, $items2[0]['id']);
        $this->assertEquals(4, $items2[1]['id']);
    }

    #[DataProvider('databaseProvider')]
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
            ->cursor($nextCursor)
            ->cursorLimit(2);

        $paginator2 = $qb2->getCursorPaginatedResult();
        $items2 = $paginator2->getItems();
        $this->assertCount(2, $items2);
        $this->assertEquals(3, $items2[0]['id']);
        $this->assertEquals(2, $items2[1]['id']);
    }

    #[DataProvider('databaseProvider')]
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
            ->cursor($nextCursor)
            ->cursorLimit(2);

        $paginator2 = $qb2->getCursorPaginatedResult();
        $items2 = $paginator2->getItems();
        $this->assertCount(2, $items2);
        $this->assertEquals('B', $items2[0]['category']);
        $this->assertEquals(3, $items2[0]['id']);
        $this->assertEquals('B', $items2[1]['category']);
        $this->assertEquals(4, $items2[1]['id']);
    }

    #[DataProvider('databaseProvider')]
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

    #[DataProvider('databaseProvider')]
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
            ->cursor($nextCursor)
            ->cursorLimit(2);

        $paginator2 = $qb2->getCursorPaginatedResult();
        $items2 = $paginator2->getItems();
        $this->assertCount(1, $items2);
        $this->assertEquals(3, $items2[0]['id']);
        $this->assertFalse($paginator2->hasNext());
        $this->assertNull($paginator2->getNextCursor());
    }

    /**
     * When total row count exactly equals the page size, hasNext must be false.
     * Old code used count === limit which gave a false positive here.
     */
    #[DataProvider('databaseProvider')]
    public function testCursorPaginationHasNextFalseOnExactPageSizeMatch(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();

        $this->connection->executeQuery('DROP TABLE IF EXISTS test_items');
        $this->connection->executeQuery('CREATE TABLE test_items (id INT PRIMARY KEY, name VARCHAR(255))');
        $this->connection->executeQuery("INSERT INTO test_items (id, name) VALUES (1, 'Item 1'), (2, 'Item 2'), (3, 'Item 3')");

        $qb = new QueryBuilder($this->connection);
        $qb->from('test_items')
            ->orderBy('id', 'ASC')
            ->cursorLimit(3);

        $paginator = $qb->getCursorPaginatedResult();

        $this->assertCount(3, $paginator->getItems());
        $this->assertFalse($paginator->hasNext());
        $this->assertNull($paginator->getNextCursor());
    }

    /**
     * Navigate forward two pages then backward to page 1, verifying items and cursors.
     */
    #[DataProvider('databaseProvider')]
    public function testCursorPaginationBackwardNavigation(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();

        $this->connection->executeQuery('DROP TABLE IF EXISTS test_items');
        $this->connection->executeQuery('CREATE TABLE test_items (id INT PRIMARY KEY, name VARCHAR(255))');
        $values = implode(', ', array_map(fn ($i) => "({$i}, 'Item {$i}')", range(1, 7)));
        $this->connection->executeQuery("INSERT INTO test_items (id, name) VALUES {$values}");

        // Page 1
        $qb1 = new QueryBuilder($this->connection);
        $qb1->from('test_items')->orderBy('id', 'ASC')->cursorLimit(3);
        $page1 = $qb1->getCursorPaginatedResult();

        $this->assertCount(3, $page1->getItems());
        $this->assertEquals([1, 2, 3], array_column($page1->getItems(), 'id'));
        $this->assertTrue($page1->hasNext());
        $this->assertFalse($page1->hasPrev());

        // Page 2
        $qb2 = new QueryBuilder($this->connection);
        $qb2->from('test_items')->orderBy('id', 'ASC')
            ->cursor($page1->getNextCursor())
            ->cursorLimit(3);
        $page2 = $qb2->getCursorPaginatedResult();

        $this->assertCount(3, $page2->getItems());
        $this->assertEquals([4, 5, 6], array_column($page2->getItems(), 'id'));
        $this->assertTrue($page2->hasNext());
        $this->assertTrue($page2->hasPrev());

        // Back to page 1 via prevCursor
        $qb3 = new QueryBuilder($this->connection);
        $qb3->from('test_items')->orderBy('id', 'ASC')
            ->cursor($page2->getPrevCursor())
            ->cursorLimit(3);
        $backToPage1 = $qb3->getCursorPaginatedResult();

        $this->assertCount(3, $backToPage1->getItems());
        $this->assertEquals([1, 2, 3], array_column($backToPage1->getItems(), 'id'));
        // At beginning of data — no further backward navigation
        $this->assertNull($backToPage1->getPrevCursor());
        // Can go forward again
        $this->assertNotNull($backToPage1->getNextCursor());

        // nextCursor from back-navigation page leads to page 2 again
        $qb4 = new QueryBuilder($this->connection);
        $qb4->from('test_items')->orderBy('id', 'ASC')
            ->cursor($backToPage1->getNextCursor())
            ->cursorLimit(3);
        $page2Again = $qb4->getCursorPaginatedResult();

        $this->assertEquals([4, 5, 6], array_column($page2Again->getItems(), 'id'));
    }

    /**
     * Backward navigation on DESC-ordered data.
     */
    #[DataProvider('databaseProvider')]
    public function testCursorPaginationBackwardNavigationDesc(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();

        $this->connection->executeQuery('DROP TABLE IF EXISTS test_items');
        $this->connection->executeQuery('CREATE TABLE test_items (id INT PRIMARY KEY, name VARCHAR(255))');
        $values = implode(', ', array_map(fn ($i) => "({$i}, 'Item {$i}')", range(1, 7)));
        $this->connection->executeQuery("INSERT INTO test_items (id, name) VALUES {$values}");

        // Page 1 (DESC: 7,6,5)
        $qb1 = new QueryBuilder($this->connection);
        $qb1->from('test_items')->orderBy('id', 'DESC')->cursorLimit(3);
        $page1 = $qb1->getCursorPaginatedResult();

        $this->assertEquals([7, 6, 5], array_column($page1->getItems(), 'id'));
        $this->assertTrue($page1->hasNext());
        $this->assertFalse($page1->hasPrev());

        // Page 2 (4,3,2)
        $qb2 = new QueryBuilder($this->connection);
        $qb2->from('test_items')->orderBy('id', 'DESC')
            ->cursor($page1->getNextCursor())
            ->cursorLimit(3);
        $page2 = $qb2->getCursorPaginatedResult();

        $this->assertEquals([4, 3, 2], array_column($page2->getItems(), 'id'));
        $this->assertTrue($page2->hasNext());
        $this->assertTrue($page2->hasPrev());

        // Back to page 1 via prevCursor
        $qb3 = new QueryBuilder($this->connection);
        $qb3->from('test_items')->orderBy('id', 'DESC')
            ->cursor($page2->getPrevCursor())
            ->cursorLimit(3);
        $backToPage1 = $qb3->getCursorPaginatedResult();

        $this->assertEquals([7, 6, 5], array_column($backToPage1->getItems(), 'id'));
        $this->assertNull($backToPage1->getPrevCursor());
        $this->assertNotNull($backToPage1->getNextCursor());
    }

    /**
     * prevCursor at the end of backward traversal is null when we reach the first row.
     */
    #[DataProvider('databaseProvider')]
    public function testCursorPaginationPrevCursorNullAtBeginning(string $databaseName): void
    {
        $this->setCurrentDatabase($this->getConnection($databaseName), $databaseName);
        $this->connection = $this->getCurrentConnection();

        $this->connection->executeQuery('DROP TABLE IF EXISTS test_items');
        $this->connection->executeQuery('CREATE TABLE test_items (id INT PRIMARY KEY, name VARCHAR(255))');
        $this->connection->executeQuery("INSERT INTO test_items (id, name) VALUES (1, 'a'), (2, 'b'), (3, 'c'), (4, 'd')");

        // Page 1
        $qb1 = new QueryBuilder($this->connection);
        $qb1->from('test_items')->orderBy('id', 'ASC')->cursorLimit(2);
        $page1 = $qb1->getCursorPaginatedResult();
        $this->assertEquals([1, 2], array_column($page1->getItems(), 'id'));

        // Page 2
        $qb2 = new QueryBuilder($this->connection);
        $qb2->from('test_items')->orderBy('id', 'ASC')
            ->cursor($page1->getNextCursor())->cursorLimit(2);
        $page2 = $qb2->getCursorPaginatedResult();
        $this->assertEquals([3, 4], array_column($page2->getItems(), 'id'));
        $this->assertFalse($page2->hasNext());
        $this->assertTrue($page2->hasPrev());

        // Navigate back — exactly 2 prev items, fills the page
        $qb3 = new QueryBuilder($this->connection);
        $qb3->from('test_items')->orderBy('id', 'ASC')
            ->cursor($page2->getPrevCursor())->cursorLimit(2);
        $back = $qb3->getCursorPaginatedResult();

        $this->assertEquals([1, 2], array_column($back->getItems(), 'id'));
        $this->assertNull($back->getPrevCursor(), 'No items before id:1 — prevCursor must be null');
        $this->assertNotNull($back->getNextCursor());
    }

    #[DataProvider('databaseProvider')]
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

    #[DataProvider('databaseProvider')]
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
