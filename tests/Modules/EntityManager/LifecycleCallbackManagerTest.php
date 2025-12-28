<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Lifecycle\PostPersist;
use Articulate\Attributes\Lifecycle\PrePersist;
use Articulate\Modules\EntityManager\LifecycleCallbackManager;
use PHPUnit\Framework\TestCase;

class CallbackTestEntity {
    public array $callbacksCalled = [];

    #[PrePersist]
    public function validate(): void
    {
        $this->callbacksCalled[] = 'validate';
    }

    #[PrePersist]
    public function setDefaults(): void
    {
        $this->callbacksCalled[] = 'setDefaults';
    }

    #[PostPersist]
    public function logCreation(): void
    {
        $this->callbacksCalled[] = 'logCreation';
    }

    public function nonCallbackMethod(): void
    {
        $this->callbacksCalled[] = 'nonCallback';
    }
}

class LifecycleCallbackManagerTest extends TestCase {
    private LifecycleCallbackManager $callbackManager;

    protected function setUp(): void
    {
        $this->callbackManager = new LifecycleCallbackManager();
    }

    public function testGetCallbacks(): void
    {
        $callbacks = $this->callbackManager->getCallbacks(CallbackTestEntity::class, 'prePersist');

        $this->assertCount(2, $callbacks);
        $this->assertContains('validate', $callbacks);
        $this->assertContains('setDefaults', $callbacks);
    }

    public function testGetCallbacksCaching(): void
    {
        // First call
        $callbacks1 = $this->callbackManager->getCallbacks(CallbackTestEntity::class, 'prePersist');

        // Second call should return cached result
        $callbacks2 = $this->callbackManager->getCallbacks(CallbackTestEntity::class, 'prePersist');

        $this->assertSame($callbacks1, $callbacks2);
    }

    public function testInvokeCallbacks(): void
    {
        $entity = new CallbackTestEntity();

        $this->callbackManager->invokeCallbacks($entity, 'prePersist');

        $this->assertContains('validate', $entity->callbacksCalled);
        $this->assertContains('setDefaults', $entity->callbacksCalled);
        $this->assertNotContains('logCreation', $entity->callbacksCalled);
    }

    public function testInvokeCallbacksWithNoCallbacks(): void
    {
        $entity = new CallbackTestEntity();

        $this->callbackManager->invokeCallbacks($entity, 'nonExistentCallback');

        // Should not modify callbacksCalled
        $this->assertEmpty($entity->callbacksCalled);
    }

    public function testMultipleCallbackTypes(): void
    {
        $entity = new CallbackTestEntity();

        // Test prePersist
        $this->callbackManager->invokeCallbacks($entity, 'prePersist');
        $this->assertContains('validate', $entity->callbacksCalled);
        $this->assertContains('setDefaults', $entity->callbacksCalled);

        // Test postPersist
        $this->callbackManager->invokeCallbacks($entity, 'postPersist');
        $this->assertContains('logCreation', $entity->callbacksCalled);
    }
}
