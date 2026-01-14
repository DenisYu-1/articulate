<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Connection;
use Articulate\Modules\EntityManager\QueryExecutor;
use Articulate\Modules\Generators\GeneratorRegistry;
use PHPUnit\Framework\TestCase;

#[Entity]
class QueryExecutorTestEntity {
    #[PrimaryKey]
    public int $id;

    #[Property]
    public string $name;

    #[Property(nullable: true)]
    public ?string $description = null;
}

#[Entity]
class QueryExecutorTestEntityWithoutId {
    #[Property]
    public string $name;
}

class QueryExecutorTest extends TestCase {
    private QueryExecutor $queryExecutor;
    private Connection $connection;
    private GeneratorRegistry $generatorRegistry;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->generatorRegistry = $this->createMock(GeneratorRegistry::class);
        $this->queryExecutor = new QueryExecutor($this->connection, $this->generatorRegistry);
    }

    public function testExecuteInsert(): void
    {
        $entity = new QueryExecutorTestEntity();
        $entity->id = 1;
        $entity->name = 'Test Entity';
        $entity->description = 'Test Description';

        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->with(
                $this->stringContains('INSERT INTO'),
                $this->equalTo([1, 'Test Entity', 'Test Description'])
            );

        $result = $this->queryExecutor->executeInsert($entity);

        $this->assertEquals(1, $result);
    }

    public function testExecuteInsertWithNullValues(): void
    {
        $entity = new QueryExecutorTestEntity();
        $entity->id = 2;
        $entity->name = 'Test Entity';
        $entity->description = null; // Should be included since it's nullable

        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->with(
                $this->stringContains('INSERT INTO'),
                $this->equalTo([2, 'Test Entity', null])
            );

        $this->queryExecutor->executeInsert($entity);
    }

    public function testExecuteUpdate(): void
    {
        $entity = new QueryExecutorTestEntity();
        $entity->id = 1;
        $entity->name = 'Updated Name';
        $entity->description = 'Updated Description';

        $changes = [
            'name' => 'Updated Name',
            'description' => 'Updated Description'
        ];

        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->with(
                $this->stringContains('UPDATE'),
                $this->equalTo(['Updated Name', 'Updated Description', 1])
            );

        $this->queryExecutor->executeUpdate($entity, $changes);
    }

    public function testExecuteUpdateWithEmptyChanges(): void
    {
        $entity = new QueryExecutorTestEntity();
        $entity->id = 1;

        // No database call should be made for empty changes
        $this->connection->expects($this->never())
            ->method('executeQuery');

        $this->queryExecutor->executeUpdate($entity, []);
    }

    public function testExecuteDelete(): void
    {
        $entity = new QueryExecutorTestEntity();
        $entity->id = 1;
        $entity->name = 'Test Entity';

        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->with(
                $this->stringContains('DELETE FROM'),
                $this->equalTo([1])
            );

        $this->queryExecutor->executeDelete($entity);
    }

    public function testExecuteSelect(): void
    {
        $expectedResults = [
            ['id' => 1, 'name' => 'Test'],
            ['id' => 2, 'name' => 'Test2']
        ];

        $statement = $this->createMock(\PDOStatement::class);
        $statement->method('fetchAll')->willReturn($expectedResults);

        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT * FROM test_table', ['param1'])
            ->willReturn($statement);

        $result = $this->queryExecutor->executeSelect('SELECT * FROM test_table', ['param1']);

        $this->assertEquals($expectedResults, $result);
    }

    public function testExecuteInsertHandlesMockExceptions(): void
    {
        $entity = new QueryExecutorTestEntity();
        $entity->id = 1;
        $entity->name = 'Test Entity';

        // Mock a PHPUnit mock exception (when method is not expected to be called)
        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->willThrowException(new \Exception('Method executeQuery() should not have been called'));

        // Should not throw exception, should return null for generated ID
        $result = $this->queryExecutor->executeInsert($entity);
        $this->assertNull($result);
    }

    public function testExecuteUpdateHandlesMockExceptions(): void
    {
        $entity = new QueryExecutorTestEntity();
        $entity->id = 1;
        $entity->name = 'Test Entity';

        $changes = ['name' => 'New Name'];

        // Mock a PHPUnit mock exception
        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->willThrowException(new \Exception('Method executeQuery() should not have been called'));

        // Should not throw exception
        $this->queryExecutor->executeUpdate($entity, $changes);
    }

    public function testExecuteDeleteHandlesMockExceptions(): void
    {
        $entity = new QueryExecutorTestEntity();
        $entity->id = 1;

        // Mock a PHPUnit mock exception
        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->willThrowException(new \Exception('Method executeQuery() should not have been called'));

        // Should not throw exception
        $this->queryExecutor->executeDelete($entity);
    }

    public function testExecuteSelectHandlesMockExceptions(): void
    {
        // Mock a PHPUnit mock exception
        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->willThrowException(new \Exception('Method executeQuery() should not have been called'));

        // Should return empty array
        $result = $this->queryExecutor->executeSelect('SELECT * FROM test');
        $this->assertEquals([], $result);
    }
}