<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Lifecycle\PostLoad;
use Articulate\Attributes\Lifecycle\PostPersist;
use Articulate\Attributes\Lifecycle\PostRemove;
use Articulate\Attributes\Lifecycle\PostUpdate;
use Articulate\Attributes\Lifecycle\PrePersist;
use Articulate\Attributes\Lifecycle\PreRemove;
use Articulate\Attributes\Lifecycle\PreUpdate;
use Articulate\Attributes\Property;
use Articulate\Connection;
use Articulate\Modules\EntityManager\EntityManager;
use PHPUnit\Framework\TestCase;

#[Entity(tableName: 'lifecycle_test_users')]
class LifecycleTestUser
{
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public ?string $name = null;

    #[Property]
    public ?string $email = null;

    // Callback tracking
    public array $callbacksCalled = [];

    #[PrePersist]
    public function onPrePersist(): void
    {
        $this->callbacksCalled[] = 'prePersist';
    }

    #[PostPersist]
    public function onPostPersist(): void
    {
        $this->callbacksCalled[] = 'postPersist';
    }

    #[PreUpdate]
    public function onPreUpdate(): void
    {
        $this->callbacksCalled[] = 'preUpdate';
    }

    #[PostUpdate]
    public function onPostUpdate(): void
    {
        $this->callbacksCalled[] = 'postUpdate';
    }

    #[PreRemove]
    public function onPreRemove(): void
    {
        $this->callbacksCalled[] = 'preRemove';
    }

    #[PostRemove]
    public function onPostRemove(): void
    {
        $this->callbacksCalled[] = 'postRemove';
    }

    #[PostLoad]
    public function onPostLoad(): void
    {
        $this->callbacksCalled[] = 'postLoad';
    }
}

class LifecycleCallbackTest extends TestCase
{
    private EntityManager $entityManager;

    protected function setUp(): void
    {
        $connection = $this->createMock(Connection::class);
        $this->entityManager = new EntityManager($connection);
    }

    public function testPrePersistCallback(): void
    {
        $user = new LifecycleTestUser();
        $user->name = 'Test User';
        $user->email = 'test@example.com';

        $this->entityManager->persist($user);

        $this->assertContains('prePersist', $user->callbacksCalled);
    }

    public function testPostPersistCallback(): void
    {
        $user = new LifecycleTestUser();
        $user->name = 'Test User';
        $user->email = 'test@example.com';

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->assertContains('postPersist', $user->callbacksCalled);
    }

    public function testPreUpdateCallback(): void
    {
        $user = new LifecycleTestUser();
        $user->id = 1; // Simulate existing entity
        $user->name = 'Original User';

        // First, register as managed (simulating loading from DB)
        $uow = $this->entityManager->getUnitOfWork();
        $uow->registerManaged($user, ['id' => 1, 'name' => 'Original User', 'email' => 'test@example.com']);

        // Now modify and persist again - this should trigger preUpdate
        $user->name = 'Updated User';
        $this->entityManager->persist($user);

        $this->assertContains('preUpdate', $user->callbacksCalled);
    }

    public function testPostUpdateCallback(): void
    {
        $user = new LifecycleTestUser();
        $user->id = 1; // Simulate existing entity
        $user->name = 'Original User';

        // First, register as managed (simulating loading from DB)
        $uow = $this->entityManager->getUnitOfWork();
        $uow->registerManaged($user, ['id' => 1, 'name' => 'Original User', 'email' => 'test@example.com']);

        // Now modify and persist again - this should trigger postUpdate on flush
        $user->name = 'Updated User';
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->assertContains('postUpdate', $user->callbacksCalled);
    }

    public function testPreRemoveCallback(): void
    {
        $user = new LifecycleTestUser();
        $user->id = 1;

        $this->entityManager->remove($user);

        $this->assertContains('preRemove', $user->callbacksCalled);
    }

    public function testPostRemoveCallback(): void
    {
        $user = new LifecycleTestUser();
        $user->id = 1;

        // First, register as managed (simulating loading from DB)
        $uow = $this->entityManager->getUnitOfWork();
        $uow->registerManaged($user, ['id' => 1, 'name' => 'Test User', 'email' => 'test@example.com']);

        // Now remove - this should trigger postRemove on flush
        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $this->assertContains('postRemove', $user->callbacksCalled);
    }

    public function testMultipleCallbacksOrder(): void
    {
        $user = new LifecycleTestUser();
        $user->name = 'Test User';
        $user->email = 'test@example.com';

        // Persist new entity
        $this->entityManager->persist($user);
        $this->assertEquals(['prePersist'], $user->callbacksCalled);

        // Flush to trigger post callbacks
        $this->entityManager->flush();
        $this->assertEquals(['prePersist', 'postPersist'], $user->callbacksCalled);

        // Update existing entity
        $user->name = 'Updated Name';
        $this->entityManager->persist($user);
        $this->assertContains('preUpdate', $user->callbacksCalled);

        // Flush updates
        $this->entityManager->flush();
        $this->assertContains('postUpdate', $user->callbacksCalled);
    }
}
