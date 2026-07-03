<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Connection;
use Articulate\Modules\EntityManager\QueryExecutor;
use Articulate\Modules\Generators\GeneratorInterface;
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

#[Entity]
class QueryExecutorUuidEntity {
    #[PrimaryKey(generator: 'uuid_v4')]
    public ?string $id = null;

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
        $this->generatorRegistry = $this->createStub(GeneratorRegistry::class);
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

    public function testExecuteInsertGeneratesAndAssignsUuidId(): void
    {
        $generatedId = '4f123ec8-f1b7-4956-9f08-6dfb89d9014f';
        $generator = $this->createStub(GeneratorInterface::class);
        $generator->method('generate')->willReturn($generatedId);

        $this->generatorRegistry = $this->createMock(GeneratorRegistry::class);
        $this->generatorRegistry->expects($this->once())
            ->method('getGenerator')
            ->with('uuid_v4')
            ->willReturn($generator);
        $this->queryExecutor = new QueryExecutor($this->connection, $this->generatorRegistry);

        $entity = new QueryExecutorUuidEntity();
        $entity->name = 'Generated Entity';

        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->with(
                $this->stringContains('INSERT INTO'),
                $this->equalTo(['Generated Entity', $generatedId])
            );

        $result = $this->queryExecutor->executeInsert($entity);

        $this->assertSame($generatedId, $result);
        $this->assertSame($generatedId, $entity->id);
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
            'description' => 'Updated Description',
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
            ['id' => 2, 'name' => 'Test2'],
        ];

        $statement = $this->createStub(\PDOStatement::class);
        $statement->method('fetchAll')->willReturn($expectedResults);

        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT * FROM test_table', ['param1'])
            ->willReturn($statement);

        $result = $this->queryExecutor->executeSelect('SELECT * FROM test_table', ['param1']);

        $this->assertEquals($expectedResults, $result);
    }

    public function testExecuteInsertPropagatesExceptions(): void
    {
        $entity = new QueryExecutorTestEntity();
        $entity->id = 1;
        $entity->name = 'Test Entity';

        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->willThrowException(new \PDOException('DB constraint violation'));

        $this->expectException(\PDOException::class);
        $this->queryExecutor->executeInsert($entity);
    }

    public function testExecuteUpdatePropagatesExceptions(): void
    {
        $entity = new QueryExecutorTestEntity();
        $entity->id = 1;
        $entity->name = 'Test Entity';

        $changes = ['name' => 'New Name'];

        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->willThrowException(new \PDOException('DB constraint violation'));

        $this->expectException(\PDOException::class);
        $this->queryExecutor->executeUpdate($entity, $changes);
    }

    public function testExecuteDeletePropagatesExceptions(): void
    {
        $entity = new QueryExecutorTestEntity();
        $entity->id = 1;

        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->willThrowException(new \PDOException('DB constraint violation'));

        $this->expectException(\PDOException::class);
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
