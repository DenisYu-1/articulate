<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Modules\EntityManager\ObjectHydrator;
use Articulate\Modules\EntityManager\UnitOfWork;
use PHPUnit\Framework\TestCase;

class ObjectHydratorTest extends TestCase
{
    private ObjectHydrator $hydrator;
    private UnitOfWork $unitOfWork;

    protected function setUp(): void
    {
        $this->unitOfWork = $this->createMock(UnitOfWork::class);
        $this->hydrator = new ObjectHydrator($this->unitOfWork);
    }

    public function testImplementsHydratorInterface(): void
    {
        $this->assertInstanceOf(\Articulate\Modules\EntityManager\HydratorInterface::class, $this->hydrator);
    }

    public function testHydrateCreatesNewEntity(): void
    {
        $data = [
            'id' => 1,
            'name' => 'Test Entity',
            'email' => 'test@example.com'
        ];

        $this->unitOfWork->expects($this->once())
            ->method('registerManaged')
            ->with($this->isInstanceOf(TestEntity::class), $data);

        $entity = $this->hydrator->hydrate(TestEntity::class, $data);

        $this->assertInstanceOf(TestEntity::class, $entity);
        $this->assertEquals(1, $entity->id);
        $this->assertEquals('Test Entity', $entity->name);
        $this->assertEquals('test@example.com', $entity->email);
    }

    public function testHydrateIntoExistingEntity(): void
    {
        $existingEntity = new TestEntity();
        $existingEntity->id = 999; // Should be overwritten
        $existingEntity->existingField = 'should remain';

        $data = [
            'id' => 1,
            'name' => 'Updated Name'
        ];

        $this->unitOfWork->expects($this->once())
            ->method('registerManaged')
            ->with($existingEntity, $data);

        $result = $this->hydrator->hydrate(TestEntity::class, $data, $existingEntity);

        $this->assertSame($existingEntity, $result);
        $this->assertEquals(1, $result->id);
        $this->assertEquals('Updated Name', $result->name);
        $this->assertEquals('should remain', $result->existingField);
    }

    public function testExtractEntityData(): void
    {
        $entity = new TestEntity();
        $entity->id = 42;
        $entity->name = 'Extract Test';

        $data = $this->hydrator->extract($entity);

        $this->assertEquals([
            'id' => 42,
            'name' => 'Extract Test',
            'email' => null,
            'existingField' => null,
            'userId' => null,
            'firstName' => null,
            'lastName' => null,
            'emailAddress' => null
        ], $data);
    }

    public function testHydratePartial(): void
    {
        $entity = new TestEntity();
        $entity->id = 1;
        $entity->name = 'Original';

        $partialData = [
            'name' => 'Updated',
            'email' => 'new@email.com'
        ];

        $this->hydrator->hydratePartial($entity, $partialData);

        $this->assertEquals(1, $entity->id); // Unchanged
        $this->assertEquals('Updated', $entity->name);
        $this->assertEquals('new@email.com', $entity->email);
    }

    public function testSnakeCaseToCamelCaseMapping(): void
    {
        $data = [
            'user_id' => 123,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email_address' => 'john@example.com'
        ];

        $this->unitOfWork->expects($this->once())
            ->method('registerManaged');

        $entity = $this->hydrator->hydrate(TestEntity::class, $data);

        $this->assertEquals(123, $entity->userId);
        $this->assertEquals('John', $entity->firstName);
        $this->assertEquals('Doe', $entity->lastName);
        $this->assertEquals('john@example.com', $entity->emailAddress);
    }

    public function testColumnToPropertyMappingWithAttributes(): void
    {
        // TODO: Test Property attribute mapping when implemented
        $this->markTestIncomplete('Property attribute mapping not yet implemented');
    }
}

// Test entity class for hydration tests
class TestEntity
{
    public ?int $id = null;
    public ?string $name = null;
    public ?string $email = null;
    public ?string $existingField = null;
    public ?int $userId = null;
    public ?string $firstName = null;
    public ?string $lastName = null;
    public ?string $emailAddress = null;
    private string $privateField = 'private';
}
