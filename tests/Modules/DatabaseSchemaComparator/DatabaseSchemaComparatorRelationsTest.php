<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Modules\Database\SchemaComparator\DatabaseSchemaComparator;
use Articulate\Modules\Database\SchemaComparator\Models\CompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;
use Articulate\Modules\Database\SchemaReader\DatabaseColumn;
use Articulate\Modules\Database\SchemaReader\DatabaseSchemaReader;
use Articulate\Schema\SchemaNaming;
use Articulate\Tests\AbstractTestCase;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestManyToOneOwner;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestManyToOneOwnerCustomColumn;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestManyToOneOwnerInverseIsOwning;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestManyToOneOwnerMappedByMismatch;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestManyToOneOwnerMissingInverse;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestManyToOneOwnerNoFk;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestManyToOneTarget;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestManyToOneTargetInverseIsOwning;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestManyToOneTargetMappedByMismatch;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestManyToOneTargetMissingInverse;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestMorphToEntity;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestOneToManyInverseMissingOwner;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestOneToManyWrongOwner;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestOneToManyWrongOwnerType;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestRelatedEntity;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestRelatedEntityInverseForeignKey;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestRelatedEntityInverseMain;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestRelatedEntityMisconfigured;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestRelatedMainEntity;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestRelatedMainEntityCustomColumn;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestRelatedMainEntityInverseForeignKey;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestRelatedMainEntityInverseMain;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestRelatedMainEntityMisconfigured;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestRelatedMainEntityNoFk;
use RuntimeException;

