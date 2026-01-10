<?php

namespace Articulate\Tests\Collection;

use Articulate\Collection\Collection;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    public function testConstructsWithItems(): void
    {
        $items = ['item1', 'item2', 'item3'];
        $collection = new Collection($items);

        $this->assertSame($items, $collection->toArray());
    }

    public function testConstructsWithEmptyArray(): void
    {
        $collection = new Collection([]);

        $this->assertSame([], $collection->toArray());
    }

    public function testToArrayReturnsItems(): void
    {
        $items = ['test' => 'value', 'another' => 'item'];
        $collection = new Collection($items);

        $this->assertSame($items, $collection->toArray());
    }

    public function testHandlesDifferentDataTypes(): void
    {
        $items = [
            'string',
            123,
            45.67,
            true,
            null,
            ['nested' => 'array'],
            (object)['property' => 'value']
        ];

        $collection = new Collection($items);

        $this->assertSame($items, $collection->toArray());
    }
}