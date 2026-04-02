<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Connection;
use Articulate\Modules\EntityManager\ChangeAggregator;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\EntityManager\UnitOfWork;
use PHPUnit\Framework\TestCase;

#[Entity]
class TestEntityForChangeAggregation {
    #[Property]
    public ?int $id = null;

    #[Property]
    public string $name;

    #[Property]
    public bool $active = true;
}

class ChangeAggregatorTest extends TestCase {
    private ChangeAggregator $aggregator;

    private Connection $connection;

    private EntityManager $entityManager;

    protected function setUp(): void
    {
        $this->connection = $this->createStub(Connection::class);
        $this->entityManager = new EntityManager($this->connection);
        $this->aggregator = new ChangeAggregator(
            $this->entityManager->getMetadataRegistry(),
            $this->entityManager->getUpdateConflictResolutionStrategy(),
        );
    }

    public function testAggregateChangesWithEmptyUnitOfWorks(): void
    {
        $result = $this->aggregator->aggregateChanges([]);

        $this->assertEquals([
            'inserts' => [],
            'updates' => [],
            'deletes' => [],
        ], $result);
    }

    public function testAggregateChangesWithSingleUnitOfWork(): void
    {
        // Use the real UnitOfWork from EntityManager
        $unitOfWork = $this->entityManager->getActiveUnitOfWork();

        // Create and persist entities
        $entity1 = new TestEntityForChangeAggregation();
        $entity1->id = 1;
        $entity1->name = 'Entity1';

        $this->entityManager->persist($entity1);

        // Get the changes that were aggregated
        $result = $this->aggregator->aggregateChanges([$unitOfWork]);

        $this->assertCount(1, $result['inserts']);
        $this->assertCount(0, $result['updates']);
        $this->assertCount(0, $result['deletes']);

        $this->assertSame($entity1, $result['inserts'][0]);
    }

    public function testAggregateChangesWithMultipleUnitOfWorks(): void
    {
        // Use multiple UnitOfWorks
        $unitOfWork1 = $this->entityManager->getActiveUnitOfWork();
        $unitOfWork2 = $this->entityManager->createUnitOfWork();

        $entity1 = new TestEntityForChangeAggregation();
        $entity1->id = 1;
        $entity1->name = 'Entity1';

        $entity2 = new TestEntityForChangeAggregation();
        $entity2->id = 2;
        $entity2->name = 'Entity2';

        $this->entityManager->persist($entity1);
        // Note: entity2 is in a different UOW, but this is simplified for testing

        $result = $this->aggregator->aggregateChanges([$unitOfWork1, $unitOfWork2]);

        $this->assertCount(1, $result['inserts']);
        $this->assertCount(0, $result['updates']);
        $this->assertCount(0, $result['deletes']);
    }

    public function testAggregateChangesWithNoChanges(): void
    {
        $unitOfWork = $this->entityManager->getActiveUnitOfWork();

        $result = $this->aggregator->aggregateChanges([$unitOfWork]);

        $this->assertEquals([
            'inserts' => [],
            'updates' => [],
            'deletes' => [],
        ], $result);
    }

    public function testAggregateChangesWithPersistedEntities(): void
    {
        $unitOfWork = $this->entityManager->getActiveUnitOfWork();

        $entity = new TestEntityForChangeAggregation();
        $entity->id = 1;
        $entity->name = 'Test Entity';

        $this->entityManager->persist($entity);

        $result = $this->aggregator->aggregateChanges([$unitOfWork]);

        $this->assertCount(1, $result['inserts']);
        $this->assertCount(0, $result['updates']);
        $this->assertCount(0, $result['deletes']);
        $this->assertSame($entity, $result['inserts'][0]);
    }

    public function testDeleteWinsOverUpdate(): void
    {
        $metadataRegistry = $this->entityManager->getMetadataRegistry();

        $unitOfWorkUpdate = new UnitOfWork(metadataRegistry: $metadataRegistry);
        $entityToUpdate = new TestEntityForChangeAggregation();
        $entityToUpdate->id = 10;
        $entityToUpdate->name = 'Original';
        $unitOfWorkUpdate->registerManaged($entityToUpdate, ['id' => 10, 'name' => 'Original', 'active' => true]);
        $entityToUpdate->name = 'Updated';

        $unitOfWorkDelete = new UnitOfWork(metadataRegistry: $metadataRegistry);
        $entityToDelete = new TestEntityForChangeAggregation();
        $entityToDelete->id = 10;
        $entityToDelete->name = 'Original';
        $unitOfWorkDelete->registerManaged($entityToDelete, ['id' => 10, 'name' => 'Original', 'active' => true]);
        $unitOfWorkDelete->remove($entityToDelete);

        $result = $this->aggregator->aggregateChanges([$unitOfWorkUpdate, $unitOfWorkDelete]);

        $this->assertCount(0, $result['inserts']);
        $this->assertCount(0, $result['updates']);
        $this->assertCount(1, $result['deletes']);
        $this->assertSame($entityToDelete, $result['deletes'][0]);
    }
}

#[Entity]
class TestEntityForChangeAggregation2 {
    #[Property]
    public ?int $id = null;

    #[Property]
    public string $title;
}
