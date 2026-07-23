<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Modules\EntityManager\Collection;
use PHPUnit\Framework\TestCase;

class CollectionTestEntity {
    public int $id;

    public string $name;
}

class CollectionTest extends TestCase {
    private Collection $collection;

    protected function setUp(): void
    {
        $this->collection = new Collection();
    }

    public function testAddAndCount(): void
    {
        $entity1 = new CollectionTestEntity();
        $entity1->id = 1;
        $entity1->name = 'Entity 1';

        $entity2 = new CollectionTestEntity();
        $entity2->id = 2;
        $entity2->name = 'Entity 2';

        $this->collection->add($entity1);
        $this->collection->add($entity2);

        $this->assertEquals(2, $this->collection->count());
        $this->assertTrue($this->collection->isDirty());
    }

    public function testRemove(): void
    {
        $entity = new CollectionTestEntity();
        $entity->id = 1;
        $entity->name = 'Test Entity';

        $this->collection->add($entity);
        $this->assertEquals(1, $this->collection->count());

        $this->collection->remove($entity);
        $this->assertEquals(0, $this->collection->count());
        $this->assertTrue($this->collection->isDirty());
    }

    public function testContains(): void
    {
        $entity = new CollectionTestEntity();
        $entity->id = 1;
        $entity->name = 'Test Entity';

        $this->assertFalse($this->collection->contains($entity));

        $this->collection->add($entity);
        $this->assertTrue($this->collection->contains($entity));
    }

    public function testClear(): void
    {
        $this->collection->add(new CollectionTestEntity());
        $this->collection->add(new CollectionTestEntity());

        $this->assertEquals(2, $this->collection->count());

        $this->collection->clear();
        $this->assertEquals(0, $this->collection->count());
        $this->assertTrue($this->collection->isDirty());
    }

    public function testToArray(): void
    {
        $entity1 = new CollectionTestEntity();
        $entity1->id = 1;

        $entity2 = new CollectionTestEntity();
        $entity2->id = 2;

        $this->collection->add($entity1);
        $this->collection->add($entity2);

        $array = $this->collection->toArray();
        $this->assertIsArray($array);
        $this->assertCount(2, $array);
        $this->assertSame($entity1, $array[0]);
        $this->assertSame($entity2, $array[1]);
    }

    public function testMarkClean(): void
    {
        $this->collection->add(new CollectionTestEntity());
        $this->assertTrue($this->collection->isDirty());

        $this->collection->markClean();
        $this->assertFalse($this->collection->isDirty());
    }

    public function testFilter(): void
    {
        $entity1 = new CollectionTestEntity();
        $entity1->id = 1;
        $entity1->name = 'First';

        $entity2 = new CollectionTestEntity();
        $entity2->id = 2;
        $entity2->name = 'Second';

        $this->collection->add($entity1);
        $this->collection->add($entity2);

        $filtered = $this->collection->filter(fn ($entity) => $entity->id === 1);

        $this->assertEquals(1, $filtered->count());
        $this->assertSame($entity1, $filtered->first());
    }

    public function testMap(): void
    {
        $entity1 = new CollectionTestEntity();
        $entity1->name = 'First';

        $entity2 = new CollectionTestEntity();
        $entity2->name = 'Second';

        $this->collection->add($entity1);
        $this->collection->add($entity2);

        $names = $this->collection->map(fn ($entity) => $entity->name);

        $this->assertEquals(['First', 'Second'], $names);
    }

    public function testFirstAndLast(): void
    {
        $this->assertNull($this->collection->first());
        $this->assertNull($this->collection->last());

        $entity1 = new CollectionTestEntity();
        $entity1->id = 1;

        $entity2 = new CollectionTestEntity();
        $entity2->id = 2;

        $this->collection->add($entity1);
        $this->collection->add($entity2);

        $this->assertSame($entity1, $this->collection->first());
        $this->assertSame($entity2, $this->collection->last());
    }

    public function testIsEmpty(): void
    {
        $this->assertTrue($this->collection->isEmpty());
        $this->assertFalse($this->collection->isNotEmpty());

        $this->collection->add(new CollectionTestEntity());

        $this->assertFalse($this->collection->isEmpty());
        $this->assertTrue($this->collection->isNotEmpty());
    }

