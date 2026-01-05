<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator;

require_once __DIR__ . '/TestEntities/TestManyToManyOwner.php';
require_once __DIR__ . '/TestEntities/TestManyToManyInvalid.php';
require_once __DIR__ . '/TestEntities/TestManyToManySharedOwner.php';
require_once __DIR__ . '/TestEntities/TestManyToManySharedOwnerConflict.php';

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionManyToMany;
use Articulate\Attributes\Reflection\ReflectionMorphedByMany;
use Articulate\Attributes\Reflection\ReflectionMorphToMany;
use Articulate\Attributes\Reflection\RelationInterface;
use Articulate\Modules\Database\SchemaComparator\DatabaseSchemaComparator;
use Articulate\Modules\Database\SchemaComparator\Models\CompareResult;
use Articulate\Modules\Database\SchemaComparator\RelationValidators\ManyToManyRelationValidator;
use Articulate\Modules\Database\SchemaComparator\RelationValidators\MorphToManyRelationValidator;
use Articulate\Modules\Database\SchemaReader\DatabaseSchemaReaderInterface;
use Articulate\Modules\Migrations\Generator\MigrationsCommandGenerator;
use Articulate\Schema\SchemaNaming;
use Articulate\Tests\AbstractTestCase;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestManyToManyInvalidOwner;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestManyToManyInvalidTarget;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestManyToManyNoReferencedBy;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestManyToManyOwner;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestManyToManySharedOwner;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestManyToManySharedOwnerConflict;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestManyToManySharedTarget;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestManyToManySharedTargetConflict;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestManyToManyTarget;
use RuntimeException;

class ManyToManyTest extends AbstractTestCase {
    public function testCreateMappingTableWithExtras()
    {
        $comparator = $this->comparator(
            tables: [],
            columns: fn (string $table) => [],
        );

        $results = iterator_to_array($comparator->compareAll([
            new ReflectionEntity(TestManyToManyOwner::class),
            new ReflectionEntity(TestManyToManyTarget::class),
        ]));

        $mappingTable = array_values(array_filter(
            $results,
            fn ($table) => $table->name === 'owner_target_map'
        ))[0] ?? null;

        $this->assertNotNull($mappingTable);
        $this->assertEquals(CompareResult::OPERATION_CREATE, $mappingTable->operation);
        $this->assertCount(3, $mappingTable->columns);
        $columnNames = array_map(fn ($c) => $c->name, $mappingTable->columns);
        $this->assertContains('test_many_to_many_owner_id', $columnNames);
        $this->assertContains('test_many_to_many_target_id', $columnNames);
        $this->assertContains('created_at', $columnNames);
        $this->assertCount(2, $mappingTable->foreignKeys);
        $this->assertEquals(['test_many_to_many_owner_id', 'test_many_to_many_target_id'], $mappingTable->primaryColumns);

        $generator = MigrationsCommandGenerator::forMySql();
        $sql = $generator->generate($mappingTable);
        $this->assertStringContainsString('PRIMARY KEY (`test_many_to_many_owner_id`, `test_many_to_many_target_id`)', $sql);
    }

    public function testMappingTablePropertiesMergedAcrossRelations()
    {
        $comparator = $this->comparator(
            tables: [],
            columns: fn (string $table) => [],
        );

        $results = iterator_to_array($comparator->compareAll([
            new ReflectionEntity(TestManyToManySharedOwner::class),
            new ReflectionEntity(TestManyToManySharedTarget::class),
        ]));

        $mappingTable = array_values(array_filter(
            $results,
            fn ($table) => $table->name === 'shared_owner_target_map'
        ))[0] ?? null;

        $this->assertNotNull($mappingTable);
        $this->assertEquals(CompareResult::OPERATION_CREATE, $mappingTable->operation);
        $this->assertCount(5, $mappingTable->columns);
        $columnsByName = [];
        foreach ($mappingTable->columns as $column) {
            $columnsByName[$column->name] = $column;
        }
        $this->assertTrue($columnsByName['shared_field']->propertyData->isNullable);
        $this->assertArrayHasKey('extra_one', $columnsByName);
        $this->assertArrayHasKey('extra_three', $columnsByName);
    }

    public function testInverseMissingThrows()
    {
        $comparator = $this->comparator(
            tables: [],
            columns: fn (string $table) => [],
        );

        $this->expectException(RuntimeException::class);
        iterator_to_array($comparator->compareAll([
            new ReflectionEntity(TestManyToManyInvalidOwner::class),
            new ReflectionEntity(TestManyToManyInvalidTarget::class),
        ]));
    }

    public function testConflictingMappingTableDefinitionsThrows()
    {
        $comparator = $this->comparator(
            tables: [],
            columns: fn (string $table) => [],
        );

        $this->expectException(RuntimeException::class);
        iterator_to_array($comparator->compareAll([
            new ReflectionEntity(TestManyToManySharedOwnerConflict::class),
            new ReflectionEntity(TestManyToManySharedTargetConflict::class),
        ]));
    }

