<?php

namespace Articulate\Tests\Modules\Database\SchemaComparator;

use Articulate\Modules\Database\SchemaComparator\Comparators\ColumnComparator;
use Articulate\Modules\Database\SchemaComparator\Comparators\EntityTableComparator;
use Articulate\Modules\Database\SchemaComparator\Comparators\ForeignKeyComparator;
use Articulate\Modules\Database\SchemaComparator\Comparators\IndexComparator;
use Articulate\Modules\Database\SchemaReader\DatabaseSchemaReaderInterface;
use PHPUnit\Framework\TestCase;

class EntityTableComparatorTest extends TestCase {
    private EntityTableComparator $comparator;

    private DatabaseSchemaReaderInterface $schemaReader;

    private ColumnComparator $columnComparator;

    private IndexComparator $indexComparator;

    private ForeignKeyComparator $foreignKeyComparator;

    protected function setUp(): void
    {
        $this->schemaReader = $this->createMock(DatabaseSchemaReaderInterface::class);
        $this->columnComparator = $this->createMock(ColumnComparator::class);
        $this->indexComparator = $this->createMock(IndexComparator::class);
        $this->foreignKeyComparator = $this->createMock(ForeignKeyComparator::class);

        $this->comparator = new EntityTableComparator(
            $this->schemaReader,
            $this->columnComparator,
            $this->indexComparator,
            $this->foreignKeyComparator
        );
    }

    public function testEntityTableComparatorCanBeInstantiated(): void
    {
        $this->assertInstanceOf(EntityTableComparator::class, $this->comparator);
    }

    public function testEntityTableComparatorHasRequiredDependencies(): void
    {
        // Test that constructor accepts all required dependencies
        $comparator = new EntityTableComparator(
            $this->createMock(DatabaseSchemaReaderInterface::class),
            $this->createMock(ColumnComparator::class),
            $this->createMock(IndexComparator::class),
            $this->createMock(ForeignKeyComparator::class)
        );

        $this->assertInstanceOf(EntityTableComparator::class, $comparator);
    }
}
