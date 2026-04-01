<?php

namespace Articulate\Tests\Modules\Database\SchemaComparator\Comparators;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Exceptions\DatabaseSchemaException;
use Articulate\Modules\Database\SchemaComparator\Comparators\ColumnComparator;
use Articulate\Modules\Database\SchemaComparator\Comparators\EntityTableComparator;
use Articulate\Modules\Database\SchemaComparator\Comparators\ForeignKeyComparator;
use Articulate\Modules\Database\SchemaComparator\Comparators\IndexComparator;
use Articulate\Modules\Database\SchemaComparator\Models\CompareResult;
use Articulate\Modules\Database\SchemaComparator\RelationValidators\RelationValidatorFactory;
use Articulate\Modules\Database\SchemaReader\DatabaseColumn;
use Articulate\Modules\Database\SchemaReader\DatabaseSchemaReaderInterface;
use Articulate\Schema\SchemaNaming;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestEntity;
use PHPUnit\Framework\TestCase;

class EntityTableComparatorTest extends TestCase
{
    private function createComparator(DatabaseSchemaReaderInterface $schemaReader): EntityTableComparator
    {
        return new EntityTableComparator(
            $schemaReader,
            new ColumnComparator(),
            new IndexComparator(),
            new ForeignKeyComparator(new SchemaNaming(), new RelationValidatorFactory()),
        );
    }

    public function testCreateTableScenario(): void
    {
        $schemaReader = $this->createStub(DatabaseSchemaReaderInterface::class);
        $schemaReader->method('getTableColumns')
            ->willThrowException(new DatabaseSchemaException('Table does not exist'));

        $comparator = $this->createComparator($schemaReader);
        $entity = new ReflectionEntity(TestEntity::class);

        $result = $comparator->compareEntityTable(
            entityGroup: [$entity],
            existingTables: [],
            tableName: 'test_entity',
        );

        $this->assertNotNull($result);
        $this->assertSame('test_entity', $result->name);
        $this->assertSame(CompareResult::OPERATION_CREATE, $result->operation);
        $this->assertNotEmpty($result->columns);
    }

    public function testUpdateTableScenario(): void
    {
        $schemaReader = $this->createStub(DatabaseSchemaReaderInterface::class);
        $schemaReader->method('getTableColumns')
            ->willReturn([new DatabaseColumn(name: 'id', type: 'string', isNullable: true, defaultValue: 'test')]);
        $schemaReader->method('getTableIndexes')->willReturn([]);
        $schemaReader->method('getTableForeignKeys')->willReturn([]);

        $comparator = $this->createComparator($schemaReader);
        $entity = new ReflectionEntity(TestEntity::class);

        $result = $comparator->compareEntityTable(
            entityGroup: [$entity],
            existingTables: ['test_entity'],
            tableName: 'test_entity',
        );

        $this->assertNotNull($result);
        $this->assertSame('test_entity', $result->name);
        $this->assertSame(CompareResult::OPERATION_UPDATE, $result->operation);
        $this->assertNotEmpty($result->columns);
    }

    public function testNoChangeScenario(): void
    {
        $schemaReader = $this->createStub(DatabaseSchemaReaderInterface::class);
        $schemaReader->method('getTableColumns')
            ->willReturn([new DatabaseColumn(name: 'id', type: 'int', isNullable: false, defaultValue: null)]);
        $schemaReader->method('getTableIndexes')->willReturn([]);
        $schemaReader->method('getTableForeignKeys')->willReturn([]);

        $comparator = $this->createComparator($schemaReader);
        $entity = new ReflectionEntity(TestEntity::class);

        $result = $comparator->compareEntityTable(
            entityGroup: [$entity],
            existingTables: ['test_entity'],
            tableName: 'test_entity',
        );

        $this->assertNull($result);
    }
}
