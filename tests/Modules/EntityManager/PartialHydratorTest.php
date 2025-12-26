<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Modules\EntityManager\ObjectHydrator;
use Articulate\Modules\EntityManager\PartialHydrator;
use Articulate\Modules\EntityManager\UnitOfWork;
use PHPUnit\Framework\TestCase;

class PartialHydratorTest extends TestCase
{
    private PartialHydrator $hydrator;

    private ObjectHydrator $objectHydrator;

    private UnitOfWork $unitOfWork;

    protected function setUp(): void
    {
        $this->unitOfWork = $this->createMock(UnitOfWork::class);
        $this->objectHydrator = new ObjectHydrator($this->unitOfWork);
        $this->hydrator = new PartialHydrator($this->objectHydrator);
    }

    public function testImplementsHydratorInterface(): void
    {
        $this->assertInstanceOf(\Articulate\Modules\EntityManager\HydratorInterface::class, $this->hydrator);
    }

    public function testHydrateCreatesEntityWithPartialData(): void
    {
        $data = [
            'name' => 'Partial Entity',
            'email' => 'partial@example.com',
        ];

        $this->unitOfWork->expects($this->once())
            ->method('registerManaged');

        $entity = $this->hydrator->hydrate(TestPartialEntity::class, $data);

        $this->assertInstanceOf(TestPartialEntity::class, $entity);
        $this->assertEquals('Partial Entity', $entity->name);
        $this->assertEquals('partial@example.com', $entity->email);
        $this->assertNull($entity->id); // Not in partial data
    }

    public function testHydrateIntoExistingEntity(): void
    {
        $existingEntity = new TestPartialEntity();
        $existingEntity->id = 999;
        $existingEntity->name = 'Original';

        $partialData = [
            'name' => 'Updated',
            'email' => 'updated@example.com',
        ];

        $entity = $this->hydrator->hydrate(TestPartialEntity::class, $partialData, $existingEntity);

        $this->assertSame($existingEntity, $entity);
        $this->assertEquals(999, $entity->id); // Preserved
        $this->assertEquals('Updated', $entity->name);
        $this->assertEquals('updated@example.com', $entity->email);
    }

    public function testExtractDelegatesToObjectHydrator(): void
    {
        $entity = new TestPartialEntity();
        $entity->id = 1;
        $entity->name = 'Test';

        $result = $this->hydrator->extract($entity);

        $expected = [
            'id' => 1,
            'name' => 'Test',
            'email' => null,
        ];
        $this->assertEquals($expected, $result);
    }

    public function testHydratePartialDelegatesToObjectHydrator(): void
    {
        $entity = new TestPartialEntity();
        $entity->id = 1;
        $entity->name = 'Original';

        $partialData = [
            'name' => 'Updated',
            'email' => 'new@example.com',
        ];

        $this->hydrator->hydratePartial($entity, $partialData);

        $this->assertEquals(1, $entity->id);
        $this->assertEquals('Updated', $entity->name);
        $this->assertEquals('new@example.com', $entity->email);
    }
}

// Test entity for partial hydration
class TestPartialEntity
{
    public ?int $id = null;

    public ?string $name = null;

    public ?string $email = null;
}
