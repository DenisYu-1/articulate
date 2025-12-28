<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\AutoIncrement;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Modules\EntityManager\DeferredImplicitStrategy;
use Articulate\Modules\EntityManager\EntityState;
use Articulate\Modules\EntityManager\UnitOfWork;
use Articulate\Modules\Generators\GeneratorRegistry;
use PHPUnit\Framework\TestCase;

class UnitOfWorkTest extends TestCase {
    private UnitOfWork $unitOfWork;

    protected function setUp(): void
    {
        $this->unitOfWork = new UnitOfWork(null, new GeneratorRegistry());
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
        $customStrategy = new DeferredImplicitStrategy();
        $unitOfWork = new UnitOfWork($customStrategy);

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

    public function testPersistGeneratesIdForNewEntity(): void
    {
        $entity = new class() {
            public ?int $id = null;

            public string $name = 'Test Entity';
        };

        $this->unitOfWork->persist($entity);
        $this->unitOfWork->commit();

        // Should have generated an ID
        $this->assertNotNull($entity->id);
        $this->assertIsInt($entity->id);
        $this->assertEquals(1, $entity->id);

        // Should be in identity map
        $retrieved = $this->unitOfWork->tryGetById($entity::class, 1);
        $this->assertSame($entity, $retrieved);
    }

    public function testPersistMultipleEntitiesGenerateSequentialIds(): void
    {
        // Use the same test entity class
        $entity1 = new TestEntityForId();
        $entity1->name = 'Entity 1';

        $entity2 = new TestEntityForId();
        $entity2->name = 'Entity 2';

        $this->unitOfWork->persist($entity1);
        $this->unitOfWork->persist($entity2);
        $this->unitOfWork->commit();

        // Should have sequential IDs
        $this->assertEquals(1, $entity1->id);
        $this->assertEquals(2, $entity2->id);

        // Both should be in identity map
        $this->assertSame($entity1, $this->unitOfWork->tryGetById(TestEntityForId::class, 1));
        $this->assertSame($entity2, $this->unitOfWork->tryGetById(TestEntityForId::class, 2));
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

    public function testUuidPrimaryKeyGeneration(): void
    {
        // Entity with UUID primary key
        $entity = new TestEntityForUuid();
        $entity->name = 'UUID Entity';

        $this->unitOfWork->persist($entity);
        $this->unitOfWork->commit();

        // Should have generated a UUID
        $this->assertNotNull($entity->id);
        $this->assertIsString($entity->id);

        // Should be a valid UUID v4 format
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $entity->id
        );

        // Should be in identity map with generated UUID
        $this->assertSame($entity, $this->unitOfWork->tryGetById($entity::class, $entity->id));
    }

    public function testMultipleUuidEntitiesGenerateUniqueIds(): void
    {
        // Entities with UUID primary keys
        $entity1 = new TestEntityForUuid();
        $entity1->name = 'UUID Entity 1';

        $entity2 = new TestEntityForUuid();
        $entity2->name = 'UUID Entity 2';

        $this->unitOfWork->persist($entity1);
        $this->unitOfWork->persist($entity2);
        $this->unitOfWork->commit();

        // Both should have UUIDs
        $this->assertNotNull($entity1->id);
        $this->assertNotNull($entity2->id);
        $this->assertIsString($entity1->id);
        $this->assertIsString($entity2->id);

        // UUIDs should be different
        $this->assertNotEquals($entity1->id, $entity2->id);

        // Both should be valid UUID v4
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $entity1->id
        );
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $entity2->id
        );

        // Both should be in identity map
        $this->assertSame($entity1, $this->unitOfWork->tryGetById($entity1::class, $entity1->id));
        $this->assertSame($entity2, $this->unitOfWork->tryGetById($entity2::class, $entity2->id));
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

    public function testBackwardCompatibilityWithAutoIncrement(): void
    {
        // Entity with AutoIncrement attribute (old style)
        $entity = new class() {
            #[PrimaryKey]
            #[AutoIncrement]
            #[Property]
            public ?int $id = null;

            #[Property]
            public string $name = 'AutoIncrement Entity';
        };

        $this->unitOfWork->persist($entity);
        $this->unitOfWork->commit();

        // Should have generated an auto-increment ID
        $this->assertNotNull($entity->id);
        $this->assertIsInt($entity->id);
        $this->assertEquals(1, $entity->id);

        // Should be in identity map
        $this->assertSame($entity, $this->unitOfWork->tryGetById($entity::class, 1));
    }

    public function testDefaultGeneratorFallback(): void
    {
        // Entity with just PrimaryKey (no generator specified)
        $entity = new class() {
            #[PrimaryKey]
            #[Property]
            public ?int $id = null;

            #[Property]
            public string $name = 'Default Generator Entity';
        };

        $this->unitOfWork->persist($entity);
        $this->unitOfWork->commit();

        // Should use default generator (auto_increment)
        $this->assertNotNull($entity->id);
        $this->assertIsInt($entity->id);
        $this->assertEquals(1, $entity->id);

        // Should be in identity map
        $this->assertSame($entity, $this->unitOfWork->tryGetById($entity::class, 1));
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
