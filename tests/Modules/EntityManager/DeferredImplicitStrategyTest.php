<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Modules\EntityManager\DeferredImplicitStrategy;
use PHPUnit\Framework\TestCase;

class DeferredImplicitStrategyTest extends TestCase
{
    private DeferredImplicitStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new DeferredImplicitStrategy();
    }

    public function testTrackEntity(): void
    {
        $entity = new class {
            public int $id = 1;
            public string $name = 'test';
        };

        $originalData = ['id' => 1, 'name' => 'original'];

        $this->strategy->trackEntity($entity, $originalData);

        // Should not throw an exception
        $this->assertTrue(true);
    }

    public function testComputeChangeSetWithNoChanges(): void
    {
        $entity = new class {
            public int $id = 1;
            public string $name = 'test';
        };

        $originalData = ['id' => 1, 'name' => 'test'];

        $this->strategy->trackEntity($entity, $originalData);

        $changes = $this->strategy->computeChangeSet($entity);

        $this->assertEmpty($changes);
    }

    public function testComputeChangeSetWithChanges(): void
    {
        $entity = new class {
            public int $id = 1;
            public string $name = 'modified';
        };

        $originalData = ['id' => 1, 'name' => 'original'];

        $this->strategy->trackEntity($entity, $originalData);

        $changes = $this->strategy->computeChangeSet($entity);

        $this->assertEquals(['name' => 'modified'], $changes);
    }

    public function testComputeChangeSetWithMultipleChanges(): void
    {
        $entity = new class {
            public int $id = 1;
            public string $name = 'modified name';
            public int $age = 30;
        };

        $originalData = ['id' => 1, 'name' => 'original name', 'age' => 25];

        $this->strategy->trackEntity($entity, $originalData);

        $changes = $this->strategy->computeChangeSet($entity);

        $expected = [
            'name' => 'modified name',
            'age' => 30
        ];

        $this->assertEquals($expected, $changes);
    }

    public function testComputeChangeSetWithoutTracking(): void
    {
        $entity = new class {
            public int $id = 1;
            public string $name = 'test';
        };

        $changes = $this->strategy->computeChangeSet($entity);

        $this->assertEmpty($changes);
    }

    public function testCalculateDifferencesWithNewFields(): void
    {
        $original = ['id' => 1, 'name' => 'original'];
        $current = ['id' => 1, 'name' => 'original', 'age' => 25];

        $reflectionMethod = new \ReflectionMethod($this->strategy, 'calculateDifferences');
        $reflectionMethod->setAccessible(true);

        $changes = $reflectionMethod->invoke($this->strategy, $original, $current);

        $this->assertEquals(['age' => 25], $changes);
    }

    public function testCalculateDifferencesWithRemovedFields(): void
    {
        $original = ['id' => 1, 'name' => 'original', 'age' => 25];
        $current = ['id' => 1, 'name' => 'original'];

        $reflectionMethod = new \ReflectionMethod($this->strategy, 'calculateDifferences');
        $reflectionMethod->setAccessible(true);

        $changes = $reflectionMethod->invoke($this->strategy, $original, $current);

        $this->assertEmpty($changes);
    }

    public function testExtractEntityDataReturnsEntityProperties(): void
    {
        $entity = new class {
            public int $id = 1;
            public string $name = 'test';
            private string $privateProp = 'private'; // Should not be included
        };

        $reflectionMethod = new \ReflectionMethod($this->strategy, 'extractEntityData');
        $reflectionMethod->setAccessible(true);

        $data = $reflectionMethod->invoke($this->strategy, $entity);

        $this->assertIsArray($data);
        $this->assertEquals(['id' => 1, 'name' => 'test'], $data);
        $this->assertArrayNotHasKey('privateProp', $data);
    }
}