    public function testManyToManyRelationValidatorWithBothMappedByAndInversedBy()
    {
        $validator = new ManyToManyRelationValidator();

        // Create a mock ReflectionManyToMany with both mappedBy and inversedBy set
        $mockRelation = $this->createMock(ReflectionManyToMany::class);
        $mockRelation->method('getMappedBy')->willReturn('mappedProperty');
        $mockRelation->method('getInversedBy')->willReturn('inverseProperty');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Many-to-many misconfigured: ownedBy and referencedBy cannot be both defined');
        $validator->validate($mockRelation);
    }

    public function testManyToManyRelationValidatorInverseSideWithExtraProperties()
    {
        $validator = new ManyToManyRelationValidator();

        // Create a mock ReflectionManyToMany for inverse side with extra properties
        $mockRelation = $this->createMock(ReflectionManyToMany::class);
        $mockRelation->method('getMappedBy')->willReturn(null);
        $mockRelation->method('getInversedBy')->willReturn(null);
        $mockRelation->method('isOwningSide')->willReturn(false);
        $mockRelation->method('getExtraProperties')->willReturn([new \stdClass()]); // Has extra properties

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Many-to-many misconfigured: inverse side cannot define extra mapping properties');
        $validator->validate($mockRelation);
    }

    public function testManyToManyRelationValidatorOwningSideValidation()
    {
        $validator = new ManyToManyRelationValidator();

        // Create mocks for owning side validation
        $mockRelation = $this->createMock(ReflectionManyToMany::class);
        $mockRelation->method('getMappedBy')->willReturn(null);
        $mockRelation->method('getInversedBy')->willReturn(null);
        $mockRelation->method('isOwningSide')->willReturn(true);
        $mockRelation->method('getExtraProperties')->willReturn([]);
        $mockRelation->method('getTargetEntity')->willReturn(\stdClass::class);
        $mockRelation->method('getDeclaringClassName')->willReturn('TestClass');
        $mockRelation->method('getPropertyName')->willReturn('testProperty');

        // Should throw exception for owning side without referencedBy
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Many-to-many owning side must specify referencedBy to define the inverse property');
        $validator->validate($mockRelation);
    }

    public function testOwningSideMustHaveReferencedByIntegration()
    {
        $comparator = $this->comparator(
            tables: [],
            columns: fn (string $table) => [],
        );

        // TestManyToManyNoReferencedBy has owning side without referencedBy - should fail
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Many-to-many owning side must specify referencedBy');
        iterator_to_array($comparator->compareAll([
            new ReflectionEntity(TestManyToManyNoReferencedBy::class),
        ]));
    }

    public function testManyToManyRelationValidatorInverseSideValidation()
    {
        $validator = new ManyToManyRelationValidator();

        $mockRelation = $this->createMock(ReflectionManyToMany::class);
        $mockRelation->method('getMappedBy')->willReturn(null);
        $mockRelation->method('getInversedBy')->willReturn(null);
        $mockRelation->method('isOwningSide')->willReturn(false);
        $mockRelation->method('getExtraProperties')->willReturn([]); // No extra properties
        $mockRelation->method('getTargetEntity')->willReturn('NonExistentClass'); // This will cause ReflectionException

        // The validator tries to create ReflectionEntity which throws ReflectionException for non-existent class
        $this->expectException(\ReflectionException::class);
        $validator->validate($mockRelation);
    }

    public function testManyToManyRelationValidatorSupports()
    {
        $validator = new ManyToManyRelationValidator();

        $mockManyToMany = $this->createMock(ReflectionManyToMany::class);
        $mockOtherRelation = $this->createMock(RelationInterface::class);

        $this->assertTrue($validator->supports($mockManyToMany));
        $this->assertFalse($validator->supports($mockOtherRelation));
    }

    public function testManyToManyRelationValidatorNonManyToManyRelation()
    {
        $validator = new ManyToManyRelationValidator();

        $mockRelation = $this->createMock(RelationInterface::class);

        // Should return early without validation for non-ManyToMany relations
        $validator->validate($mockRelation);

        // Verify that this validator doesn't support other relation types
        $this->assertFalse($validator->supports($mockRelation));
    }

    private function comparator(
        array $tables,
        callable $columns,
    ): DatabaseSchemaComparator {
        $reader = $this->createMock(DatabaseSchemaReaderInterface::class);
        $reader->expects($this->once())->method('getTables')->willReturn($tables);
        $reader->expects($this->any())->method('getTableColumns')->willReturnCallback($columns);
        $reader->expects($this->any())->method('getTableIndexes')->willReturn([]);
        $reader->expects($this->any())->method('getTableForeignKeys')->willReturn([]);

        return new DatabaseSchemaComparator($reader, new SchemaNaming());
    }

    public function testMorphToManyRelationValidatorSupportsCorrectTypes(): void
    {
        $validator = new MorphToManyRelationValidator();

        // Create mock relations to test supports method
        $morphToManyRelation = $this->createMock(ReflectionMorphToMany::class);
        $this->assertTrue($validator->supports($morphToManyRelation));

        $morphedByManyRelation = $this->createMock(ReflectionMorphedByMany::class);
        $this->assertTrue($validator->supports($morphedByManyRelation));

        // Should not support other relation types
        $manyToManyRelation = $this->createMock(ReflectionManyToMany::class);
        $this->assertFalse($validator->supports($manyToManyRelation));
    }
}
