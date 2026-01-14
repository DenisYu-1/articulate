<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Connection;
use Articulate\Modules\EntityManager\ChangeAggregator;
use Articulate\Modules\EntityManager\Collection;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\EntityManager\EntityMetadata;
use Articulate\Modules\EntityManager\ObjectHydrator;
use Articulate\Modules\EntityManager\RelationshipLoader;
use Articulate\Modules\EntityManager\UnitOfWork;
use PHPUnit\Framework\TestCase;

#[Entity]
class EntityManagerClassesTestEntity {
    public int $id;
}

class EntityManagerClassesTest extends TestCase {
    public function testChangeAggregatorCanBeInstantiated(): void
    {
        $changeAggregator = new ChangeAggregator();
        $this->assertInstanceOf(ChangeAggregator::class, $changeAggregator);
    }

    public function testCollectionCanBeInstantiated(): void
    {
        $collection = new Collection();
        $this->assertInstanceOf(Collection::class, $collection);
    }

    public function testEntityManagerCanBeInstantiated(): void
    {
        $connection = $this->createMock(Connection::class);
        $entityManager = new EntityManager($connection);
        $this->assertInstanceOf(EntityManager::class, $entityManager);
    }

    public function testEntityMetadataCanBeInstantiated(): void
    {
        $entityMetadata = new EntityMetadata(EntityManagerClassesTestEntity::class);
        $this->assertInstanceOf(EntityMetadata::class, $entityMetadata);
    }

    public function testObjectHydratorCanBeInstantiated(): void
    {
        $unitOfWork = $this->createMock(UnitOfWork::class);
        $relationshipLoader = $this->createMock(RelationshipLoader::class);
        $objectHydrator = new ObjectHydrator($unitOfWork, $relationshipLoader);
        $this->assertInstanceOf(ObjectHydrator::class, $objectHydrator);
    }

    public function testRelationshipLoaderCanBeInstantiated(): void
    {
        $entityManager = $this->createMock(EntityManager::class);
        $metadataRegistry = $this->createMock(\Articulate\Modules\EntityManager\EntityMetadataRegistry::class);
        $relationshipLoader = new RelationshipLoader($entityManager, $metadataRegistry);
        $this->assertInstanceOf(RelationshipLoader::class, $relationshipLoader);
    }

    public function testUnitOfWorkCanBeInstantiated(): void
    {
        $connection = $this->createMock(Connection::class);
        $changeTrackingStrategy = $this->createMock(\Articulate\Modules\EntityManager\ChangeTrackingStrategy::class);
        $generatorRegistry = $this->createMock(\Articulate\Modules\Generators\GeneratorRegistry::class);
        $callbackManager = $this->createMock(\Articulate\Modules\EntityManager\LifecycleCallbackManager::class);
        $metadataRegistry = $this->createMock(\Articulate\Modules\EntityManager\EntityMetadataRegistry::class);

        $unitOfWork = new UnitOfWork(
            $connection,
            $changeTrackingStrategy,
            $generatorRegistry,
            $callbackManager,
            $metadataRegistry
        );
        $this->assertInstanceOf(UnitOfWork::class, $unitOfWork);
    }
}