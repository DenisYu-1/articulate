<?php

namespace Norm\Tests\Modules\DatabaseSchemaComparator;

use Norm\Attributes\Reflection\ReflectionEntity;
use Norm\Exceptions\EmptyPropertiesList;
use Norm\Modules\DatabaseSchemaComparator\DatabaseSchemaComparator;
use Norm\Modules\DatabaseSchemaComparator\Models\TableCompareResult;
use Norm\Modules\DatabaseSchemaReader\DatabaseColumn;
use Norm\Modules\DatabaseSchemaReader\DatabaseSchemaReader;
use Norm\Tests\AbstractTestCase;
use Norm\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestEmptyEntity;
use Norm\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestEntity;
use Norm\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestMultiPrimaryKeyEntity;
use Norm\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestMultiSortedPrimaryKeyEntity;
use Norm\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestPrimaryKeyEntity;
use Norm\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestSecondEntity;

class DatabaseSchemaComparatorTest extends AbstractTestCase
{
    public function testEmptyDbEmptyEntities()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn([]);
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader);
        $result = $databaseSchemaComparator->compareAll([]);
        $this->assertEmpty(iterator_to_array($result));
    }

    public function testEmptyDb()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn([]);
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader);
        /** @var TableCompareResult[] $result */
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestEntity::class)
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
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader);
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
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader);
        /** @var TableCompareResult[] $result */
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestEntity::class)
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
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader);
        /** @var TableCompareResult[] $result */
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestEntity::class)
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
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader);
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
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader);
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
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader);
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
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader);
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
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader);
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
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader);
        $this->expectException(EmptyPropertiesList::class);
        iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestEmptyEntity::class),
        ]));
    }
}
