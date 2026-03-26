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
        $this->schemaReader = $this->createStub(DatabaseSchemaReaderInterface::class);
        $this->columnComparator = $this->createStub(ColumnComparator::class);
        $this->indexComparator = $this->createStub(IndexComparator::class);
        $this->foreignKeyComparator = $this->createStub(ForeignKeyComparator::class);

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
            $this->createStub(DatabaseSchemaReaderInterface::class),
            $this->createStub(ColumnComparator::class),
            $this->createStub(IndexComparator::class),
            $this->createStub(ForeignKeyComparator::class)
        );

        $this->assertInstanceOf(EntityTableComparator::class, $comparator);
    }
}