class DatabaseSchemaComparatorRelationsTest extends AbstractTestCase
{
    /**
     * 1. создание релейшена
     * 2. апдейт релейшена
     * 3. создание релейшена без фк
     * 4. апдейт релейшена без фк.
     */
    public function testCreateMainOneToOneSide()
    {
        $databaseSchemaComparator = $this->comparator(
            tables: [],
            columns: fn () => [],
        );
        /** @var TableCompareResult[] $result */
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestRelatedMainEntity::class),
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
        $databaseSchemaComparator = $this->comparator(
            tables: [],
            columns: fn () => [],
        );
        /** @var TableCompareResult[] $result */
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestRelatedEntity::class),
        ]));
        $this->assertInstanceOf(TableCompareResult::class, $result[0]);
        $this->assertEquals('create', $result[0]->operation);
        $this->assertEquals(1, count($result[0]->columns));
        $this->assertEquals('id', $result[0]->columns[0]->name);
    }

    public function testOneToOneMainSideWithoutForeignKey()
    {
        $databaseSchemaComparator = $this->comparator(
            tables: [],
            columns: fn () => [],
        );
        /** @var TableCompareResult[] $result */
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestRelatedMainEntityNoFk::class),
        ]));
        $this->assertCount(1, $result);
        $this->assertEquals('create', $result[0]->operation);
        $this->assertCount(1, $result[0]->columns);
        $this->assertCount(0, $result[0]->foreignKeys);
    }

    public function testForeignKeyDroppedWhenDisabled()
    {
        $databaseSchemaComparator = $this->comparator(
            tables: ['test_related_main_entity_no_fk'],
            columns: fn () => [new DatabaseColumn('name_id', 'int', false, null)],
            indexes: [],
            foreignKeys: fn () => [
                'fk_test_related_main_entity_no_fk_test_related_entity_name_id' => [
                    'column' => 'name_id',
                    'referencedTable' => 'test_related_entity',
                    'referencedColumn' => 'id',
                ],
            ],
            indexesExpectation: 'any',
            foreignKeysExpectation: 'once',
        );
        /** @var TableCompareResult[] $result */
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestRelatedMainEntityNoFk::class),
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
        $databaseSchemaComparator = $this->comparator(
            tables: [],
            columns: fn () => [],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('One-to-one inverse side misconfigured: ownedBy does not reference owning property');

        iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestRelatedMainEntityMisconfigured::class),
            new ReflectionEntity(TestRelatedEntityMisconfigured::class),
        ]));
    }

    public function testOneToOneInverseMarkedAsMainThrows()
    {
        $databaseSchemaComparator = $this->comparator(
            tables: [],
            columns: fn () => [],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('One-to-one inverse side misconfigured: ownedBy is required on inverse side');

        iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestRelatedMainEntityInverseMain::class),
            new ReflectionEntity(TestRelatedEntityInverseMain::class),
        ]));
    }

    public function testOneToOneInverseRequestsForeignKeyIsIgnored()
    {
        $databaseSchemaComparator = $this->comparator(
            tables: [],
            columns: fn () => [],
        );

        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestRelatedMainEntityInverseForeignKey::class),
            new ReflectionEntity(TestRelatedEntityInverseForeignKey::class),
        ]));

        $this->assertNotEmpty($result);
    }

    public function testOneToOneCustomColumnName()
    {
        $databaseSchemaComparator = $this->comparator(
            tables: [],
            columns: fn () => [],
        );
        /** @var TableCompareResult[] $result */
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestRelatedMainEntityCustomColumn::class),
        ]));

        $this->assertEquals('create', $result[0]->operation);
        $this->assertEquals('custom_fk', $result[0]->columns[1]->name);
        $this->assertEquals('custom_fk', $result[0]->foreignKeys[0]->column);
    }

    public function testCreateManyToOneWithForeignKey()
    {
        $databaseSchemaComparator = $this->comparator(
            tables: [],
            columns: fn () => [],
        );
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestManyToOneOwner::class),
            new ReflectionEntity(TestManyToOneTarget::class),
        ]));

        $ownerTable = array_values(array_filter(
            $result,
            fn (TableCompareResult $table) => $table->name === 'test_many_to_one_owner'
        ))[0];

        $this->assertEquals('create', $ownerTable->operation);
        $this->assertCount(2, $ownerTable->columns);
        $this->assertSame('target_id', $ownerTable->columns[1]->name);
        $this->assertFalse($ownerTable->columns[1]->propertyData->isNullable);
        $this->assertCount(1, $ownerTable->foreignKeys);
        $this->assertSame('target_id', $ownerTable->foreignKeys[0]->column);
    }

    public function testCreateManyToOneWithoutForeignKey()
    {
        $databaseSchemaComparator = $this->comparator(
            tables: [],
            columns: fn () => [],
        );
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestManyToOneOwnerNoFk::class),
            new ReflectionEntity(TestManyToOneTarget::class),
        ]));

        $ownerTable = array_values(array_filter(
            $result,
            fn (TableCompareResult $table) => $table->name === 'test_many_to_one_owner_no_fk'
        ))[0];

        $this->assertEquals('create', $ownerTable->operation);
        $this->assertCount(2, $ownerTable->columns);
        $this->assertTrue($ownerTable->columns[1]->propertyData->isNullable);
        $this->assertCount(0, $ownerTable->foreignKeys);
    }

    public function testManyToOneForeignKeyCreatedWhenMissing()
    {
        $databaseSchemaComparator = $this->comparator(
            tables: ['test_many_to_one_owner', 'test_many_to_one_target'],
            columns: fn (string $table) => $table === 'test_many_to_one_owner'
                ? [
                    new DatabaseColumn('id', 'int', false, null),
                    new DatabaseColumn('target_id', 'int', false, null),
                ]
                : [new DatabaseColumn('id', 'int', false, null)],
            indexes: [],
            foreignKeys: fn () => [],
            indexesExpectation: 'any',
        );
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestManyToOneOwner::class),
            new ReflectionEntity(TestManyToOneTarget::class),
        ]));

        $ownerTable = array_values(array_filter(
            $result,
            fn (TableCompareResult $table) => $table->name === 'test_many_to_one_owner'
        ))[0];

        $this->assertEquals('update', $ownerTable->operation);
        $this->assertCount(0, $ownerTable->columns);
        $this->assertCount(1, $ownerTable->foreignKeys);
        $this->assertEquals('create', $ownerTable->foreignKeys[0]->operation);
    }

    public function testManyToOneForeignKeyDroppedWhenDisabled()
    {
        $databaseSchemaComparator = $this->comparator(
            tables: ['test_many_to_one_owner_no_fk', 'test_many_to_one_target'],
            columns: fn (string $table) => $table === 'test_many_to_one_owner_no_fk'
                ? [
                    new DatabaseColumn('id', 'int', false, null),
                    new DatabaseColumn('nullable_target_id', 'int', true, null),
                ]
                : [new DatabaseColumn('id', 'int', false, null)],
            indexes: [],
            foreignKeys: fn (string $table) => $table === 'test_many_to_one_owner_no_fk'
                ? [
                    'fk_test_many_to_one_owner_no_fk_test_many_to_one_target_nullable_target_id' => [
                        'column' => 'nullable_target_id',
                        'referencedTable' => 'test_many_to_one_target',
                        'referencedColumn' => 'id',
                    ],
                ]
                : [],
            indexesExpectation: 'any',
            foreignKeysExpectation: 'any',
        );
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestManyToOneOwnerNoFk::class),
            new ReflectionEntity(TestManyToOneTarget::class),
        ]));

        $ownerTable = array_values(array_filter(
            $result,
            fn (TableCompareResult $table) => $table->name === 'test_many_to_one_owner_no_fk'
        ))[0];

        $this->assertEquals('update', $ownerTable->operation);
        $this->assertCount(1, $ownerTable->foreignKeys);
        $this->assertEquals('delete', $ownerTable->foreignKeys[0]->operation);
        $this->assertEquals('nullable_target_id', $ownerTable->foreignKeys[0]->column);
    }

    public function testManyToOneColumnRenameAndNullableChange()
    {
        $databaseSchemaComparator = $this->comparator(
            tables: ['test_many_to_one_owner_custom_column', 'test_many_to_one_target'],
            columns: fn (string $table) => $table === 'test_many_to_one_owner_custom_column'
                ? [
                    new DatabaseColumn('id', 'int', false, null),
                    new DatabaseColumn('target_id', 'int', false, null),
                ]
                : [new DatabaseColumn('id', 'int', false, null)],
            indexes: [],
            foreignKeys: fn (string $table) => $table === 'test_many_to_one_owner_custom_column'
                ? [
                    'fk_test_many_to_one_owner_custom_column_test_many_to_one_target_target_id' => [
                        'column' => 'target_id',
                        'referencedTable' => 'test_many_to_one_target',
                        'referencedColumn' => 'id',
                    ],
                ]
                : [],
            indexesExpectation: 'any',
            foreignKeysExpectation: 'any',
        );
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestManyToOneOwnerCustomColumn::class),
            new ReflectionEntity(TestManyToOneTarget::class),
        ]));

        $ownerTable = array_values(array_filter(
            $result,
            fn (TableCompareResult $table) => $table->name === 'test_many_to_one_owner_custom_column'
        ))[0];

        $this->assertEquals('update', $ownerTable->operation);
        $created = array_filter($ownerTable->columns, fn ($c) => $c->name === 'custom_column_id');
        $deleted = array_filter($ownerTable->columns, fn ($c) => $c->name === 'target_id' && $c->operation === CompareResult::OPERATION_DELETE);
        $this->assertNotEmpty($created);
        $this->assertTrue(reset($created)->propertyData->isNullable);
        $this->assertNotEmpty($deleted);
        $this->assertCount(2, $ownerTable->foreignKeys);
    }

    public function testManyToOneInverseMissingThrows()
    {
        $databaseSchemaComparator = $this->comparator(
            tables: [],
            columns: fn () => [],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Many-to-one inverse side misconfigured: property not found');

        iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestManyToOneOwnerMissingInverse::class),
            new ReflectionEntity(TestManyToOneTargetMissingInverse::class),
        ]));
    }

    public function testManyToOneMappedByMismatchThrows()
    {
        $databaseSchemaComparator = $this->comparator(
            tables: [],
            columns: fn () => [],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Many-to-one inverse side misconfigured: ownedBy does not reference owning property');

        iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestManyToOneOwnerMappedByMismatch::class),
            new ReflectionEntity(TestManyToOneTargetMappedByMismatch::class),
        ]));
    }

    public function testManyToOneInverseActingAsOwnerThrows()
    {
        $databaseSchemaComparator = $this->comparator(
            tables: [],
            columns: fn () => [],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Many-to-one inverse side misconfigured: inverse side marked as owner');

        iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestManyToOneOwnerInverseIsOwning::class),
            new ReflectionEntity(TestManyToOneTargetInverseIsOwning::class),
        ]));
    }

    public function testOneToManyMappedByMissingOwnerThrows()
    {
        $databaseSchemaComparator = $this->comparator(
            tables: [],
            columns: fn () => [],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('One-to-many inverse side misconfigured: owning property not found');

        iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestOneToManyInverseMissingOwner::class),
            new ReflectionEntity(TestManyToOneOwner::class),
        ]));
    }

    public function testOneToManyMappedByWrongOwnerTypeThrows()
    {
        $databaseSchemaComparator = $this->comparator(
            tables: [],
            columns: fn () => [],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('One-to-many inverse side misconfigured: owning property not many-to-one');

        iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestOneToManyWrongOwnerType::class),
            new ReflectionEntity(TestOneToManyWrongOwner::class),
        ]));
    }

    private function comparator(
        array $tables,
        callable $columns,
        ?array $indexes = [],
        ?callable $foreignKeys = null,
        string $indexesExpectation = 'any',
        string $foreignKeysExpectation = 'any',
    ): DatabaseSchemaComparator {
        $reader = $this->createMock(DatabaseSchemaReader::class);
        $reader->expects($this->once())->method('getTables')->willReturn($tables);
        $reader->expects($this->any())->method('getTableColumns')->willReturnCallback($columns);

        if ($indexesExpectation === 'once') {
            $reader->expects($this->once())->method('getTableIndexes')->willReturn($indexes ?? []);
        } else {
            $reader->expects($this->any())->method('getTableIndexes')->willReturn($indexes ?? []);
        }

        if ($foreignKeys === null) {
            $reader->expects($this->any())->method('getTableForeignKeys')->willReturn([]);
        } elseif ($foreignKeysExpectation === 'once') {
            $reader->expects($this->once())->method('getTableForeignKeys')->willReturnCallback($foreignKeys);
        } else {
            $reader->expects($this->any())->method('getTableForeignKeys')->willReturnCallback($foreignKeys);
        }

        return new DatabaseSchemaComparator($reader, new SchemaNaming());
    }

    public function testPolymorphicRelationCreatesCorrectColumns()
    {
        $databaseSchemaComparator = $this->comparator(
            tables: [],
            columns: fn () => [],
        );
        /** @var TableCompareResult[] $result */
        $result = iterator_to_array($databaseSchemaComparator->compareAll([
            new ReflectionEntity(TestMorphToEntity::class),
        ]));

        $this->assertCount(1, $result);
        $this->assertEquals('create', $result[0]->operation);
        $this->assertEquals('test_morph_to_entity', $result[0]->name);

        // Should have 4 columns: id, title, pollable_type, pollable_id
        $this->assertCount(4, $result[0]->columns);

        // Check id column
        $this->assertEquals('id', $result[0]->columns[0]->name);
        $this->assertEquals('create', $result[0]->columns[0]->operation);

        // Check title column
        $this->assertEquals('title', $result[0]->columns[1]->name);
        $this->assertEquals('create', $result[0]->columns[1]->operation);

        // Check pollable_type column
        $this->assertEquals('pollable_type', $result[0]->columns[2]->name);
        $this->assertEquals('create', $result[0]->columns[2]->operation);
        $this->assertEquals('string', $result[0]->columns[2]->propertyData->type);
        $this->assertEquals(255, $result[0]->columns[2]->propertyData->length);
        $this->assertFalse($result[0]->columns[2]->propertyData->isNullable);

        // Check pollable_id column
        $this->assertEquals('pollable_id', $result[0]->columns[3]->name);
        $this->assertEquals('create', $result[0]->columns[3]->operation);
        $this->assertEquals('int', $result[0]->columns[3]->propertyData->type);
        $this->assertFalse($result[0]->columns[3]->propertyData->isNullable);

        // Polymorphic relations should not create foreign keys
        $this->assertCount(0, $result[0]->foreignKeys);

        // Should automatically generate an index for the polymorphic columns
        $this->assertCount(1, $result[0]->indexes);
        $this->assertEquals('pollable_morph_index', $result[0]->indexes[0]->name);
        $this->assertEquals(['pollable_type', 'pollable_id'], $result[0]->indexes[0]->columns);
    }
}
