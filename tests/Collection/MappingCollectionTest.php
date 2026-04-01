<?php

namespace Articulate\Tests\Collection;

use Articulate\Collection\MappingCollection;
use Articulate\Collection\MappingItem;
use PHPUnit\Framework\TestCase;

class MappingCollectionTest extends TestCase {
    public function testConstructWithMappingItems(): void
    {
        $entity1 = new \stdClass();
        $entity2 = new \stdClass();
        $item1 = new MappingItem($entity1, ['role' => 'admin']);
        $item2 = new MappingItem($entity2, ['role' => 'user']);

        $collection = new MappingCollection([$item1, $item2]);

        $this->assertCount(2, $collection);
        $this->assertSame($item1, $collection->toArray()[0]);
        $this->assertSame($item2, $collection->toArray()[1]);
    }

    public function testConstructWithObjects(): void
    {
        $entity1 = new \stdClass();
        $entity2 = new \stdClass();

        $collection = new MappingCollection([$entity1, $entity2]);

        $this->assertCount(2, $collection);
        $this->assertSame($entity1, $collection->toArray()[0]->entity);
        $this->assertSame($entity2, $collection->toArray()[1]->entity);
    }

    public function testConstructWithInvalidItem(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new MappingCollection(['not_an_object']);
    }

    public function testToArray(): void
    {
        $item1 = new MappingItem(new \stdClass());
        $item2 = new MappingItem(new \stdClass());
        $collection = new MappingCollection([$item1, $item2]);

        $array = $collection->toArray();

        $this->assertIsArray($array);
        $this->assertCount(2, $array);
        $this->assertSame($item1, $array[0]);
        $this->assertSame($item2, $array[1]);
    }

    public function testFirst(): void
    {
        $firstEntity = new \stdClass();
        $item1 = new MappingItem($firstEntity);
        $item2 = new MappingItem(new \stdClass());

        $collection = new MappingCollection([$item1, $item2]);

        $this->assertSame($item1, $collection->first());
    }

    public function testFirstOnEmpty(): void
    {
        $collection = new MappingCollection();

        $this->assertNull($collection->first());
    }

    public function testPivotOf(): void
    {
        $entity1 = new \stdClass();
        $entity2 = new \stdClass();
        $pivot1 = ['role' => 'admin'];
        $pivot2 = ['role' => 'editor'];

        $collection = new MappingCollection([
            new MappingItem($entity1, $pivot1),
            new MappingItem($entity2, $pivot2),
        ]);

        $this->assertSame($pivot1, $collection->pivotOf($entity1));
        $this->assertSame($pivot2, $collection->pivotOf($entity2));
        $this->assertNull($collection->pivotOf(new \stdClass()));
    }

    public function testCount(): void
    {
        $this->assertCount(0, new MappingCollection());
        $this->assertCount(3, new MappingCollection([
            new MappingItem(new \stdClass()),
            new MappingItem(new \stdClass()),
            new MappingItem(new \stdClass()),
        ]));
    }

    public function testIterable(): void
    {
        $item1 = new MappingItem(new \stdClass());
        $item2 = new MappingItem(new \stdClass());
        $collection = new MappingCollection([$item1, $item2]);

        $iterated = [];
        foreach ($collection as $item) {
            $iterated[] = $item;
        }

        $this->assertCount(2, $iterated);
        $this->assertSame($item1, $iterated[0]);
        $this->assertSame($item2, $iterated[1]);
    }
}