    public function testArrayAccess(): void
    {
        $entity1 = new CollectionTestEntity();
        $entity1->id = 1;

        $entity2 = new CollectionTestEntity();
        $entity2->id = 2;

        $this->collection[0] = $entity1;
        $this->collection[1] = $entity2;

        $this->assertTrue(isset($this->collection[0]));
        $this->assertSame($entity1, $this->collection[0]);
        $this->assertSame($entity2, $this->collection[1]);

        unset($this->collection[0]);
        // After re-indexing, the element at index 1 becomes index 0
        $this->assertTrue(isset($this->collection[0]));
        $this->assertSame($entity2, $this->collection[0]);
        $this->assertEquals(1, $this->collection->count());
    }

    public function testIterator(): void
    {
        $entity1 = new CollectionTestEntity();
        $entity1->id = 1;

        $entity2 = new CollectionTestEntity();
        $entity2->id = 2;

        $this->collection->add($entity1);
        $this->collection->add($entity2);

        $ids = [];
        foreach ($this->collection as $entity) {
            $ids[] = $entity->id;
        }

        $this->assertEquals([1, 2], $ids);
    }

    public function testConstructorItemsAreInAddedItems(): void
    {
        $a = new CollectionTestEntity();
        $b = new CollectionTestEntity();
        $col = new Collection([$a, $b]);

        $this->assertSame([$a, $b], $col->getAddedItems());
        $this->assertSame([], $col->getRemovedItems());
        $this->assertTrue($col->isDirty());
    }

    public function testMarkCleanResetsTrackingArrays(): void
    {
        $a = new CollectionTestEntity();
        $col = new Collection([$a]);

        $col->markClean();

        $this->assertSame([], $col->getAddedItems());
        $this->assertSame([], $col->getRemovedItems());
        $this->assertFalse($col->isDirty());
    }

    public function testAddTrackedInAddedItems(): void
    {
        $a = new CollectionTestEntity();
        $this->collection->add($a);

        $this->assertSame([$a], $this->collection->getAddedItems());
        $this->assertSame([], $this->collection->getRemovedItems());
    }

    public function testRemoveOfAddedItemCancelsBothSides(): void
    {
        $a = new CollectionTestEntity();
        $this->collection->add($a);
        $this->collection->remove($a);

        $this->assertSame([], $this->collection->getAddedItems());
        $this->assertSame([], $this->collection->getRemovedItems());
    }

    public function testRemoveOfLoadedItemTrackedInRemovedItems(): void
    {
        $a = new CollectionTestEntity();
        $col = new Collection([$a]);
        $col->markClean();

        $col->remove($a);

        $this->assertSame([], $col->getAddedItems());
        $this->assertSame([$a], $col->getRemovedItems());
    }

    public function testClearMovesLoadedItemsToRemovedAndResetsAdded(): void
    {
        $a = new CollectionTestEntity();
        $b = new CollectionTestEntity();
        $col = new Collection([$a, $b]);
        $col->markClean();

        $newItem = new CollectionTestEntity();
        $col->add($newItem);
        $col->clear();

        $this->assertSame([], $col->getAddedItems());
        $this->assertSame([$a, $b], $col->getRemovedItems());
        $this->assertSame(0, $col->count());
    }

    // ── Mutation killers ──────────────────────────────────────────────────────

    public function testRemoveOnlyRemovesOneItemFromAddedItems(): void
    {
        $a = new CollectionTestEntity();
        $b = new CollectionTestEntity();

        $col = new Collection();
        $col->add($a);
        $col->add($b);

        $col->remove($a);

        $this->assertSame([$b], $col->getAddedItems(), 'Only A must be removed from addedItems; B must remain');
        $this->assertSame([], $col->getRemovedItems());
    }

    public function testClearDoesNotPushUnpersistedItemsToRemovedItems(): void
    {
        $a = new CollectionTestEntity();
        $b = new CollectionTestEntity();

        $col = new Collection();
        $col->add($a);
        $col->add($b);
        $col->clear();

        $this->assertSame([], $col->getAddedItems());
        $this->assertSame([], $col->getRemovedItems(), 'Items never persisted must not appear in removedItems');
    }

    public function testOffsetSetNullRoutesToAddAndTracksInAddedItems(): void
    {
        $entity = new CollectionTestEntity();

        $col = new Collection();
        $col[] = $entity; // null offset

        $this->assertSame([$entity], $col->getAddedItems(), 'Appended item must appear in addedItems');
        $this->assertSame(1, $col->count());
        $this->assertSame($entity, $col[0]);
    }

    public function testOffsetSetNumericMarksDirty(): void
    {
        $entity = new CollectionTestEntity();

        $col = new Collection();
        $col->markClean(); // ensure clean start
        $col[0] = $entity; // else branch: items[0] = entity, isDirty = true

        $this->assertTrue($col->isDirty(), 'Setting a numeric offset must mark the collection dirty');
    }
}
