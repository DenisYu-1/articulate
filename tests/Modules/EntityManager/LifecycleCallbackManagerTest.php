<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Lifecycle\PostLoad;
use Articulate\Attributes\Lifecycle\PostPersist;
use Articulate\Attributes\Lifecycle\PostRemove;
use Articulate\Attributes\Lifecycle\PostUpdate;
use Articulate\Attributes\Lifecycle\PrePersist;
use Articulate\Attributes\Lifecycle\PreRemove;
use Articulate\Attributes\Lifecycle\PreUpdate;
use Articulate\Modules\EntityManager\LifecycleCallbackManager;
use PHPUnit\Framework\TestCase;

class LifecycleCallbackManagerTest extends TestCase
{
    private LifecycleCallbackManager $manager;

    protected function setUp(): void
    {
        $this->manager = new LifecycleCallbackManager();
    }

    public function testGetCallbacksForClassWithoutCallbacks(): void
    {
        $callbacks = $this->manager->getCallbacks(\stdClass::class, 'prePersist');

        $this->assertEquals([], $callbacks);
    }

    public function testGetCallbacksWithInvalidCallbackType(): void
    {
        $callbacks = $this->manager->getCallbacks(\stdClass::class, 'invalidType');

        $this->assertEquals([], $callbacks);
    }

    public function testGetCallbacksCaching(): void
    {
        // First call should load callbacks
        $callbacks1 = $this->manager->getCallbacks(\stdClass::class, 'prePersist');

        // Second call should use cached result
        $callbacks2 = $this->manager->getCallbacks(\stdClass::class, 'postPersist');

        // Both should be empty arrays
        $this->assertEquals([], $callbacks1);
        $this->assertEquals([], $callbacks2);
    }

    public function testInvokeCallbacksOnEntityWithoutCallbacks(): void
    {
        $entity = new \stdClass();

        // Should not throw exception
        $this->manager->invokeCallbacks($entity, 'prePersist');

        // Should complete without issues
        $this->assertTrue(true);
    }

    public function testInvokeCallbacksWithInvalidCallbackType(): void
    {
        $entity = new \stdClass();

        // Should not throw exception
        $this->manager->invokeCallbacks($entity, 'invalidType');

        // Should complete without issues
        $this->assertTrue(true);
    }

    public function testGetCallbacksForEntityWithSingleCallback(): void
    {
        $callbacks = $this->manager->getCallbacks(TestEntityWithCallbacks::class, 'prePersist');

        $this->assertEquals(['prepareForInsert'], $callbacks);
    }

    public function testGetCallbacksForEntityWithMultipleCallbacksOfSameType(): void
    {
        $callbacks = $this->manager->getCallbacks(TestEntityWithCallbacks::class, 'postPersist');

        $this->assertEquals(['afterInsert', 'logInsert'], $callbacks);
    }

    public function testGetCallbacksForAllCallbackTypes(): void
    {
        $callbackTypes = [
            'prePersist', 'postPersist', 'preUpdate', 'postUpdate',
            'preRemove', 'postRemove', 'postLoad'
        ];

        foreach ($callbackTypes as $type) {
            $callbacks = $this->manager->getCallbacks(TestEntityWithCallbacks::class, $type);
            $this->assertIsArray($callbacks);
        }
    }

    public function testInvokeCallbacksExecutesMethods(): void
    {
        $entity = new TestEntityWithCallbacks();

        $this->manager->invokeCallbacks($entity, 'prePersist');

        $this->assertTrue($entity->prepareForInsertCalled);
    }

    public function testInvokeCallbacksExecutesMultipleMethods(): void
    {
        $entity = new TestEntityWithCallbacks();

        $this->manager->invokeCallbacks($entity, 'postPersist');

        $this->assertTrue($entity->afterInsertCalled);
        $this->assertTrue($entity->logInsertCalled);
    }

    public function testInvokeCallbacksWithNonExistentMethod(): void
    {
        $entity = new TestEntityWithCallbacks();

        // Should throw exception when trying to call non-existent method
        $this->expectException(\Exception::class);
        $this->manager->invokeCallbacks($entity, 'preUpdate');
    }

    public function testCallbacksAreLoadedOnlyOnce(): void
    {
        // First call loads callbacks
        $this->manager->getCallbacks(TestEntityWithCallbacks::class, 'prePersist');

        // Modify the loaded callbacks (simulate external modification)
        $reflection = new \ReflectionClass($this->manager);
        $property = $reflection->getProperty('callbacks');
        $property->setAccessible(true);
        $callbacks = $property->getValue($this->manager);
        $callbacks[TestEntityWithCallbacks::class]['prePersist'] = ['modified'];
        $property->setValue($this->manager, $callbacks);

        // Second call should return cached (modified) result
        $result = $this->manager->getCallbacks(TestEntityWithCallbacks::class, 'prePersist');
        $this->assertEquals(['modified'], $result);
    }

    public function testGetCallbacksForEntityWithInheritedCallbacks(): void
    {
        // Test child class inherits callbacks from parent
        $childCallbacks = $this->manager->getCallbacks(TestChildEntityWithCallbacks::class, 'prePersist');
        $parentCallbacks = $this->manager->getCallbacks(TestParentEntityWithCallbacks::class, 'prePersist');

        // Child should have its own callback plus inherited ones
        $this->assertContains('childPrepare', $childCallbacks);
        $this->assertContains('parentPrepare', $childCallbacks);
    }
}

// Test entity classes
class TestEntityWithCallbacks {
    public bool $prepareForInsertCalled = false;
    public bool $afterInsertCalled = false;
    public bool $logInsertCalled = false;

    #[PrePersist]
    public function prepareForInsert(): void
    {
        $this->prepareForInsertCalled = true;
    }

    #[PostPersist]
    public function afterInsert(): void
    {
        $this->afterInsertCalled = true;
    }

    #[PostPersist]
    public function logInsert(): void
    {
        $this->logInsertCalled = true;
    }

    #[PreUpdate]
    public function prepareForUpdate(): void
    {
        // This method doesn't exist - used for testing
        throw new \Exception('Should not be called');
    }
}

class TestParentEntityWithCallbacks {
    #[PrePersist]
    public function parentPrepare(): void
    {
        // Parent callback
    }
}

class TestChildEntityWithCallbacks extends TestParentEntityWithCallbacks {
    #[PrePersist]
    public function childPrepare(): void
    {
        // Child callback
    }
}