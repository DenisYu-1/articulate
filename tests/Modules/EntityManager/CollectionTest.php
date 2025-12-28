<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Modules\EntityManager\Collection;
use PHPUnit\Framework\TestCase;

class CollectionTestEntity
{
    public int $id;

    public string $name;
}

class CollectionTest extends TestCase
{
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
}
