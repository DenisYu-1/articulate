<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator;

require_once __DIR__ . '/TestEntities/TestSharedTableVariants.php';
require_once __DIR__ . '/TestEntities/TestSharedTableRelationVariants.php';

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Modules\DatabaseSchemaComparator\DatabaseSchemaComparator;
use Articulate\Modules\DatabaseSchemaComparator\Models\CompareResult;
use Articulate\Modules\DatabaseSchemaReader\DatabaseSchemaReader;
use Articulate\Modules\MigrationsGenerator\MigrationsCommandGenerator;
use Articulate\Schema\SchemaNaming;
use Articulate\Tests\AbstractTestCase;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestSharedTableRelationOwnerA;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestSharedTableRelationOwnerB;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestSharedTableRelationTarget;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestSharedTableVariantA;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestSharedTableVariantB;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestSharedTableVariantConflict;
use RuntimeException;

class SharedTableTest extends AbstractTestCase
{
    public function testMergesNullableAcrossEntitiesForSameTable()
    {
        $comparator = $this->comparator(
            tables: [],
            columns: fn (string $table) => [],
        );

        $results = iterator_to_array($comparator->compareAll([
            new ReflectionEntity(TestSharedTableVariantA::class),
            new ReflectionEntity(TestSharedTableVariantB::class),
        ]));

        $table = array_values(array_filter(
            $results,
            fn ($table) => $table->name === 'shared_table_base'
        ))[0] ?? null;

        $this->assertNotNull($table);
        $this->assertEquals(CompareResult::OPERATION_CREATE, $table->operation);
        $columnsByName = [];
        foreach ($table->columns as $column) {
            $columnsByName[$column->name] = $column;
        }
        $this->assertTrue($columnsByName['shared_field']->propertyData->isNullable);

        $sql = (new MigrationsCommandGenerator())->generate($table);
        $this->assertStringContainsString('shared_field int', $sql);
    }

    public function testConflictingDefinitionsThrow()
    {
        $comparator = $this->comparator(
            tables: [],
            columns: fn (string $table) => [],
        );

        $this->expectException(RuntimeException::class);
        iterator_to_array($comparator->compareAll([
            new ReflectionEntity(TestSharedTableVariantA::class),
            new ReflectionEntity(TestSharedTableVariantConflict::class),
        ]));
    }

    public function testRelationColumnMergedNullableWithForeignKey()
    {
        $comparator = $this->comparator(
            tables: [],
            columns: fn (string $table) => [],
        );

        $results = iterator_to_array($comparator->compareAll([
            new ReflectionEntity(TestSharedTableRelationOwnerA::class),
            new ReflectionEntity(TestSharedTableRelationOwnerB::class),
            new ReflectionEntity(TestSharedTableRelationTarget::class),
        ]));

        $table = array_values(array_filter(
            $results,
            fn ($table) => $table->name === 'shared_table_rel'
        ))[0] ?? null;

        $this->assertNotNull($table);
        $this->assertEquals(CompareResult::OPERATION_CREATE, $table->operation);
        $columnsByName = [];
        foreach ($table->columns as $column) {
            $columnsByName[$column->name] = $column;
        }
        $this->assertTrue($columnsByName['target_id']->propertyData->isNullable);
        $this->assertNotEmpty($table->foreignKeys);
    }

    private function comparator(
        array $tables,
        callable $columns,
    ): DatabaseSchemaComparator {
        $reader = $this->createMock(DatabaseSchemaReader::class);
        $reader->expects($this->once())->method('getTables')->willReturn($tables);
        $reader->expects($this->any())->method('getTableColumns')->willReturnCallback($columns);
        $reader->expects($this->any())->method('getTableIndexes')->willReturn([]);
        $reader->expects($this->any())->method('getTableForeignKeys')->willReturn([]);

        return new DatabaseSchemaComparator($reader, new SchemaNaming());
    }
}
