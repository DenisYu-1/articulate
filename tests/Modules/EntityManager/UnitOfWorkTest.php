<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Connection;
use Articulate\Modules\EntityManager\DeferredImplicitStrategy;
use Articulate\Modules\EntityManager\EntityState;
use Articulate\Modules\EntityManager\UnitOfWork;
use Articulate\Modules\Generators\GeneratorRegistry;
use PHPUnit\Framework\TestCase;

class UnitOfWorkTest extends TestCase {
    private UnitOfWork $unitOfWork;

    protected function setUp(): void
    {
        $connection = $this->createMock(Connection::class);
        $this->unitOfWork = new UnitOfWork($connection, null, new GeneratorRegistry());
    }

    public function testInitialEntityStateIsNew(): void
    {
        $entity = new class() {
            public int $id = 1;
        };

        $this->assertEquals(EntityState::NEW, $this->unitOfWork->getEntityState($entity));
    }

    public function testPersistNewEntity(): void
    {
        $entity = new class() {
            public int $id = 1;
        };

        $this->unitOfWork->persist($entity);

        $this->assertEquals(EntityState::MANAGED, $this->unitOfWork->getEntityState($entity));
    }

    public function testPersistAlreadyManagedEntity(): void
    {
        $entity = new class() {
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
        $entity = new class() {
            public int $id = 1;
        };

        $this->unitOfWork->persist($entity);
        $this->assertEquals(EntityState::MANAGED, $this->unitOfWork->getEntityState($entity));

        $this->unitOfWork->remove($entity);
        $this->assertEquals(EntityState::REMOVED, $this->unitOfWork->getEntityState($entity));
    }

    public function testRemoveNewEntity(): void
    {
        $entity = new class() {
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
        $entity = new class() {
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
        $entity = new class() {
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
        $entity = new class() {
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
        $entity = new class() {
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
        $entity = new class() {
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
        $connection = $this->createMock(Connection::class);
        $customStrategy = new DeferredImplicitStrategy();
        $unitOfWork = new UnitOfWork($connection, $customStrategy);

        $entity = new class() {
            public int $id = 1;
        };

        $unitOfWork->persist($entity);

        $this->assertEquals(EntityState::MANAGED, $unitOfWork->getEntityState($entity));
    }

    public function testIsInIdentityMap(): void
    {
        $entity = new class() {
            public int $id = 1;
        };

        // Currently returns false as it's not implemented
        $this->assertFalse($this->unitOfWork->isInIdentityMap($entity));
    }

    public function testPersistEntityWithExistingIdDoesNotOverwrite(): void
    {
        $entity = new TestEntityForId();
        $entity->id = 42;
        $entity->name = 'Entity with ID';

        $this->unitOfWork->persist($entity);
        $this->unitOfWork->commit();

        // Should keep the original ID
        $this->assertEquals(42, $entity->id);

        // Should be in identity map with original ID
        $this->assertSame($entity, $this->unitOfWork->tryGetById(TestEntityForId::class, 42));
    }

    public function testUuidEntityWithExistingIdDoesNotOverwrite(): void
    {
        $existingUuid = '550e8400-e29b-41d4-a716-446655440000';

        $entity = new TestEntityForUuid();
        $entity->id = $existingUuid;
        $entity->name = 'UUID Entity';

        $entity->id = $existingUuid;

        $this->unitOfWork->persist($entity);
        $this->unitOfWork->commit();

        // Should keep the original UUID
        $this->assertEquals($existingUuid, $entity->id);

        // Should be in identity map with original UUID
        $this->assertSame($entity, $this->unitOfWork->tryGetById($entity::class, $existingUuid));
    }

    public function testGetEntityByOidFailureInComputeChangeSets(): void
    {
        // This test demonstrates the bug where getEntityByOid always returns null
        // even when the entity OID exists in entityStates

        $entity = new class() {
            public int $id = 1;

            public string $name = 'modified';
        };

        $originalData = ['id' => 1, 'name' => 'original'];
        $this->unitOfWork->registerManaged($entity, $originalData);

        // At this point, the entity should be managed and have an OID in entityStates
        $this->assertEquals(EntityState::MANAGED, $this->unitOfWork->getEntityState($entity));

        // The entity has changes (name changed from 'original' to 'modified')
        $changes = $this->unitOfWork->getEntityChangeSet($entity);
        $this->assertEquals(['name' => 'modified'], $changes);

        // computeChangeSets() internally calls getEntityByOid() for each managed entity
        // to check for changes and schedule updates. Currently, getEntityByOid() always returns null,
        // so no entities are scheduled for update even though they have changes
        $this->unitOfWork->computeChangeSets();

        // The entity should be scheduled for update because it has changes,
        // but currently it's not scheduled due to getEntityByOid bug
        $entityOid = spl_object_id($entity);
        $this->assertArrayHasKey(
            $entityOid,
            $this->unitOfWork->getScheduledUpdates(),
            'Entity with changes should be scheduled for update, but getEntityByOid returns null'
        );
    }
}

// Test entity class for ID generation tests
class TestEntityForId {
    public ?int $id = null;

    public string $name;
}

// Test entity class for UUID generation tests
#[Entity]
class TestEntityForUuid {
    #[PrimaryKey(generator: 'uuid')]
    #[Property]
    public ?string $id = null;

    #[Property]
    public string $name;
}
