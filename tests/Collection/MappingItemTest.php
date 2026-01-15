<?php

namespace Articulate\Tests\Collection;

use Articulate\Collection\MappingItem;
use PHPUnit\Framework\TestCase;

class MappingItemTest extends TestCase {
    public function testConstructsWithEntityAndEmptyPivot(): void
    {
        $entity = new \stdClass();
        $mappingItem = new MappingItem($entity);

        $this->assertSame($entity, $mappingItem->entity);
        $this->assertSame([], $mappingItem->pivot);
    }

    public function testConstructsWithEntityAndPivotData(): void
    {
        $entity = new \stdClass();
        $pivot = ['role' => 'admin', 'created_at' => '2023-01-01'];
        $mappingItem = new MappingItem($entity, $pivot);

        $this->assertSame($entity, $mappingItem->entity);
        $this->assertSame($pivot, $mappingItem->pivot);
    }

    public function testPivotReturnsPivotData(): void
    {
        $pivot = ['key' => 'value', 'another' => 'data'];
        $mappingItem = new MappingItem(new \stdClass(), $pivot);

        $this->assertSame($pivot, $mappingItem->pivot());
    }

    public function testPivotValueReturnsExistingValue(): void
    {
        $pivot = ['role' => 'admin', 'status' => 'active'];
        $mappingItem = new MappingItem(new \stdClass(), $pivot);

        $this->assertSame('admin', $mappingItem->pivotValue('role'));
        $this->assertSame('active', $mappingItem->pivotValue('status'));
    }

    public function testPivotValueReturnsDefaultForMissingKey(): void
    {
        $pivot = ['role' => 'admin'];
        $mappingItem = new MappingItem(new \stdClass(), $pivot);

        $this->assertNull($mappingItem->pivotValue('missing_key'));
        $this->assertSame('default_value', $mappingItem->pivotValue('missing_key', 'default_value'));
    }

    public function testPivotValueWithDifferentDataTypes(): void
    {
        $pivot = [
            'string' => 'text',
            'integer' => 42,
            'float' => 3.14,
            'boolean' => true,
            'null' => null,
            'array' => ['nested' => 'value'],
        ];

        $mappingItem = new MappingItem(new \stdClass(), $pivot);

        $this->assertSame('text', $mappingItem->pivotValue('string'));
        $this->assertSame(42, $mappingItem->pivotValue('integer'));
        $this->assertSame(3.14, $mappingItem->pivotValue('float'));
        $this->assertTrue($mappingItem->pivotValue('boolean'));
        $this->assertNull($mappingItem->pivotValue('null'));
        $this->assertSame(['nested' => 'value'], $mappingItem->pivotValue('array'));
    }

    public function testEntityIsAccessible(): void
    {
        $entity = new \stdClass();
        $mappingItem = new MappingItem($entity);

        $this->assertSame($entity, $mappingItem->entity);
    }

    public function testPivotIsAccessible(): void
    {
        $pivot = ['key' => 'value'];
        $mappingItem = new MappingItem(new \stdClass(), $pivot);

        $this->assertSame($pivot, $mappingItem->pivot);
    }
}
