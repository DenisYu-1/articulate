<?php

namespace Articulate\Tests\Modules\Database\SchemaComparator;

use Articulate\Modules\Database\SchemaComparator\Comparators\IndexComparator;
use PHPUnit\Framework\TestCase;

class IndexComparatorTest extends TestCase {
    private IndexComparator $comparator;

    protected function setUp(): void
    {
        $this->comparator = new IndexComparator();
    }

    public function testIndexComparatorCanBeInstantiated(): void
    {
        $this->assertInstanceOf(IndexComparator::class, $this->comparator);
    }
}
