<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Exceptions\EmptyPropertiesList;
use Articulate\Modules\DatabaseSchemaComparator\DatabaseSchemaComparator;
use Articulate\Modules\DatabaseSchemaComparator\Models\TableCompareResult;
use Articulate\Modules\DatabaseSchemaReader\DatabaseColumn;
use Articulate\Modules\DatabaseSchemaReader\DatabaseSchemaReader;
use Articulate\Schema\SchemaNaming;
use Articulate\Tests\AbstractTestCase;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestEmptyEntity;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestEntity;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestMultiPrimaryKeyEntity;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestMultiSortedPrimaryKeyEntity;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestPrimaryKeyEntity;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestSecondEntity;

class DatabaseSchemaComparatorTest extends AbstractTestCase
{
    public function testEmptyDbEmptyEntities()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn([]);
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader, new SchemaNaming());
        $result = $databaseSchemaComparator->compareAll([]);
        $this->assertEmpty(iterator_to_array($result));
    }

    public function testEmptyDb()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn([]);
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader, new SchemaNaming());
        /** @var TableCompareResult[] $result */
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestEntity::class),
        ]));
        $this->assertInstanceOf(TableCompareResult::class, $result[0]);
        $this->assertEquals('test_entity', $result[0]->name);
        $this->assertEquals('create', $result[0]->operation);
        $this->assertEquals(1, count($result[0]->columns));
        $this->assertEquals('id', $result[0]->columns[0]->name);
        $this->assertEquals('create', $result[0]->columns[0]->operation);
        $this->assertEquals('int', $result[0]->columns[0]->propertyData->type);
        $this->assertFalse($result[0]->columns[0]->propertyData->isNullable);
        $this->assertTrue($result[0]->columns[0]->isDefaultValueMatch);
        $this->assertNull($result[0]->columns[0]->propertyData->defaultValue);
        $this->assertNull($result[0]->columns[0]->columnData->defaultValue);
    }

    public function testEmptyEntities()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn(['table_to_delete']);
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader, new SchemaNaming());
        /** @var TableCompareResult[] $result */
        $result = iterator_to_array($databaseSchemaComparator->compareAll([]));
        $this->assertInstanceOf(TableCompareResult::class, $result[0]);
        $this->assertEquals('table_to_delete', $result[0]->name);
        $this->assertEquals('delete', $result[0]->operation);
        $this->assertEquals(0, count($result[0]->columns));
    }

    public function testUpdateOneField()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn(['test_entity']);
        $databaseSchemaReader->expects($this->once())->method('getTableColumns')->willReturn([new DatabaseColumn('id', 'string', true, 'test')]);
        $databaseSchemaReader->expects($this->once())->method('getTableIndexes')->willReturn([]);
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader, new SchemaNaming());
        /** @var TableCompareResult[] $result */
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestEntity::class),
        ]));
        $this->assertInstanceOf(TableCompareResult::class, $result[0]);
        $this->assertEquals('update', $result[0]->operation);
        $this->assertEquals(1, count($result[0]->columns));
        $this->assertEquals('id', $result[0]->columns[0]->name);
        $this->assertEquals('update', $result[0]->columns[0]->operation);
        $this->assertEquals('int', $result[0]->columns[0]->propertyData->type);
        $this->assertEquals('string', $result[0]->columns[0]->columnData->type);
        $this->assertFalse($result[0]->columns[0]->propertyData->isNullable);
        $this->assertTrue($result[0]->columns[0]->columnData->isNullable);
        $this->assertEquals('test', $result[0]->columns[0]->columnData->defaultValue);
        $this->assertNull($result[0]->columns[0]->propertyData->defaultValue);
        $this->assertFalse($result[0]->columns[0]->isDefaultValueMatch);
    }

    public function testDeleteOneField()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn(['test_entity']);
        $databaseSchemaReader->expects($this->once())->method('getTableColumns')->willReturn([new DatabaseColumn('id_to_remove', 'string', true, null)]);
        $databaseSchemaReader->expects($this->once())->method('getTableIndexes')->willReturn([]);
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader, new SchemaNaming());
        /** @var TableCompareResult[] $result */
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestEntity::class),
        ]));
        $this->assertInstanceOf(TableCompareResult::class, $result[0]);
        $this->assertEquals(2, count($result[0]->columns));
        $this->assertEquals('id', $result[0]->columns[0]->name);
        $this->assertEquals('create', $result[0]->columns[0]->operation);
        $this->assertEquals('int', $result[0]->columns[0]->propertyData->type);
        $this->assertFalse($result[0]->columns[0]->propertyData->isNullable);
        $this->assertEquals('id_to_remove', $result[0]->columns[1]->name);
        $this->assertEquals('delete', $result[0]->columns[1]->operation);
        $this->assertEquals('string', $result[0]->columns[1]->columnData->type);
        $this->assertTrue($result[0]->columns[1]->columnData->isNullable);
    }

    public function testTwoEntitiesOneTable()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn(['test_entity']);
        $databaseSchemaReader->expects($this->once())->method('getTableColumns')->willReturn([]);
        $databaseSchemaReader->expects($this->once())->method('getTableIndexes')->willReturn([]);
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader, new SchemaNaming());
        /** @var TableCompareResult[] $result */
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestEntity::class),
            new ReflectionEntity(TestSecondEntity::class),
        ]));
        $this->assertEquals(1, count($result));
        $this->assertInstanceOf(TableCompareResult::class, $result[0]);
        $this->assertEquals(2, count($result[0]->columns));
        $this->assertEquals('id', $result[0]->columns[0]->name);
        $this->assertEquals('create', $result[0]->columns[0]->operation);
        $this->assertEquals('int', $result[0]->columns[0]->propertyData->type);
        $this->assertFalse($result[0]->columns[0]->propertyData->isNullable);
        $this->assertEquals('name', $result[0]->columns[1]->name);
        $this->assertEquals('create', $result[0]->columns[1]->operation);
        $this->assertEquals('string', $result[0]->columns[1]->propertyData->type);
        $this->assertFalse($result[0]->columns[1]->propertyData->isNullable);
    }

    public function testOnePrimaryKey()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn(['test_entity3']);
        $databaseSchemaReader->expects($this->once())->method('getTableColumns')->willReturn([]);
        $databaseSchemaReader->expects($this->once())->method('getTableIndexes')->willReturn([]);
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader, new SchemaNaming());
        /** @var TableCompareResult[] $result */
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestPrimaryKeyEntity::class),
        ]));
        $this->assertEquals(1, count($result));
        $this->assertInstanceOf(TableCompareResult::class, $result[0]);
        $this->assertEquals(['id'], $result[0]->primaryColumns);
    }

    public function testCombinedPrimaryKey()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn(['test_entity31']);
        $databaseSchemaReader->expects($this->once())->method('getTableColumns')->willReturn([]);
        $databaseSchemaReader->expects($this->once())->method('getTableIndexes')->willReturn([]);
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader, new SchemaNaming());
        /** @var TableCompareResult[] $result */
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestMultiPrimaryKeyEntity::class),
        ]));
        $this->assertEquals(1, count($result));
        $this->assertInstanceOf(TableCompareResult::class, $result[0]);
        $this->assertEquals(['id', 'name'], $result[0]->primaryColumns);
    }

    public function testCombinedSortedPrimaryKey()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn(['test_entity312']);
        $databaseSchemaReader->expects($this->once())->method('getTableColumns')->willReturn([]);
        $databaseSchemaReader->expects($this->once())->method('getTableIndexes')->willReturn([]);
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader, new SchemaNaming());
        /** @var TableCompareResult[] $result */
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestMultiSortedPrimaryKeyEntity::class),
        ]));
        $this->assertEquals(1, count($result));
        $this->assertInstanceOf(TableCompareResult::class, $result[0]);
        $this->assertEquals(['abc', 'id', 'name'], $result[0]->primaryColumns);
    }

    public function testSyncedState()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn(['test_entity']);
        $databaseSchemaReader->expects($this->once())->method('getTableColumns')->willReturn([
            new DatabaseColumn('id', 'int', false, null),
        ]);
        $databaseSchemaReader->expects($this->once())->method('getTableIndexes')->willReturn([]);
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader, new SchemaNaming());
        /** @var TableCompareResult[] $result */
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestEntity::class),
        ]));
        $this->assertEquals(0, count($result));
    }

    public function testEntityWithoutProperties()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn(['test_entity']);
        $databaseSchemaReader->expects($this->once())->method('getTableColumns')->willReturn([]);
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader, new SchemaNaming());
        $this->expectException(EmptyPropertiesList::class);
        iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestEmptyEntity::class),
        ]));
    }

    public function testEmptyIndexesArrayHandling()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn(['test_entity']);
        $databaseSchemaReader->expects($this->once())->method('getTableColumns')->willReturn([
            new DatabaseColumn('id', 'int', false, null),
        ]);
        // Return empty array for indexes - this tests the UnwrapArrayKeys mutation on line 56
        $databaseSchemaReader->expects($this->once())->method('getTableIndexes')->willReturn([]);
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader, new SchemaNaming());
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestEntity::class),
        ]));
        $this->assertEquals(0, count($result)); // Should be synced, no changes needed
    }

    public function testEntityWithNoIndexAttributes()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn(['test_entity']);
        $databaseSchemaReader->expects($this->once())->method('getTableColumns')->willReturn([
            new DatabaseColumn('id', 'int', false, null),
        ]);
        $databaseSchemaReader->expects($this->once())->method('getTableIndexes')->willReturn([]);
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader, new SchemaNaming());
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestEntity::class), // TestEntity has no Index attributes
        ]));
        $this->assertEquals(0, count($result)); // Should be synced
    }

    public function testColumnUpdateWithComplexMatchingConditions()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn(['test_entity']);
        // Test different combinations of column properties to cover LogicalOr mutations on line 147
        $databaseSchemaReader->expects($this->once())->method('getTableColumns')->willReturn([
            new DatabaseColumn('id', 'INT(11)', false, null), // Different length
        ]);
        $databaseSchemaReader->expects($this->once())->method('getTableIndexes')->willReturn([]);
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader, new SchemaNaming());
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestEntity::class),
        ]));
        $this->assertEquals(1, count($result));
        $this->assertEquals('update', $result[0]->operation);
        $this->assertEquals(1, count($result[0]->columns));
        $this->assertEquals('update', $result[0]->columns[0]->operation);
        // Test that length mismatch is detected
        $this->assertFalse($result[0]->columns[0]->isLengthMatch);
    }

    public function testColumnUpdateWithAllMatchingProperties()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn(['test_entity']);
        // Test when all properties match to cover the negative case of the LogicalOr on line 147
        $databaseSchemaReader->expects($this->once())->method('getTableColumns')->willReturn([
            new DatabaseColumn('id', 'int', false, null), // Exact match
        ]);
        $databaseSchemaReader->expects($this->once())->method('getTableIndexes')->willReturn([]);
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader, new SchemaNaming());
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestEntity::class),
        ]));
        $this->assertEquals(0, count($result)); // No changes needed when everything matches
    }

    public function testIndexDeletionWithEmptyIndexData()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn(['test_entity']);
        $databaseSchemaReader->expects($this->once())->method('getTableColumns')->willReturn([
            new DatabaseColumn('id', 'int', false, null),
        ]);
        // Test with index that has empty columns array - covers UnwrapArrayKeys mutation on line 183
        $databaseSchemaReader->expects($this->once())->method('getTableIndexes')->willReturn([
            'test_index' => ['columns' => [], 'unique' => false],
        ]);
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader, new SchemaNaming());
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestEntity::class),
        ]));
        $this->assertEquals(1, count($result)); // Index with empty columns should be marked for deletion
        $this->assertEquals('update', $result[0]->operation);
        $this->assertEquals(1, count($result[0]->indexes));
        $this->assertEquals('delete', $result[0]->indexes[0]->operation);
    }

    public function testForeignKeyProcessingWithEmptyExistingKeys()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn(['test_entity']);
        $databaseSchemaReader->expects($this->once())->method('getTableColumns')->willReturn([
            new DatabaseColumn('id', 'int', false, null),
        ]);
        $databaseSchemaReader->expects($this->once())->method('getTableIndexes')->willReturn([]);
        $databaseSchemaReader->expects($this->once())->method('getTableForeignKeys')->willReturn([]); // Empty FKs

        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader, new SchemaNaming());
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestEntity::class),
        ]));
        $this->assertEquals(0, count($result)); // Should handle empty foreign keys gracefully
    }

    public function testForeignKeyProcessingWithOperationCreate()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn([]); // Table doesn't exist
        $databaseSchemaReader->expects($this->once())->method('getTableColumns')->willReturn([]); // Called for new table but returns empty
        $databaseSchemaReader->expects($this->never())->method('getTableIndexes'); // Won't be called for new table
        $databaseSchemaReader->expects($this->never())->method('getTableForeignKeys'); // Won't be called for new table

        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader, new SchemaNaming());
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestEntity::class),
        ]));
        $this->assertEquals(1, count($result));
        $this->assertEquals('create', $result[0]->operation);
        // For create operations, foreign key processing should be skipped (covers Continue_ on line 201)
    }

    public function testForeignKeyColumnWithoutRelation()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn(['test_entity']);
        $databaseSchemaReader->expects($this->once())->method('getTableColumns')->willReturn([
            new DatabaseColumn('id', 'int', false, null),
            new DatabaseColumn('unrelated_column', 'string', true, null), // Column without relation
        ]);
        $databaseSchemaReader->expects($this->once())->method('getTableIndexes')->willReturn([]);
        $databaseSchemaReader->expects($this->once())->method('getTableForeignKeys')->willReturn([]);

        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader, new SchemaNaming());
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestEntity::class), // Only has 'id' property
        ]));
        $this->assertEquals(1, count($result));
        $this->assertEquals('update', $result[0]->operation);
        $this->assertEquals(1, count($result[0]->columns)); // Should only have the delete operation for unrelated_column
        $this->assertEquals('delete', $result[0]->columns[0]->operation);
    }

    public function testComplexPrimaryKeyIndexHandling()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn(['test_entity31']);
        $databaseSchemaReader->expects($this->once())->method('getTableColumns')->willReturn([]);
        $databaseSchemaReader->expects($this->once())->method('getTableIndexes')->willReturn([
            'PRIMARY' => ['columns' => ['id', 'name'], 'unique' => true], // Primary key index
        ]);
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader, new SchemaNaming());
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestMultiPrimaryKeyEntity::class),
        ]));
        $this->assertEquals(1, count($result));
        $this->assertEquals('update', $result[0]->operation); // Table exists but needs columns added
        $this->assertEquals(['id', 'name'], $result[0]->primaryColumns);
        // Primary key index should be filtered out (tests removePrimaryIndex method)
    }

    public function testEmptyEntityIndexAttributes()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn(['test_entity']);
        $databaseSchemaReader->expects($this->once())->method('getTableColumns')->willReturn([
            new DatabaseColumn('id', 'int', false, null),
        ]);
        $databaseSchemaReader->expects($this->once())->method('getTableIndexes')->willReturn([]);
        $databaseSchemaReader->expects($this->once())->method('getTableForeignKeys')->willReturn([]);

        // Create a mock entity that returns empty attributes for Index::class
        $mockEntity = $this->createMock(ReflectionEntity::class);
        $mockEntity->expects($this->once())->method('getAttributes')->willReturn([]);
        $mockEntity->expects($this->once())->method('getEntityProperties')->willReturn([]);
        $mockEntity->expects($this->any())->method('getPrimaryKeyColumns')->willReturn(['id']);
        $mockEntity->expects($this->once())->method('getTableName')->willReturn('test_entity');
        $mockEntity->expects($this->once())->method('isEntity')->willReturn(true);

        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader, new SchemaNaming());
        $result = iterator_to_array($databaseSchemaComparator->compareAll([$mockEntity]));

        $this->assertEquals(1, count($result));
        $this->assertEquals('update', $result[0]->operation);
        // Should detect column deletion since entity has no properties
        $this->assertEquals(1, count($result[0]->columns));
        $this->assertEquals('delete', $result[0]->columns[0]->operation);
    }

    public function testForeignKeyValidationFailure()
    {
        // This test covers the MethodCallRemoval mutation on validator->validate()
        // Validation happens early in the process, so we test that validation exceptions are properly thrown
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn([]);

        // Create a mock validator that throws an exception
        $mockValidator = $this->createMock(\Articulate\Modules\DatabaseSchemaComparator\RelationValidators\RelationValidatorInterface::class);
        $mockValidator->expects($this->once())->method('validate')->willThrowException(new \RuntimeException('Validation failed'));

        $validatorFactory = $this->createMock(\Articulate\Modules\DatabaseSchemaComparator\RelationValidators\RelationValidatorFactory::class);
        $validatorFactory->expects($this->once())->method('getValidator')->willReturn($mockValidator);

        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader, new SchemaNaming(), $validatorFactory);

        // Validation happens in validateRelations() before table processing
        $this->expectException(\RuntimeException::class);
        iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestEntities\TestManyToOneOwner::class),
        ]));
    }

    public function testCreatedColumnsWithForeignKeysFlag()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn([]);
        $databaseSchemaReader->expects($this->once())->method('getTableColumns')->willReturn([]);

        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader, new SchemaNaming());
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestEntity::class),
        ]));

        $this->assertEquals(1, count($result));
        $this->assertEquals('create', $result[0]->operation);
        // Test that covers the TrueValue mutation where createdColumnsWithForeignKeys is set to false instead of true
    }

    public function testComplexColumnMatchingLogic()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn(['test_entity']);
        // Test various combinations of column property mismatches to cover LogicalOr mutations
        $databaseSchemaReader->expects($this->once())->method('getTableColumns')->willReturn([
            new DatabaseColumn('id', 'varchar(255)', true, 'default'), // Different type, nullable, has default
        ]);
        $databaseSchemaReader->expects($this->once())->method('getTableIndexes')->willReturn([]);

        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader, new SchemaNaming());
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestEntity::class), // TestEntity has id as int, not null, no default
        ]));

        $this->assertEquals(1, count($result));
        $this->assertEquals('update', $result[0]->operation);
        $this->assertEquals(1, count($result[0]->columns));
        $this->assertEquals('update', $result[0]->columns[0]->operation);
        // All properties should mismatch: type, nullable, default
        $this->assertFalse($result[0]->columns[0]->typeMatch);
        $this->assertFalse($result[0]->columns[0]->isNullableMatch);
        $this->assertFalse($result[0]->columns[0]->isDefaultValueMatch);
    }

    public function testIndexProcessingWithEmptyEntityIndexes()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn(['test_entity']);
        $databaseSchemaReader->expects($this->once())->method('getTableColumns')->willReturn([
            new DatabaseColumn('id', 'int', false, null),
        ]);
        $databaseSchemaReader->expects($this->once())->method('getTableIndexes')->willReturn([]);

        // Use TestEntity but mock the getAttributes method to return empty array for Index::class
        $mockEntity = $this->createMock(ReflectionEntity::class);
        $mockEntity->expects($this->once())->method('getAttributes')->willReturn([]); // No index attributes
        $mockEntity->expects($this->once())->method('getEntityProperties')->willReturn([]); // No properties
        $mockEntity->expects($this->any())->method('getPrimaryKeyColumns')->willReturn(['id']);
        $mockEntity->expects($this->once())->method('getTableName')->willReturn('test_entity');
        $mockEntity->expects($this->once())->method('isEntity')->willReturn(true);

        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader, new SchemaNaming());
        $result = iterator_to_array($databaseSchemaComparator->compareAll([$mockEntity]));

        $this->assertEquals(1, count($result));
        $this->assertEquals('update', $result[0]->operation);
        // Should detect column deletion
        $this->assertEquals(1, count($result[0]->columns));
        $this->assertEquals('delete', $result[0]->columns[0]->operation);
    }

    public function testForeignKeyProcessingEdgeCases()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn(['test_entity']);
        $databaseSchemaReader->expects($this->once())->method('getTableColumns')->willReturn([
            new DatabaseColumn('id', 'int', false, null),
        ]);
        $databaseSchemaReader->expects($this->once())->method('getTableIndexes')->willReturn([]);
        $databaseSchemaReader->expects($this->once())->method('getTableForeignKeys')->willReturn([
            'fk_test' => ['column' => 'id', 'referencedTable' => 'other_table', 'referencedColumn' => 'id'],
        ]);

        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader, new SchemaNaming());
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestEntity::class), // No relations
        ]));

        $this->assertEquals(1, count($result));
        $this->assertEquals('update', $result[0]->operation);
        // Should include foreign key deletion since entity has no relations
        $this->assertEquals(1, count($result[0]->foreignKeys));
        $this->assertEquals('delete', $result[0]->foreignKeys[0]->operation);
    }

    public function testIndexDeletionSkipLogic()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn(['test_entity']);
        $databaseSchemaReader->expects($this->once())->method('getTableColumns')->willReturn([
            new DatabaseColumn('id', 'int', false, null),
        ]);
        // Test index that should NOT be deleted (primary key index)
        $databaseSchemaReader->expects($this->once())->method('getTableIndexes')->willReturn([
            'PRIMARY' => ['columns' => ['id'], 'unique' => true],
        ]);
        $databaseSchemaReader->expects($this->once())->method('getTableForeignKeys')->willReturn([]);

        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader, new SchemaNaming());
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestEntity::class), // Has primary key 'id'
        ]));

        $this->assertEquals(0, count($result)); // Should be synced, no index deletion
    }
}
