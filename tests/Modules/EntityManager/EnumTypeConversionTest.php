<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Connection;
use Articulate\Modules\EntityManager\DeferredImplicitStrategy;
use Articulate\Modules\EntityManager\ObjectHydrator;
use Articulate\Modules\EntityManager\QueryExecutor;
use Articulate\Modules\EntityManager\UnitOfWork;
use Articulate\Modules\Generators\GeneratorRegistry;
use Articulate\Schema\EntityMetadataRegistry;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

enum EnumStatus: string {
    case Active = 'active';
    case Inactive = 'inactive';
}

enum EnumPriority: int {
    case Low = 1;
    case High = 2;
}

enum PureStatusEnum {
    case Draft;
    case Published;
}

#[Entity(tableName: 'enum_items')]
class EnumItem {
    #[PrimaryKey]
    public int $id;

    #[Property]
    public EnumStatus $status;

    #[Property]
    public ?EnumPriority $priority = null;

    #[Property]
    public ?PureStatusEnum $pureStatus = null;
}

class EnumTypeConversionTest extends TestCase {
    // ── Hydration: DB string → PHP enum ─────────────────────────────────────

    #[AllowMockObjectsWithoutExpectations]
    public function testHydratesStringBackedEnumFromDb(): void
    {
        $uow = $this->createMock(UnitOfWork::class);
        $hydrator = new ObjectHydrator($uow);

        $entity = $hydrator->hydrate(EnumItem::class, ['id' => 1, 'status' => 'active']);

        $this->assertInstanceOf(EnumItem::class, $entity);
        $this->assertSame(EnumStatus::Active, $entity->status);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testHydratesIntBackedEnumFromDb(): void
    {
        $uow = $this->createMock(UnitOfWork::class);
        $hydrator = new ObjectHydrator($uow);

        $entity = $hydrator->hydrate(EnumItem::class, ['id' => 1, 'status' => 'active', 'priority' => 2]);

        $this->assertSame(EnumPriority::High, $entity->priority);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testHydratesPureEnumByName(): void
    {
        $uow = $this->createMock(UnitOfWork::class);
        $hydrator = new ObjectHydrator($uow);

        $entity = $hydrator->hydrate(EnumItem::class, ['id' => 1, 'status' => 'active', 'pure_status' => 'Published']);

        $this->assertSame(PureStatusEnum::Published, $entity->pureStatus);
    }

    // ── Write path: PHP enum → DB scalar ────────────────────────────────────

    public function testExecuteInsertConvertsBackedEnum(): void
    {
        $connection = $this->createMock(Connection::class);
        $generatorRegistry = $this->createStub(GeneratorRegistry::class);
        $executor = new QueryExecutor($connection, $generatorRegistry);

        $entity = new EnumItem();
        $entity->id = 7;
        $entity->status = EnumStatus::Inactive;

        $connection->expects($this->once())
            ->method('executeQuery')
            ->with(
                $this->stringContains('INSERT INTO'),
                $this->callback(function (array $values) {
                    // status must be the backing scalar 'inactive', not an enum object
                    return in_array('inactive', $values, true);
                })
            );

        $executor->executeInsert($entity);
    }

    public function testExecuteUpdateConvertsBackedEnum(): void
    {
        $connection = $this->createMock(Connection::class);
        $generatorRegistry = $this->createStub(GeneratorRegistry::class);
        $executor = new QueryExecutor($connection, $generatorRegistry);

        $entity = new EnumItem();
        $entity->id = 7;
        $entity->status = EnumStatus::Active;

        $connection->expects($this->once())
            ->method('executeQuery')
            ->with(
                $this->stringContains('UPDATE'),
                $this->callback(function (array $values) {
                    return in_array('inactive', $values, true);
                })
            );

        // Pass enum object as the change value — must be normalized to scalar
        $executor->executeUpdate($entity, ['status' => EnumStatus::Inactive]);
    }

    // ── Change detection: no false positive after round-trip ────────────────

    public function testNoFalsePositiveChangeAfterEnumHydration(): void
    {
        $metadataRegistry = new EntityMetadataRegistry();
        $strategy = new DeferredImplicitStrategy($metadataRegistry);

        $entity = new EnumItem();
        $entity->id = 3;
        $entity->status = EnumStatus::Active;
        $entity->priority = null;
        $entity->pureStatus = null;

        // Simulate what registerManaged does: track with full raw DB row (all columns present)
        $strategy->trackEntity($entity, ['id' => 3, 'status' => 'active', 'priority' => null, 'pure_status' => null]);

        // No modifications — change set must be empty
        $changes = $strategy->computeChangeSet($entity);

        $this->assertEmpty(
            $changes,
            'Expected no changes when enum property matches its DB representation'
        );
    }

    public function testChangeDetectedWhenEnumPropertyChanges(): void
    {
        $metadataRegistry = new EntityMetadataRegistry();
        $strategy = new DeferredImplicitStrategy($metadataRegistry);

        $entity = new EnumItem();
        $entity->id = 3;
        $entity->status = EnumStatus::Active;

        $strategy->trackEntity($entity, ['id' => 3, 'status' => 'active']);

        $entity->status = EnumStatus::Inactive;

        $changes = $strategy->computeChangeSet($entity);

        $this->assertNotEmpty($changes, 'Expected a change when enum property is mutated');
        $this->assertArrayHasKey('status', $changes);
        $this->assertSame('inactive', $changes['status']);
    }
}
