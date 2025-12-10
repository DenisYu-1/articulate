<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator;

require_once __DIR__ . '/TestEntities/TestManyToManyOwner.php';
require_once __DIR__ . '/TestEntities/TestManyToManyInvalid.php';

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Modules\DatabaseSchemaComparator\DatabaseSchemaComparator;
use Articulate\Modules\DatabaseSchemaComparator\Models\CompareResult;
use Articulate\Modules\DatabaseSchemaReader\DatabaseSchemaReader;
use Articulate\Modules\MigrationsGenerator\MigrationsCommandGenerator;
use Articulate\Schema\SchemaNaming;
use Articulate\Tests\AbstractTestCase;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestManyToManyInvalidOwner;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestManyToManyInvalidTarget;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestManyToManyOwner;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestManyToManyTarget;
use RuntimeException;

class ManyToManyTest extends AbstractTestCase
{
    public function testCreateMappingTableWithExtras()
    {
        $comparator = $this->comparator(
            tables: [],
            columns: fn(string $table) => [],
        );

        $results = iterator_to_array($comparator->compareAll([
            new ReflectionEntity(TestManyToManyOwner::class),
            new ReflectionEntity(TestManyToManyTarget::class),
        ]));

        $mappingTable = array_values(array_filter(
            $results,
            fn($table) => $table->name === 'owner_target_map'
        ))[0] ?? null;

        $this->assertNotNull($mappingTable);
        $this->assertEquals(CompareResult::OPERATION_CREATE, $mappingTable->operation);
        $this->assertCount(3, $mappingTable->columns);
        $columnNames = array_map(fn($c) => $c->name, $mappingTable->columns);
        $this->assertContains('test_many_to_many_owner_id', $columnNames);
        $this->assertContains('test_many_to_many_target_id', $columnNames);
        $this->assertContains('created_at', $columnNames);
        $this->assertCount(2, $mappingTable->foreignKeys);
        $this->assertEquals(['test_many_to_many_owner_id', 'test_many_to_many_target_id'], $mappingTable->primaryColumns);

        $generator = new MigrationsCommandGenerator();
        $sql = $generator->generate($mappingTable);
        $this->assertStringContainsString('PRIMARY KEY (`test_many_to_many_owner_id`, `test_many_to_many_target_id`)', $sql);
    }

    public function testInverseMissingThrows()
    {
        $comparator = $this->comparator(
            tables: [],
            columns: fn(string $table) => [],
        );

        $this->expectException(RuntimeException::class);
        iterator_to_array($comparator->compareAll([
            new ReflectionEntity(TestManyToManyInvalidOwner::class),
            new ReflectionEntity(TestManyToManyInvalidTarget::class),
        ]));
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
