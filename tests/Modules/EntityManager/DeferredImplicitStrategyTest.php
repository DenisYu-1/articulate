<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Modules\EntityManager\DeferredImplicitStrategy;
use Articulate\Modules\EntityManager\EntityMetadataRegistry;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[Entity]
class TestChangeTrackingEntity {
    #[Property]
    public int $id;

    #[Property]
    public string $name;

    #[Property]
    public ?int $age = null;
}

class DeferredImplicitStrategyTest extends TestCase {
    private DeferredImplicitStrategy $strategy;

    protected function setUp(): void
    {
        $metadataRegistry = new EntityMetadataRegistry();
        $this->strategy = new DeferredImplicitStrategy($metadataRegistry);
    }

    public function testTrackEntity(): void
    {
        $entity = new TestChangeTrackingEntity();
        $entity->id = 1;
        $entity->name = 'test';

        $originalData = ['id' => 1, 'name' => 'original'];

        $this->strategy->trackEntity($entity, $originalData);

        // Should not throw an exception
        $this->assertTrue(true);
    }

    public function testComputeChangeSetWithNoChanges(): void
    {
        $entity = new TestChangeTrackingEntity();
        $entity->id = 1;
        $entity->name = 'test';
        $entity->age = null; // Initialize nullable property

        $originalData = ['id' => 1, 'name' => 'test', 'age' => null];

        $this->strategy->trackEntity($entity, $originalData);

        $changes = $this->strategy->computeChangeSet($entity);

        $this->assertEmpty($changes);
    }

    public function testComputeChangeSetWithChanges(): void
    {
        $entity = new TestChangeTrackingEntity();
        $entity->id = 1;
        $entity->name = 'modified';
        $entity->age = null; // Initialize nullable property

        $originalData = ['id' => 1, 'name' => 'original', 'age' => null];

        $this->strategy->trackEntity($entity, $originalData);

        $changes = $this->strategy->computeChangeSet($entity);

        $this->assertEquals(['name' => 'modified'], $changes);
    }

    public function testComputeChangeSetWithMultipleChanges(): void
    {
        $entity = new TestChangeTrackingEntity();
        $entity->id = 1;
        $entity->name = 'modified name';
        $entity->age = 30;

        $originalData = ['id' => 1, 'name' => 'original name', 'age' => 25];

        $this->strategy->trackEntity($entity, $originalData);

        $changes = $this->strategy->computeChangeSet($entity);

        $expected = [
            'name' => 'modified name',
            'age' => 30,
        ];

        $this->assertEquals($expected, $changes);
    }

    public function testComputeChangeSetWithoutTracking(): void
    {
        $entity = new TestChangeTrackingEntity();
        $entity->id = 1;
        $entity->name = 'test';

        $changes = $this->strategy->computeChangeSet($entity);

        $this->assertEmpty($changes);
    }

    public function testCalculateDifferencesWithNewFields(): void
    {
        $original = ['id' => 1, 'name' => 'original'];
        $current = ['id' => 1, 'name' => 'original', 'age' => 25];

        $reflectionMethod = new ReflectionMethod($this->strategy, 'calculateDifferences');
        $reflectionMethod->setAccessible(true);

        $changes = $reflectionMethod->invoke($this->strategy, $original, $current);

        $this->assertEquals(['age' => 25], $changes);
    }

    public function testCalculateDifferencesWithRemovedFields(): void
    {
        $original = ['id' => 1, 'name' => 'original', 'age' => 25];
        $current = ['id' => 1, 'name' => 'original'];

        $reflectionMethod = new ReflectionMethod($this->strategy, 'calculateDifferences');
        $reflectionMethod->setAccessible(true);

        $changes = $reflectionMethod->invoke($this->strategy, $original, $current);

        $this->assertEmpty($changes);
    }

    public function testExtractEntityDataReturnsEntityProperties(): void
    {
        $entity = new TestChangeTrackingEntity();
        $entity->id = 1;
        $entity->name = 'test';
        $entity->age = 25;

        $reflectionMethod = new ReflectionMethod($this->strategy, 'extractEntityData');
        $reflectionMethod->setAccessible(true);

        $data = $reflectionMethod->invoke($this->strategy, $entity);

        $this->assertIsArray($data);
        // Should return column names as keys (same as property names for these simple cases)
        $this->assertEquals(['id' => 1, 'name' => 'test', 'age' => 25], $data);
    }
}
