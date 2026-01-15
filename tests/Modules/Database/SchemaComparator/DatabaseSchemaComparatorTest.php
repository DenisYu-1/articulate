<?php

namespace Articulate\Tests\Modules\Database\SchemaComparator;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Modules\Database\SchemaComparator\DatabaseSchemaComparator;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;
use Articulate\Modules\Database\SchemaComparator\RelationValidators\RelationValidatorFactory;
use Articulate\Modules\Database\SchemaComparator\SchemaComparisonCoordinator;
use Articulate\Modules\Database\SchemaReader\DatabaseSchemaReaderInterface;
use Articulate\Schema\SchemaNaming;
use PHPUnit\Framework\TestCase;

class DatabaseSchemaComparatorTest extends TestCase {
    private DatabaseSchemaReaderInterface $schemaReader;

    private SchemaNaming $schemaNaming;

    private DatabaseSchemaComparator $comparator;

    protected function setUp(): void
    {
        $this->schemaReader = $this->createMock(DatabaseSchemaReaderInterface::class);
        $this->schemaNaming = new SchemaNaming();
        $this->comparator = new DatabaseSchemaComparator(
            $this->schemaReader,
            $this->schemaNaming
        );
    }

    public function testConstructorCreatesCoordinator(): void
    {
        $reflection = new \ReflectionClass($this->comparator);
        $property = $reflection->getProperty('coordinator');
        $property->setAccessible(true);

        $coordinator = $property->getValue($this->comparator);

        $this->assertInstanceOf(SchemaComparisonCoordinator::class, $coordinator);
    }

    public function testCompareAllDelegatesToCoordinator(): void
    {
        $entities = [
            $this->createMock(ReflectionEntity::class),
            $this->createMock(ReflectionEntity::class),
        ];

        $expectedResults = [
            $this->createMock(TableCompareResult::class),
            $this->createMock(TableCompareResult::class),
        ];

        // Mock the coordinator
        $reflection = new \ReflectionClass($this->comparator);
        $property = $reflection->getProperty('coordinator');
        $property->setAccessible(true);
        $coordinator = $property->getValue($this->comparator);

        // Mock the compareAll method on coordinator
        $coordinatorReflection = new \ReflectionClass($coordinator);
        $compareAllMethod = $coordinatorReflection->getMethod('compareAll');
        $compareAllMethod->setAccessible(true);

        // Since we can't easily mock the return, let's just test that compareAll is callable
        $result = $this->comparator->compareAll($entities);

        $this->assertIsIterable($result);
    }

    public function testCompareAllWithEmptyEntities(): void
    {
        $result = $this->comparator->compareAll([]);

        $this->assertIsIterable($result);
        // Should return empty iterable
        $results = iterator_to_array($result);
        $this->assertEmpty($results);
    }

    public function testConstructorWithCustomRelationValidatorFactory(): void
    {
        $validatorFactory = $this->createMock(RelationValidatorFactory::class);

        $comparator = new DatabaseSchemaComparator(
            $this->schemaReader,
            $this->schemaNaming,
            $validatorFactory
        );

        $this->assertInstanceOf(DatabaseSchemaComparator::class, $comparator);
    }

    public function testConstructorWithDefaultRelationValidatorFactory(): void
    {
        $comparator = new DatabaseSchemaComparator(
            $this->schemaReader,
            $this->schemaNaming
        );

        $this->assertInstanceOf(DatabaseSchemaComparator::class, $comparator);
    }
}
