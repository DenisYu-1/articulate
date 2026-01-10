<?php

namespace Articulate\Tests\Collection;

use Articulate\Collection\MappingCollection;
use Articulate\Collection\MappingItem;
use PHPUnit\Framework\TestCase;
use stdClass;

class MappingCollectionTest extends TestCase
{
    public function testConstructsWithEmptyArray(): void
    {
        $collection = new MappingCollection();

        $this->assertSame([], $collection->toArray());
        $this->assertNull($collection->first());
    }

    public function testConstructsWithMappingItems(): void
    {
        $item1 = new MappingItem(new stdClass(), ['role' => 'admin']);
        $item2 = new MappingItem(new stdClass(), ['role' => 'user']);

        $collection = new MappingCollection([$item1, $item2]);

        $this->assertSame([$item1, $item2], $collection->toArray());
        $this->assertSame($item1, $collection->first());
    }

    public function testConstructsWithRawDataAndConvertsToMappingItems(): void
    {
        $entity1 = new stdClass();
        $entity2 = new stdClass();

        $collection = new MappingCollection([$entity1, $entity2]);

        $items = $collection->toArray();
        $this->assertCount(2, $items);
        $this->assertInstanceOf(MappingItem::class, $items[0]);
        $this->assertInstanceOf(MappingItem::class, $items[1]);
        $this->assertSame($entity1, $items[0]->entity);
        $this->assertSame($entity2, $items[1]->entity);
        $this->assertSame([], $items[0]->pivot);
        $this->assertSame([], $items[1]->pivot);
    }

    public function testConstructsWithMixedData(): void
    {
        $entity1 = new stdClass();
        $item2 = new MappingItem(new stdClass(), ['role' => 'admin']);

        $collection = new MappingCollection([$entity1, $item2]);

        $items = $collection->toArray();
        $this->assertCount(2, $items);
        $this->assertInstanceOf(MappingItem::class, $items[0]);
        $this->assertInstanceOf(MappingItem::class, $items[1]);
        $this->assertSame($entity1, $items[0]->entity);
        $this->assertSame([], $items[0]->pivot);
        $this->assertSame($item2, $items[1]);
    }

    public function testToArrayReturnsMappingItems(): void
    {
        $item1 = new MappingItem(new stdClass());
        $item2 = new MappingItem(new stdClass());

        $collection = new MappingCollection([$item1, $item2]);

        $this->assertSame([$item1, $item2], $collection->toArray());
    }

    public function testFirstReturnsFirstItem(): void
    {
        $item1 = new MappingItem(new stdClass());
        $item2 = new MappingItem(new stdClass());

        $collection = new MappingCollection([$item1, $item2]);

        $this->assertSame($item1, $collection->first());
    }

    public function testFirstReturnsNullWhenEmpty(): void
    {
        $collection = new MappingCollection();

        $this->assertNull($collection->first());
    }

    public function testPivotOfReturnsPivotDataForEntity(): void
    {
        $entity1 = new stdClass();
        $entity2 = new stdClass();
        $pivot1 = ['role' => 'admin', 'created_at' => '2023-01-01'];
        $pivot2 = ['role' => 'user', 'created_at' => '2023-01-02'];

        $collection = new MappingCollection([
            new MappingItem($entity1, $pivot1),
            new MappingItem($entity2, $pivot2)
        ]);

        $this->assertSame($pivot1, $collection->pivotOf($entity1));
        $this->assertSame($pivot2, $collection->pivotOf($entity2));
    }

    public function testPivotOfReturnsNullForUnknownEntity(): void
    {
        $knownEntity = new stdClass();
        $unknownEntity = new stdClass();

        $collection = new MappingCollection([
            new MappingItem($knownEntity, ['role' => 'admin'])
        ]);

        $this->assertNull($collection->pivotOf($unknownEntity));
    }

    public function testPivotOfWithEmptyCollection(): void
    {
        $collection = new MappingCollection();
        $entity = new stdClass();

        $this->assertNull($collection->pivotOf($entity));
    }

    public function testGetIteratorReturnsTraversable(): void
    {
        $item1 = new MappingItem(new stdClass());
        $item2 = new MappingItem(new stdClass());

        $collection = new MappingCollection([$item1, $item2]);

        $iterator = $collection->getIterator();
        $this->assertInstanceOf(\Traversable::class, $iterator);

        // Test iteration
        $items = [];
        foreach ($iterator as $item) {
            $items[] = $item;
        }

        $this->assertSame([$item1, $item2], $items);
    }

    public function testIterationWithForeach(): void
    {
        $item1 = new MappingItem(new stdClass(), ['id' => 1]);
        $item2 = new MappingItem(new stdClass(), ['id' => 2]);
        $item3 = new MappingItem(new stdClass(), ['id' => 3]);

        $collection = new MappingCollection([$item1, $item2, $item3]);

        $ids = [];
        foreach ($collection as $item) {
            $ids[] = $item->pivotValue('id');
        }

        $this->assertSame([1, 2, 3], $ids);
    }

    public function testHandlesLargeCollections(): void
    {
        $items = [];
        for ($i = 0; $i < 1000; $i++) {
            $items[] = new MappingItem(new stdClass(), ['index' => $i]);
        }

        $collection = new MappingCollection($items);

        $this->assertCount(1000, $collection->toArray());

        // Test first item
        $first = $collection->first();
        $this->assertInstanceOf(MappingItem::class, $first);
        $this->assertSame(0, $first->pivotValue('index'));

        // Test pivotOf
        $this->assertSame(['index' => 500], $collection->pivotOf($items[500]->entity));
    }

    public function testPivotOfWithIdenticalObjects(): void
    {
        $entity = new stdClass();
        $entity->id = 1;

        $collection = new MappingCollection([
            new MappingItem($entity, ['role' => 'admin'])
        ]);

        // Same object reference
        $this->assertSame(['role' => 'admin'], $collection->pivotOf($entity));

        // Different object with same properties (should not match)
        $differentEntity = new stdClass();
        $differentEntity->id = 1;

        $this->assertNull($collection->pivotOf($differentEntity));
    }
}