<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Modules\DatabaseSchemaComparator\DatabaseSchemaComparator;
use Articulate\Modules\DatabaseSchemaComparator\Models\TableCompareResult;
use Articulate\Modules\DatabaseSchemaReader\DatabaseSchemaReader;
use Articulate\Modules\DatabaseSchemaReader\DatabaseColumn;
use Articulate\Tests\AbstractTestCase;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestRelatedEntity;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestRelatedMainEntity;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestRelatedMainEntityNoFk;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestRelatedEntityMisconfigured;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestRelatedEntityInverseMain;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestRelatedEntityInverseForeignKey;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestRelatedMainEntityMisconfigured;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestRelatedMainEntityInverseMain;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestRelatedMainEntityInverseForeignKey;

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
        $databaseSchemaReader->expects($this->any())->method('getTableColumns')->willReturn([]);
        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader);
        /** @var TableCompareResult[] $result */
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestRelatedMainEntity::class)
        ]));
        $this->assertInstanceOf(TableCompareResult::class, $result[0]);
        $this->assertEquals('create', $result[0]->operation);
        $this->assertEquals(2, count($result[0]->columns));
        $this->assertEquals('id', $result[0]->columns[0]->name);
        $this->assertEquals('create', $result[0]->columns[0]->operation);
        $this->assertEquals('name_id', $result[0]->columns[1]->name);
        $this->assertEquals('create', $result[0]->columns[1]->operation);
        $this->assertEquals('int', $result[0]->columns[1]->propertyData->type);
        $this->assertFalse($result[0]->columns[1]->propertyData->isNullable);
        $this->assertNull($result[0]->columns[1]->propertyData->defaultValue);
        $this->assertTrue($result[0]->columns[1]->isDefaultValueMatch);
        $this->assertCount(1, $result[0]->foreignKeys);
    }

    public function testCreateDependantOneToOneSide()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn([]);
        $databaseSchemaReader->expects($this->any())->method('getTableColumns')->willReturn([]);
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
        $databaseSchemaReader->expects($this->any())->method('getTableColumns')->willReturn([]);
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

    public function testForeignKeyDroppedWhenDisabled()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn(['test_related_main_entity_no_fk']);
        $databaseSchemaReader->expects($this->once())->method('getTableColumns')->willReturn([
            new DatabaseColumn('name_id', 'int', false, null),
        ]);
        $databaseSchemaReader->expects($this->once())->method('getTableIndexes')->willReturn([]);
        $databaseSchemaReader->expects($this->once())->method('getTableForeignKeys')->willReturn([
            'fk_test_related_main_entity_no_fk_test_related_entity_name_id' => [
                'column' => 'name_id',
                'referencedTable' => 'test_related_entity',
                'referencedColumn' => 'id',
            ],
        ]);

        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader);
        /** @var TableCompareResult[] $result */
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestRelatedMainEntityNoFk::class)
        ]));

        $this->assertCount(1, $result);
        $this->assertEquals('update', $result[0]->operation);
        $this->assertCount(0, $result[0]->columns);
        $this->assertCount(1, $result[0]->foreignKeys);
        $this->assertEquals('delete', $result[0]->foreignKeys[0]->operation);
        $this->assertEquals('name_id', $result[0]->foreignKeys[0]->column);
    }

    public function testOneToOneInverseMisconfiguredThrows()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn([]);
        $databaseSchemaReader->expects($this->any())->method('getTableColumns')->willReturn([]);

        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('One-to-one inverse side misconfigured');

        iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestRelatedMainEntityMisconfigured::class),
            new ReflectionEntity(TestRelatedEntityMisconfigured::class),
        ]));
    }

    public function testOneToOneInverseMarkedAsMainThrows()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn([]);
        $databaseSchemaReader->expects($this->any())->method('getTableColumns')->willReturn([]);

        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('One-to-one inverse side misconfigured: inverse side marked as main');

        iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestRelatedMainEntityInverseMain::class),
            new ReflectionEntity(TestRelatedEntityInverseMain::class),
        ]));
    }

    public function testOneToOneInverseRequestsForeignKeyThrows()
    {
        $databaseSchemaReader = $this->createMock(DatabaseSchemaReader::class);
        $databaseSchemaReader->expects($this->once())->method('getTables')->willReturn([]);
        $databaseSchemaReader->expects($this->any())->method('getTableColumns')->willReturn([]);

        $databaseSchemaComparator = new DatabaseSchemaComparator($databaseSchemaReader);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('One-to-one inverse side misconfigured: inverse side requests foreign key');

        iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestRelatedMainEntityInverseForeignKey::class),
            new ReflectionEntity(TestRelatedEntityInverseForeignKey::class),
        ]));
    }
}
