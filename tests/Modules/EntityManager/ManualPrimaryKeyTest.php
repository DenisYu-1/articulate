<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Connection;
use Articulate\Modules\EntityManager\DeferredImplicitStrategy;
use Articulate\Modules\EntityManager\EntityState;
use Articulate\Modules\EntityManager\QueryExecutor;
use Articulate\Modules\EntityManager\UnitOfWork;
use Articulate\Modules\Generators\GeneratorRegistry;
use Articulate\Schema\EntityMetadataRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Entity with a manually-assigned (non-auto-increment) primary key
 * and a custom column name via #[Property(name: ...)].
 */
#[Entity(tableName: 'product_stock')]
class InventorySlotEntity {
    #[PrimaryKey]
    #[Property(name: 'product_id')]
    public int $productId;

    #[Property]
    public int $stock;
}

class ManualPrimaryKeyTest extends TestCase {
    // ── UnitOfWork: explicit-PK entity is scheduled for INSERT, not ignored ─

    public function testPersistWithExplicitPkSchedulesInsert(): void
    {
        $uow = new UnitOfWork();

        $slot = new InventorySlotEntity();
        $slot->productId = 42;
        $slot->stock = 25;

        // Before persist: entity is NEW
        $this->assertSame(EntityState::NEW, $uow->getEntityState($slot));

        $uow->persist($slot);

        // After persist: entity is MANAGED and queued for insert
        $this->assertSame(EntityState::MANAGED, $uow->getEntityState($slot));

        $changes = $uow->getChangeSets();
        $this->assertContains($slot, $changes['inserts']);
        $this->assertEmpty($changes['deletes']);
    }

    public function testRePersistLoadedEntitySchedulesUpdateNotInsert(): void
    {
        $uow = new UnitOfWork();

        $slot = new InventorySlotEntity();
        $slot->productId = 42;
        $slot->stock = 25;

        // Simulate an entity that was loaded from DB (already managed)
        $uow->registerManaged($slot, ['product_id' => 42, 'stock' => 25]);

        $this->assertSame(EntityState::MANAGED, $uow->getEntityState($slot));

        // Re-persisting a managed entity must NOT add it to inserts again
        $uow->persist($slot);

        $changes = $uow->getChangeSets();
        $this->assertNotContains($slot, $changes['inserts']);
    }

    // ── QueryExecutor: INSERT includes manually-set PK column ───────────────

    public function testExecuteInsertIncludesManualPk(): void
    {
        $connection = $this->createMock(Connection::class);
        $generatorRegistry = $this->createStub(GeneratorRegistry::class);
        $executor = new QueryExecutor($connection, $generatorRegistry);

        $slot = new InventorySlotEntity();
        $slot->productId = 99;
        $slot->stock = 10;

        $connection->expects($this->once())
            ->method('executeQuery')
            ->with(
                $this->stringContains('INSERT INTO product_stock'),
                $this->callback(function (array $values) {
                    return in_array(99, $values, true) && in_array(10, $values, true);
                })
            );

        $result = $executor->executeInsert($slot);

        $this->assertSame(99, $result);
    }

    // ── QueryExecutor: UPDATE resolves column by column name, not field name ─

    public function testExecuteUpdateResolvesCustomColumnName(): void
    {
        $connection = $this->createMock(Connection::class);
        $generatorRegistry = $this->createStub(GeneratorRegistry::class);
        $executor = new QueryExecutor($connection, $generatorRegistry);

        $slot = new InventorySlotEntity();
        $slot->productId = 42;
        $slot->stock = 30;

        $connection->expects($this->once())
            ->method('executeQuery')
            ->with(
                $this->logicalAnd(
                    $this->stringContains('UPDATE product_stock'),
                    $this->stringContains('stock = ?')
                ),
                $this->equalTo([30, 42])
            );

        // Changes keyed by COLUMN name (as DeferredImplicitStrategy produces them)
        $executor->executeUpdate($slot, ['stock' => 30]);
    }

    // ── Change tracking: custom column names round-trip correctly ───────────

    public function testNoSpuriousUpdateAfterLoadWithCustomColumnName(): void
    {
        $metadataRegistry = new EntityMetadataRegistry();
        $strategy = new DeferredImplicitStrategy($metadataRegistry);

        $slot = new InventorySlotEntity();
        $slot->productId = 42;
        $slot->stock = 25;

        // Simulate registerManaged with raw DB data keyed by column names
        $strategy->trackEntity($slot, ['product_id' => 42, 'stock' => 25]);

        $changes = $strategy->computeChangeSet($slot);

        $this->assertEmpty($changes, 'No spurious changes expected when nothing was modified');
    }

    public function testChangeDetectedForCustomColumnNameProperty(): void
    {
        $metadataRegistry = new EntityMetadataRegistry();
        $strategy = new DeferredImplicitStrategy($metadataRegistry);

        $slot = new InventorySlotEntity();
        $slot->productId = 42;
        $slot->stock = 25;

        $strategy->trackEntity($slot, ['product_id' => 42, 'stock' => 25]);

        $slot->stock = 99;

        $changes = $strategy->computeChangeSet($slot);

        $this->assertNotEmpty($changes);
        $this->assertArrayHasKey('stock', $changes);
        $this->assertSame(99, $changes['stock']);
    }
}
