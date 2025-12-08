<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Modules\DatabaseSchemaComparator\DatabaseSchemaComparator;
use Articulate\Modules\DatabaseSchemaComparator\Models\TableCompareResult;
use Articulate\Modules\DatabaseSchemaReader\DatabaseSchemaReader;
use Articulate\Tests\AbstractTestCase;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestRelatedEntity;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestRelatedMainEntity;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestRelatedMainEntityNoFk;

class DatabaseSchemaComparatorRelationsTest extends AbstractTestCase
{
    /**
     * 1. создание релейшена
     * 2. апдейт релейшена
     * 3. создание релейшена без фк
     * 4. апдейт релейшена без фк
     */
    public function testCreateMainOneToOneSide()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn([]);
        $databaseSchemaReader->expects($this->once())->method('getTableColumns')->willReturn([]);
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader);
        /** @var TableCompareResult[] $result */
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestRelatedMainEntity::class)
        ]));
        $this->assertInstanceOf(TableCompareResult::class, $result[0]);
        $this->assertEquals('create', $result[0]->operation);
        $this->assertEquals(1, count($result[0]->columns));
        $this->assertEquals('name_id', $result[0]->columns[0]->name);
        $this->assertEquals('create', $result[0]->columns[0]->operation);
        $this->assertEquals('int', $result[0]->columns[0]->propertyData->type);
        $this->assertFalse($result[0]->columns[0]->propertyData->isNullable);
        $this->assertNull($result[0]->columns[0]->propertyData->defaultValue);
        $this->assertTrue($result[0]->columns[0]->isDefaultValueMatch);
        $this->assertCount(1, $result[0]->foreignKeys);
    }

    public function testCreateDependantOneToOneSide()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn([]);
        $databaseSchemaReader->expects($this->once())->method('getTableColumns')->willReturn([]);
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader);
        /** @var TableCompareResult[] $result */
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestRelatedEntity::class)
        ]));
        $this->assertInstanceOf(TableCompareResult::class, $result[0]);
        $this->assertEquals('create', $result[0]->operation);
        $this->assertEquals(1, count($result[0]->columns));
        $this->assertEquals('id', $result[0]->columns[0]->name);
    }

    public function testOneToOneMainSideWithoutForeignKey()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn([]);
        $databaseSchemaReader->expects($this->once())->method('getTableColumns')->willReturn([]);
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader);
        /** @var TableCompareResult[] $result */
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestRelatedMainEntityNoFk::class)
        ]));
        $this->assertCount(1, $result);
        $this->assertEquals('create', $result[0]->operation);
        $this->assertCount(1, $result[0]->columns);
        $this->assertCount(0, $result[0]->foreignKeys);
    }
}
