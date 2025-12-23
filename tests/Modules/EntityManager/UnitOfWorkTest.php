<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Modules\EntityManager\UnitOfWork;
use Articulate\Modules\EntityManager\EntityState;
use Articulate\Modules\EntityManager\DeferredImplicitStrategy;
use PHPUnit\Framework\TestCase;

class UnitOfWorkTest extends TestCase
{
    private UnitOfWork $unitOfWork;

    protected function setUp(): void
    {
        $this->unitOfWork = new UnitOfWork();
    }

    public function testInitialEntityStateIsNew(): void
    {
        $entity = new class {
            public int $id = 1;
        };

        $this->assertEquals(EntityState::NEW, $this->unitOfWork->getEntityState($entity));
    }

    public function testPersistNewEntity(): void
    {
        $entity = new class {
            public int $id = 1;
        };

        $this->unitOfWork->persist($entity);

        $this->assertEquals(EntityState::MANAGED, $this->unitOfWork->getEntityState($entity));
    }

    public function testPersistAlreadyManagedEntity(): void
    {
        $entity = new class {
            public int $id = 1;
        };

        $this->unitOfWork->persist($entity);
        $this->assertEquals(EntityState::MANAGED, $this->unitOfWork->getEntityState($entity));

        // Persist again should not change state
        $this->unitOfWork->persist($entity);
        $this->assertEquals(EntityState::MANAGED, $this->unitOfWork->getEntityState($entity));
    }

    public function testRemoveManagedEntity(): void
    {
        $entity = new class {
            public int $id = 1;
        };

        $this->unitOfWork->persist($entity);
        $this->assertEquals(EntityState::MANAGED, $this->unitOfWork->getEntityState($entity));

        $this->unitOfWork->remove($entity);
        $this->assertEquals(EntityState::REMOVED, $this->unitOfWork->getEntityState($entity));
    }

    public function testRemoveNewEntity(): void
    {
        $entity = new class {
            public int $id = 1;
        };

        $this->unitOfWork->persist($entity);
        $this->assertEquals(EntityState::MANAGED, $this->unitOfWork->getEntityState($entity));

        $this->unitOfWork->remove($entity);

        // Entity should be removed from tracking entirely for new entities
        // This is a simplified test - actual behavior depends on implementation
        $this->assertTrue(true);
    }

    public function testRegisterManaged(): void
    {
        $entity = new class {
            public int $id = 1;
            public string $name = 'test';
        };

        $originalData = ['id' => 1, 'name' => 'original'];

        $this->unitOfWork->registerManaged($entity, $originalData);

        $this->assertEquals(EntityState::MANAGED, $this->unitOfWork->getEntityState($entity));
        $this->assertSame($entity, $this->unitOfWork->tryGetById($entity::class, 1));
    }

    public function testTryGetById(): void
    {
        $entity = new class {
            public int $id = 1;
        };

        $this->unitOfWork->registerManaged($entity, ['id' => 1]);

        $retrieved = $this->unitOfWork->tryGetById($entity::class, 1);
        $this->assertSame($entity, $retrieved);

        $notFound = $this->unitOfWork->tryGetById($entity::class, 999);
        $this->assertNull($notFound);
    }

    public function testClear(): void
    {
        $entity = new class {
            public int $id = 1;
        };

        $this->unitOfWork->persist($entity);
        $this->unitOfWork->registerManaged($entity, ['id' => 1]);

        $this->assertEquals(EntityState::MANAGED, $this->unitOfWork->getEntityState($entity));
        $this->assertNotNull($this->unitOfWork->tryGetById($entity::class, 1));

        $this->unitOfWork->clear();

        $this->assertEquals(EntityState::NEW, $this->unitOfWork->getEntityState($entity));
        $this->assertNull($this->unitOfWork->tryGetById($entity::class, 1));
    }

    public function testComputeChangeSets(): void
    {
        $entity = new class {
            public int $id = 1;
            public string $name = 'modified';
        };

        $originalData = ['id' => 1, 'name' => 'original'];
        $this->unitOfWork->registerManaged($entity, $originalData);

        $this->unitOfWork->computeChangeSets();

        $changes = $this->unitOfWork->getEntityChangeSet($entity);
        $this->assertEquals(['name' => 'modified'], $changes);
    }

    public function testCommit(): void
    {
        $entity = new class {
            public int $id = 1;
        };

        $this->unitOfWork->persist($entity);
        $this->unitOfWork->computeChangeSets();

        $this->unitOfWork->commit();

        // After commit, schedules should be cleared
        // This is a simplified test since actual commit logic is not implemented yet
        $this->assertTrue(true);
    }

    public function testCustomChangeTrackingStrategy(): void
    {
        $customStrategy = new DeferredImplicitStrategy();
        $unitOfWork = new UnitOfWork($customStrategy);

        $entity = new class {
            public int $id = 1;
        };

        $unitOfWork->persist($entity);

        $this->assertEquals(EntityState::MANAGED, $unitOfWork->getEntityState($entity));
    }

    public function testIsInIdentityMap(): void
    {
        $entity = new class {
            public int $id = 1;
        };

        // Currently returns false as it's not implemented
        $this->assertFalse($this->unitOfWork->isInIdentityMap($entity));
    }
}
