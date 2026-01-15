<?php

namespace Articulate\Modules\Database\SchemaComparator;

use Articulate\Attributes\Property;
use Articulate\Attributes\Reflection\ReflectionProperty;
use Articulate\Attributes\Reflection\ReflectionRelation;
use Articulate\Modules\Database\SchemaComparator\Comparators\ColumnComparator;
use Articulate\Modules\Database\SchemaComparator\Models\ColumnCompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\CompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\PropertiesData;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

class ColumnComparatorTest extends TestCase {
    private ColumnComparator $comparator;

    protected function setUp(): void
    {
        $this->comparator = new ColumnComparator();
    }

    public function testColumnComparatorCanBeInstantiated(): void
    {
        $this->assertInstanceOf(ColumnComparator::class, $this->comparator);
    }

    // ===== compareColumns Tests =====

    public function testCompareColumnsWithEmptyInputs(): void
    {
        $result = $this->comparator->compareColumns([], []);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testCompareColumnsCreateSingleColumn(): void
    {
        $propertiesIndexed = [
            'name' => [
                'type' => 'string',
                'nullable' => false,
                'default' => null,
                'length' => 255,
                'relation' => null,
                'foreignKeyRequired' => false,
                'referencedColumn' => null,
                'generatorType' => null,
                'sequence' => null,
                'isPrimaryKey' => false,
                'isAutoIncrement' => false,
            ],
        ];

        $columnsIndexed = [];
        $results = $this->comparator->compareColumns($propertiesIndexed, $columnsIndexed);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(ColumnCompareResult::class, $results[0]);
        $this->assertEquals('name', $results[0]->name);
        $this->assertEquals(CompareResult::OPERATION_CREATE, $results[0]->operation);
        $this->assertEquals('string', $results[0]->propertyData->type);
        $this->assertFalse($results[0]->propertyData->isNullable);
        $this->assertNull($results[0]->propertyData->defaultValue);
        $this->assertEquals(255, $results[0]->propertyData->length);
        $this->assertInstanceOf(PropertiesData::class, $results[0]->columnData);
        $this->assertNull($results[0]->columnData->type);
    }

    public function testCompareColumnsCreateColumnWithAllProperties(): void
    {
        $propertiesIndexed = [
            'id' => [
                'type' => 'int',
                'nullable' => false,
                'default' => null,
                'length' => null,
                'relation' => null,
                'foreignKeyRequired' => false,
                'referencedColumn' => null,
                'generatorType' => 'auto',
                'sequence' => null,
                'isPrimaryKey' => true,
                'isAutoIncrement' => true,
            ],
        ];

        $columnsIndexed = [];
        $results = $this->comparator->compareColumns($propertiesIndexed, $columnsIndexed);

        $this->assertCount(1, $results);
        $result = $results[0];
        $this->assertEquals('id', $result->name);
        $this->assertEquals(CompareResult::OPERATION_CREATE, $result->operation);
        $this->assertEquals('int', $result->propertyData->type);
        $this->assertFalse($result->propertyData->isNullable);
        $this->assertNull($result->propertyData->defaultValue);
        $this->assertNull($result->propertyData->length);
        $this->assertEquals('auto', $result->propertyData->generatorType);
        $this->assertNull($result->propertyData->sequence);
        $this->assertTrue($result->propertyData->isPrimaryKey);
        $this->assertTrue($result->propertyData->isAutoIncrement);
    }

    public function testCompareColumnsDeleteSingleColumn(): void
    {
        $propertiesIndexed = [];

        $columnsIndexed = [
            'old_column' => (object) [
                'type' => 'varchar',
                'isNullable' => true,
                'defaultValue' => 'default_value',
                'length' => 100,
            ],
        ];

        $results = $this->comparator->compareColumns($propertiesIndexed, $columnsIndexed);

        $this->assertCount(1, $results);
        $result = $results[0];
        $this->assertEquals('old_column', $result->name);
        $this->assertEquals(CompareResult::OPERATION_DELETE, $result->operation);
        $this->assertInstanceOf(PropertiesData::class, $result->propertyData);
        $this->assertNull($result->propertyData->type);
        $this->assertEquals('varchar', $result->columnData->type);
        $this->assertTrue($result->columnData->isNullable);
        $this->assertEquals('default_value', $result->columnData->defaultValue);
        $this->assertEquals(100, $result->columnData->length);
    }

    public function testCompareColumnsUpdateColumnTypeOnly(): void
    {
        $propertiesIndexed = [
            'name' => [
                'type' => 'string',
                'nullable' => false,
                'default' => null,
                'length' => 255,
                'relation' => null,
                'foreignKeyRequired' => false,
                'referencedColumn' => null,
                'generatorType' => null,
                'sequence' => null,
                'isPrimaryKey' => false,
                'isAutoIncrement' => false,
            ],
        ];

        $columnsIndexed = [
            'name' => (object) [
                'type' => 'int',
                'isNullable' => false,
                'defaultValue' => null,
                'length' => null,
            ],
        ];

        $results = $this->comparator->compareColumns($propertiesIndexed, $columnsIndexed);

        $this->assertCount(1, $results);
        $result = $results[0];
        $this->assertEquals('name', $result->name);
        $this->assertEquals(CompareResult::OPERATION_UPDATE, $result->operation);
        $this->assertFalse($result->typeMatch);
        $this->assertTrue($result->isNullableMatch);
        $this->assertTrue($result->isDefaultValueMatch);
        $this->assertFalse($result->isLengthMatch);
        $this->assertTrue($result->hasChanges());
    }

    public function testCompareColumnsUpdateColumnNullableOnly(): void
    {
        $propertiesIndexed = [
            'name' => [
                'type' => 'string',
                'nullable' => true, // Changed from false to true
                'default' => null,
                'length' => 255,
                'relation' => null,
                'foreignKeyRequired' => false,
                'referencedColumn' => null,
                'generatorType' => null,
                'sequence' => null,
                'isPrimaryKey' => false,
                'isAutoIncrement' => false,
            ],
        ];

        $columnsIndexed = [
            'name' => (object) [
                'type' => 'string',
                'isNullable' => false,
                'defaultValue' => null,
                'length' => 255,
            ],
        ];

        $results = $this->comparator->compareColumns($propertiesIndexed, $columnsIndexed);

        $this->assertCount(1, $results);
        $result = $results[0];
        $this->assertEquals(CompareResult::OPERATION_UPDATE, $result->operation);
        $this->assertTrue($result->typeMatch);
        $this->assertFalse($result->isNullableMatch);
        $this->assertTrue($result->isDefaultValueMatch);
        $this->assertTrue($result->isLengthMatch);
        $this->assertTrue($result->hasChanges());
    }

    public function testCompareColumnsUpdateColumnDefaultValueOnly(): void
    {
        $propertiesIndexed = [
            'status' => [
                'type' => 'string',
                'nullable' => false,
                'default' => 'active', // New default
                'length' => 50,
                'relation' => null,
                'foreignKeyRequired' => false,
                'referencedColumn' => null,
                'generatorType' => null,
                'sequence' => null,
                'isPrimaryKey' => false,
                'isAutoIncrement' => false,
            ],
        ];

        $columnsIndexed = [
            'status' => (object) [
                'type' => 'string',
                'isNullable' => false,
                'defaultValue' => null,
                'length' => 50,
            ],
        ];

        $results = $this->comparator->compareColumns($propertiesIndexed, $columnsIndexed);

        $this->assertCount(1, $results);
        $result = $results[0];
        $this->assertEquals(CompareResult::OPERATION_UPDATE, $result->operation);
        $this->assertTrue($result->typeMatch);
        $this->assertTrue($result->isNullableMatch);
        $this->assertFalse($result->isDefaultValueMatch);
        $this->assertTrue($result->isLengthMatch);
        $this->assertTrue($result->hasChanges());
    }

    public function testCompareColumnsUpdateColumnLengthOnly(): void
    {
        $propertiesIndexed = [
            'name' => [
                'type' => 'string',
                'nullable' => false,
                'default' => null,
                'length' => 100, // Changed length
                'relation' => null,
                'foreignKeyRequired' => false,
                'referencedColumn' => null,
                'generatorType' => null,
                'sequence' => null,
                'isPrimaryKey' => false,
                'isAutoIncrement' => false,
            ],
        ];

        $columnsIndexed = [
            'name' => (object) [
                'type' => 'string',
                'isNullable' => false,
                'defaultValue' => null,
                'length' => 255,
            ],
        ];

        $results = $this->comparator->compareColumns($propertiesIndexed, $columnsIndexed);

        $this->assertCount(1, $results);
        $result = $results[0];
        $this->assertEquals(CompareResult::OPERATION_UPDATE, $result->operation);
        $this->assertTrue($result->typeMatch);
        $this->assertTrue($result->isNullableMatch);
        $this->assertTrue($result->isDefaultValueMatch);
        $this->assertFalse($result->isLengthMatch);
        $this->assertTrue($result->hasChanges());
    }

    public function testCompareColumnsUpdateColumnPartialMatches(): void
    {
        $propertiesIndexed = [
            'mixed_column' => [
                'type' => 'string', // Same
                'nullable' => true, // Different
                'default' => 'new_default', // Different
                'length' => 255, // Same
                'relation' => null,
                'foreignKeyRequired' => false,
                'referencedColumn' => null,
                'generatorType' => null,
                'sequence' => null,
                'isPrimaryKey' => false,
                'isAutoIncrement' => false,
            ],
        ];

        $columnsIndexed = [
            'mixed_column' => (object) [
                'type' => 'string', // Same
                'isNullable' => false, // Different
                'defaultValue' => null, // Different
                'length' => 255, // Same
            ],
        ];

        $results = $this->comparator->compareColumns($propertiesIndexed, $columnsIndexed);

        $this->assertCount(1, $results);
        $result = $results[0];
        $this->assertEquals(CompareResult::OPERATION_UPDATE, $result->operation);

        // Check partial matching
        $this->assertTrue($result->typeMatch); // Same
        $this->assertFalse($result->isNullableMatch); // Different
        $this->assertFalse($result->isDefaultValueMatch); // Different
        $this->assertTrue($result->isLengthMatch); // Same

        $this->assertTrue($result->hasChanges()); // Should have changes

        // Verify PropertiesData content
        $this->assertEquals('string', $result->propertyData->type);
        $this->assertTrue($result->propertyData->isNullable);
        $this->assertEquals('new_default', $result->propertyData->defaultValue);
        $this->assertEquals(255, $result->propertyData->length);

        $this->assertEquals('string', $result->columnData->type);
        $this->assertFalse($result->columnData->isNullable);
        $this->assertNull($result->columnData->defaultValue);
        $this->assertEquals(255, $result->columnData->length);
    }

    public function testCompareColumnsUpdatePropertiesDataConstructor(): void
    {
        // Test that the PropertiesData constructor in update section works correctly
        // The update section uses a different constructor than create section
        $propertiesIndexed = [
            'test_column' => [
                'type' => 'varchar',
                'nullable' => false,
                'default' => 'test',
                'length' => 100,
                'relation' => null,
                'foreignKeyRequired' => false,
                'referencedColumn' => null,
                'generatorType' => 'uuid', // Not used in update PropertiesData
                'sequence' => 'seq', // Not used in update PropertiesData
                'isPrimaryKey' => true, // Not used in update PropertiesData
                'isAutoIncrement' => true, // Not used in update PropertiesData
            ],
        ];

        $columnsIndexed = [
            'test_column' => (object) [
                'type' => 'text', // Different
                'isNullable' => false,
                'defaultValue' => 'test',
                'length' => 100,
            ],
        ];

        $results = $this->comparator->compareColumns($propertiesIndexed, $columnsIndexed);

        $this->assertCount(1, $results);
        $result = $results[0];

        // Property data should include all fields from propertiesIndexed
        $this->assertEquals('varchar', $result->propertyData->type);
        $this->assertFalse($result->propertyData->isNullable);
        $this->assertEquals('test', $result->propertyData->defaultValue);
        $this->assertEquals(100, $result->propertyData->length);

        // Column data should only include compared fields from database
        $this->assertEquals('text', $result->columnData->type);
        $this->assertFalse($result->columnData->isNullable);
        $this->assertEquals('test', $result->columnData->defaultValue);
        $this->assertEquals(100, $result->columnData->length);

        // The additional fields (generatorType, sequence, etc.) are not compared
        // so they're not included in the PropertiesData for updates
        $this->assertFalse($result->typeMatch); // varchar vs text
    }

    public function testCompareColumnsNoUpdateWhenIdentical(): void
    {
        $propertiesIndexed = [
            'name' => [
                'type' => 'string',
                'nullable' => false,
                'default' => null,
                'length' => 255,
                'relation' => null,
                'foreignKeyRequired' => false,
                'referencedColumn' => null,
                'generatorType' => null,
                'sequence' => null,
                'isPrimaryKey' => false,
                'isAutoIncrement' => false,
            ],
        ];

        $columnsIndexed = [
            'name' => (object) [
                'type' => 'string',
                'isNullable' => false,
                'defaultValue' => null,
                'length' => 255,
            ],
        ];

        $results = $this->comparator->compareColumns($propertiesIndexed, $columnsIndexed);

        $this->assertCount(0, $results);
    }

    public function testCompareColumnsMixedOperations(): void
    {
        $propertiesIndexed = [
            // New column
            'new_column' => [
                'type' => 'int',
                'nullable' => false,
                'default' => null,
                'length' => null,
                'relation' => null,
                'foreignKeyRequired' => false,
                'referencedColumn' => null,
                'generatorType' => null,
                'sequence' => null,
                'isPrimaryKey' => false,
                'isAutoIncrement' => false,
            ],
            // Changed column
            'changed_column' => [
                'type' => 'string',
                'nullable' => false,
                'default' => null,
                'length' => 100,
                'relation' => null,
                'foreignKeyRequired' => false,
                'referencedColumn' => null,
                'generatorType' => null,
                'sequence' => null,
                'isPrimaryKey' => false,
                'isAutoIncrement' => false,
            ],
        ];

        $columnsIndexed = [
            // Changed column (different length)
            'changed_column' => (object) [
                'type' => 'string',
                'isNullable' => false,
                'defaultValue' => null,
                'length' => 255,
            ],
            // Deleted column
            'deleted_column' => (object) [
                'type' => 'bool',
                'isNullable' => true,
                'defaultValue' => 'false',
                'length' => null,
            ],
        ];

        $results = $this->comparator->compareColumns($propertiesIndexed, $columnsIndexed);

        $this->assertCount(3, $results);

        $operations = array_map(fn ($result) => $result->operation, $results);
        $names = array_map(fn ($result) => $result->name, $results);

        $this->assertContains(CompareResult::OPERATION_CREATE, $operations);
        $this->assertContains(CompareResult::OPERATION_UPDATE, $operations);
        $this->assertContains(CompareResult::OPERATION_DELETE, $operations);

        $this->assertContains('new_column', $names);
        $this->assertContains('changed_column', $names);
        $this->assertContains('deleted_column', $names);
    }

    // ===== mergeColumnDefinition Tests =====

    public function testMergeColumnDefinitionWithNewColumn(): void
    {
        $property = $this->createMock(ReflectionProperty::class);
        $property->method('getType')->willReturn('string');
        $property->method('isNullable')->willReturn(false);
        $property->method('getDefaultValue')->willReturn(null);
        $property->method('getLength')->willReturn(255);
        $property->method('getGeneratorType')->willReturn(null);
        $property->method('getSequence')->willReturn(null);
        $property->method('isPrimaryKey')->willReturn(false);
        $property->method('isAutoIncrement')->willReturn(false);

        $propertiesIndexed = [];
        $result = $this->comparator->mergeColumnDefinition($propertiesIndexed, 'name', $property, 'users');

        $this->assertArrayHasKey('name', $result);
        $this->assertEquals('string', $result['name']['type']);
        $this->assertFalse($result['name']['nullable']);
        $this->assertNull($result['name']['default']);
        $this->assertEquals(255, $result['name']['length']);
        $this->assertNull($result['name']['relation']);
        $this->assertFalse($result['name']['foreignKeyRequired']);
        $this->assertNull($result['name']['referencedColumn']);
    }

    public function testMergeColumnDefinitionWithRelationProperty(): void
    {
        $property = $this->createMock(ReflectionRelation::class);
        $property->method('getType')->willReturn('int');
        $property->method('isNullable')->willReturn(false);
        $property->method('getDefaultValue')->willReturn(null);
        $property->method('getLength')->willReturn(null);
        $property->method('isForeignKeyRequired')->willReturn(true);
        $property->method('getReferencedColumnName')->willReturn('id');

        $propertiesIndexed = [];
        $result = $this->comparator->mergeColumnDefinition($propertiesIndexed, 'user_id', $property, 'posts');

        $this->assertArrayHasKey('user_id', $result);
        $this->assertEquals('int', $result['user_id']['type']);
        $this->assertEquals($property, $result['user_id']['relation']);
        $this->assertTrue($result['user_id']['foreignKeyRequired']);
        $this->assertEquals('id', $result['user_id']['referencedColumn']);
    }

    public function testMergeColumnDefinitionWithExistingColumnCompatible(): void
    {
        $existingProperty = $this->createMock(ReflectionProperty::class);
        $existingProperty->method('getType')->willReturn('string');
        $existingProperty->method('isNullable')->willReturn(false);
        $existingProperty->method('getDefaultValue')->willReturn(null);
        $existingProperty->method('getLength')->willReturn(255);
        $existingProperty->method('getGeneratorType')->willReturn(null);
        $existingProperty->method('getSequence')->willReturn(null);
        $existingProperty->method('isPrimaryKey')->willReturn(false);
        $existingProperty->method('isAutoIncrement')->willReturn(false);

        $propertiesIndexed = $this->comparator->mergeColumnDefinition([], 'name', $existingProperty, 'users');

        // Second property with compatible definition
        $newProperty = $this->createMock(ReflectionProperty::class);
        $newProperty->method('getType')->willReturn('string');
        $newProperty->method('isNullable')->willReturn(true); // Different nullable - should be merged
        $newProperty->method('getDefaultValue')->willReturn(null);
        $newProperty->method('getLength')->willReturn(255);
        $newProperty->method('getGeneratorType')->willReturn(null);
        $newProperty->method('getSequence')->willReturn(null);
        $newProperty->method('isPrimaryKey')->willReturn(false);
        $newProperty->method('isAutoIncrement')->willReturn(false);

        $result = $this->comparator->mergeColumnDefinition($propertiesIndexed, 'name', $newProperty, 'users');

        $this->assertArrayHasKey('name', $result);
        $this->assertEquals('string', $result['name']['type']);
        $this->assertTrue($result['name']['nullable']); // Should be merged to true
        $this->assertEquals(255, $result['name']['length']);
    }

    public function testMergeColumnDefinitionThrowsOnTypeConflict(): void
    {
        $existingProperty = $this->createMock(ReflectionProperty::class);
        $existingProperty->method('getType')->willReturn('string');
        $existingProperty->method('isNullable')->willReturn(false);
        $existingProperty->method('getDefaultValue')->willReturn(null);
        $existingProperty->method('getLength')->willReturn(255);

        $propertiesIndexed = $this->comparator->mergeColumnDefinition([], 'name', $existingProperty, 'users');

        $conflictingProperty = $this->createMock(ReflectionProperty::class);
        $conflictingProperty->method('getType')->willReturn('int'); // Different type
        $conflictingProperty->method('isNullable')->willReturn(false);
        $conflictingProperty->method('getDefaultValue')->willReturn(null);
        $conflictingProperty->method('getLength')->willReturn(255);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Column "name" on table "users" conflicts between entities');

        $this->comparator->mergeColumnDefinition($propertiesIndexed, 'name', $conflictingProperty, 'users');
    }

    public function testMergeColumnDefinitionThrowsOnLengthConflict(): void
    {
        $existingProperty = $this->createMock(ReflectionProperty::class);
        $existingProperty->method('getType')->willReturn('string');
        $existingProperty->method('isNullable')->willReturn(false);
        $existingProperty->method('getDefaultValue')->willReturn(null);
        $existingProperty->method('getLength')->willReturn(255);

        $propertiesIndexed = $this->comparator->mergeColumnDefinition([], 'name', $existingProperty, 'users');

        $conflictingProperty = $this->createMock(ReflectionProperty::class);
        $conflictingProperty->method('getType')->willReturn('string');
        $conflictingProperty->method('isNullable')->willReturn(false);
        $conflictingProperty->method('getDefaultValue')->willReturn(null);
        $conflictingProperty->method('getLength')->willReturn(100); // Different length

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Column "name" on table "users" conflicts between entities');

        $this->comparator->mergeColumnDefinition($propertiesIndexed, 'name', $conflictingProperty, 'users');
    }

    public function testMergeColumnDefinitionThrowsOnDefaultValueConflict(): void
    {
        $existingProperty = $this->createMock(ReflectionProperty::class);
        $existingProperty->method('getType')->willReturn('string');
        $existingProperty->method('isNullable')->willReturn(false);
        $existingProperty->method('getDefaultValue')->willReturn('default1');
        $existingProperty->method('getLength')->willReturn(255);

        $propertiesIndexed = $this->comparator->mergeColumnDefinition([], 'status', $existingProperty, 'users');

        $conflictingProperty = $this->createMock(ReflectionProperty::class);
        $conflictingProperty->method('getType')->willReturn('string');
        $conflictingProperty->method('isNullable')->willReturn(false);
        $conflictingProperty->method('getDefaultValue')->willReturn('default2'); // Different default
        $conflictingProperty->method('getLength')->willReturn(255);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Column "status" on table "users" conflicts between entities');

        $this->comparator->mergeColumnDefinition($propertiesIndexed, 'status', $conflictingProperty, 'users');
    }

    public function testMergeColumnDefinitionThrowsOnRelationVsScalarConflict(): void
    {
        $existingProperty = $this->createMock(ReflectionProperty::class);
        $existingProperty->method('getType')->willReturn('int');
        $existingProperty->method('isNullable')->willReturn(false);
        $existingProperty->method('getDefaultValue')->willReturn(null);
        $existingProperty->method('getLength')->willReturn(null);

        $propertiesIndexed = $this->comparator->mergeColumnDefinition([], 'user_id', $existingProperty, 'posts');

        $relationProperty = $this->createMock(ReflectionRelation::class);
        $relationProperty->method('getType')->willReturn('int');
        $relationProperty->method('isNullable')->willReturn(false);
        $relationProperty->method('getDefaultValue')->willReturn(null);
        $relationProperty->method('getLength')->willReturn(null);
        $relationProperty->method('isForeignKeyRequired')->willReturn(true); // This makes it a relation

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Column "user_id" on table "posts" conflicts between relation and scalar definitions');

        $this->comparator->mergeColumnDefinition($propertiesIndexed, 'user_id', $relationProperty, 'posts');
    }

    public function testMergeColumnDefinitionThrowsOnRelationTargetConflict(): void
    {
        $existingRelation = $this->createMock(ReflectionRelation::class);
        $existingRelation->method('getType')->willReturn('int');
        $existingRelation->method('isNullable')->willReturn(false);
        $existingRelation->method('getDefaultValue')->willReturn(null);
        $existingRelation->method('getLength')->willReturn(null);
        $existingRelation->method('isForeignKeyRequired')->willReturn(true);
        $existingRelation->method('getReferencedColumnName')->willReturn('id');
        $existingRelation->method('getTargetEntity')->willReturn('Articulate\\Tests\\Modules\\DatabaseSchemaComparator\\TestEntities\\TestEntity');

        $propertiesIndexed = $this->comparator->mergeColumnDefinition([], 'user_id', $existingRelation, 'posts');

        $conflictingRelation = $this->createMock(ReflectionRelation::class);
        $conflictingRelation->method('getType')->willReturn('int');
        $conflictingRelation->method('isNullable')->willReturn(false);
        $conflictingRelation->method('getDefaultValue')->willReturn(null);
        $conflictingRelation->method('getLength')->willReturn(null);
        $conflictingRelation->method('isForeignKeyRequired')->willReturn(true);
        $conflictingRelation->method('getReferencedColumnName')->willReturn('uuid'); // Different referenced column
        $conflictingRelation->method('getTargetEntity')->willReturn('Articulate\\Tests\\Modules\\DatabaseSchemaComparator\\TestEntities\\TestEntity');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Relation column "user_id" on table "posts" points to different targets');

        $this->comparator->mergeColumnDefinition($propertiesIndexed, 'user_id', $conflictingRelation, 'posts');
    }

    public function testMergeColumnDefinitionWithCompatibleRelationsSameTarget(): void
    {
        $existingRelation = $this->createMock(ReflectionRelation::class);
        $existingRelation->method('getType')->willReturn('int');
        $existingRelation->method('isNullable')->willReturn(false);
        $existingRelation->method('getDefaultValue')->willReturn(null);
        $existingRelation->method('getLength')->willReturn(null);
        $existingRelation->method('isForeignKeyRequired')->willReturn(true);
        $existingRelation->method('getReferencedColumnName')->willReturn('id');
        $existingRelation->method('getTargetEntity')->willReturn('Articulate\\Tests\\Modules\\DatabaseSchemaComparator\\TestEntities\\TestEntity');

        $propertiesIndexed = $this->comparator->mergeColumnDefinition([], 'user_id', $existingRelation, 'posts');

        $compatibleRelation = $this->createMock(ReflectionRelation::class);
        $compatibleRelation->method('getType')->willReturn('int');
        $compatibleRelation->method('isNullable')->willReturn(false);
        $compatibleRelation->method('getDefaultValue')->willReturn(null);
        $compatibleRelation->method('getLength')->willReturn(null);
        $compatibleRelation->method('isForeignKeyRequired')->willReturn(true);
        $compatibleRelation->method('getReferencedColumnName')->willReturn('id'); // Same referenced column
        $compatibleRelation->method('getTargetEntity')->willReturn('Articulate\\Tests\\Modules\\DatabaseSchemaComparator\\TestEntities\\TestEntity'); // Same target entity

        $result = $this->comparator->mergeColumnDefinition($propertiesIndexed, 'user_id', $compatibleRelation, 'posts');

        // Should merge successfully without throwing exception
        $this->assertArrayHasKey('user_id', $result);
        $this->assertEquals('int', $result['user_id']['type']);
        $this->assertTrue($result['user_id']['foreignKeyRequired']);
        $this->assertEquals('id', $result['user_id']['referencedColumn']);
    }

    public function testMergeColumnDefinitionWithNullTargetRelations(): void
    {
        // Test the case where relations have null targets (MorphTo case)
        // This should skip the target comparison entirely
        $existingRelation = $this->createMock(ReflectionRelation::class);
        $existingRelation->method('getType')->willReturn('int');
        $existingRelation->method('isNullable')->willReturn(false);
        $existingRelation->method('getDefaultValue')->willReturn(null);
        $existingRelation->method('getLength')->willReturn(null);
        $existingRelation->method('isForeignKeyRequired')->willReturn(true);
        $existingRelation->method('getReferencedColumnName')->willReturn('id');
        $existingRelation->method('getTargetEntity')->willReturn(null); // Null target

        $propertiesIndexed = $this->comparator->mergeColumnDefinition([], 'commentable_id', $existingRelation, 'comments');

        $incomingRelation = $this->createMock(ReflectionRelation::class);
        $incomingRelation->method('getType')->willReturn('int');
        $incomingRelation->method('isNullable')->willReturn(false);
        $incomingRelation->method('getDefaultValue')->willReturn(null);
        $incomingRelation->method('getLength')->willReturn(null);
        $incomingRelation->method('isForeignKeyRequired')->willReturn(true);
        $incomingRelation->method('getReferencedColumnName')->willReturn('id');
        $incomingRelation->method('getTargetEntity')->willReturn(null); // Also null target

        $result = $this->comparator->mergeColumnDefinition($propertiesIndexed, 'commentable_id', $incomingRelation, 'comments');

        // Should merge successfully without checking targets (since both are null)
        $this->assertArrayHasKey('commentable_id', $result);
        $this->assertEquals('int', $result['commentable_id']['type']);
    }

    public function testMergeColumnDefinitionWithMorphToRelationsNullTargets(): void
    {
        $existingRelation = $this->createMock(ReflectionRelation::class);
        $existingRelation->method('getType')->willReturn('int');
        $existingRelation->method('isNullable')->willReturn(false);
        $existingRelation->method('getDefaultValue')->willReturn(null);
        $existingRelation->method('getLength')->willReturn(null);
        $existingRelation->method('isForeignKeyRequired')->willReturn(true);
        $existingRelation->method('getReferencedColumnName')->willReturn('id');
        $existingRelation->method('getTargetEntity')->willReturn(null); // MorphTo - null target

        $propertiesIndexed = $this->comparator->mergeColumnDefinition([], 'commentable_id', $existingRelation, 'comments');

        $anotherMorphToRelation = $this->createMock(ReflectionRelation::class);
        $anotherMorphToRelation->method('getType')->willReturn('int');
        $anotherMorphToRelation->method('isNullable')->willReturn(false);
        $anotherMorphToRelation->method('getDefaultValue')->willReturn(null);
        $anotherMorphToRelation->method('getLength')->willReturn(null);
        $anotherMorphToRelation->method('isForeignKeyRequired')->willReturn(true);
        $anotherMorphToRelation->method('getReferencedColumnName')->willReturn('id');
        $anotherMorphToRelation->method('getTargetEntity')->willReturn(null); // Also MorphTo - null target

        $result = $this->comparator->mergeColumnDefinition($propertiesIndexed, 'commentable_id', $anotherMorphToRelation, 'comments');

        // Should skip comparison and merge successfully
        $this->assertArrayHasKey('commentable_id', $result);
        $this->assertEquals('int', $result['commentable_id']['type']);
    }

    public function testMergeColumnDefinitionMergesAllPropertiesWithNullCoalescing(): void
    {
        // First property with some values
        $existingProperty = $this->createMock(ReflectionProperty::class);
        $existingProperty->method('getType')->willReturn('string');
        $existingProperty->method('isNullable')->willReturn(false);
        $existingProperty->method('getDefaultValue')->willReturn('default_value');
        $existingProperty->method('getLength')->willReturn(255);
        $existingProperty->method('getGeneratorType')->willReturn('uuid');
        $existingProperty->method('getSequence')->willReturn('my_sequence');
        $existingProperty->method('isPrimaryKey')->willReturn(true);
        $existingProperty->method('isAutoIncrement')->willReturn(false);

        $propertiesIndexed = $this->comparator->mergeColumnDefinition([], 'test_column', $existingProperty, 'test_table');

        // Second property with default values (false/null) that should be overridden by existing
        $incomingProperty = $this->createMock(ReflectionProperty::class);
        $incomingProperty->method('getType')->willReturn('string');
        $incomingProperty->method('isNullable')->willReturn(false);
        $incomingProperty->method('getDefaultValue')->willReturn('default_value');
        $incomingProperty->method('getLength')->willReturn(255);
        $incomingProperty->method('getGeneratorType')->willReturn(null); // Should use existing
        $incomingProperty->method('getSequence')->willReturn(null); // Should use existing
        $incomingProperty->method('isPrimaryKey')->willReturn(false); // Should be OR'd with existing true
        $incomingProperty->method('isAutoIncrement')->willReturn(false); // Should be OR'd with existing false

        $result = $this->comparator->mergeColumnDefinition($propertiesIndexed, 'test_column', $incomingProperty, 'test_table');

        $this->assertArrayHasKey('test_column', $result);
        $this->assertEquals('uuid', $result['test_column']['generatorType']); // From existing
        $this->assertEquals('my_sequence', $result['test_column']['sequence']); // From existing
        $this->assertTrue($result['test_column']['isPrimaryKey']); // From existing
        $this->assertFalse($result['test_column']['isAutoIncrement']); // From existing
    }

    public function testMergeColumnDefinitionMergesRelationProperties(): void
    {
        // First relation with some properties
        $existingRelation = $this->createMock(ReflectionRelation::class);
        $existingRelation->method('getType')->willReturn('int');
        $existingRelation->method('isNullable')->willReturn(false);
        $existingRelation->method('getDefaultValue')->willReturn(null);
        $existingRelation->method('getLength')->willReturn(null);
        $existingRelation->method('isForeignKeyRequired')->willReturn(true);
        $existingRelation->method('getReferencedColumnName')->willReturn('id');

        $propertiesIndexed = $this->comparator->mergeColumnDefinition([], 'user_id', $existingRelation, 'posts');

        // Second relation with different FK requirement
        $incomingRelation = $this->createMock(ReflectionRelation::class);
        $incomingRelation->method('getType')->willReturn('int');
        $incomingRelation->method('isNullable')->willReturn(false);
        $incomingRelation->method('getDefaultValue')->willReturn(null);
        $incomingRelation->method('getLength')->willReturn(null);
        $incomingRelation->method('isForeignKeyRequired')->willReturn(false); // Different FK requirement
        $incomingRelation->method('getReferencedColumnName')->willReturn('uuid'); // Different referenced column

        $result = $this->comparator->mergeColumnDefinition($propertiesIndexed, 'user_id', $incomingRelation, 'posts');

        $this->assertArrayHasKey('user_id', $result);
        $this->assertTrue($result['user_id']['foreignKeyRequired']); // Should be OR'd: true || false = true
        $this->assertEquals('id', $result['user_id']['referencedColumn']); // Should use existing non-null value (?? operator)
    }

    // ===== addMorphToColumns Tests =====

    public function testAddMorphToColumnsCreatesBothColumns(): void
    {
        $propertiesIndexed = [];
        $relation = $this->createMock(ReflectionRelation::class);
        $relation->method('getMorphTypeColumnName')->willReturn('commentable_type');
        $relation->method('getMorphIdColumnName')->willReturn('commentable_id');
        $relation->method('getReferencedColumnName')->willReturn('id');

        $result = $this->comparator->addMorphToColumns($propertiesIndexed, $relation, 'comments');

        $this->assertArrayHasKey('commentable_type', $result);
        $this->assertArrayHasKey('commentable_id', $result);

        // Check type column
        $typeColumn = $result['commentable_type'];
        $this->assertEquals('string', $typeColumn['type']);
        $this->assertFalse($typeColumn['nullable']);
        $this->assertNull($typeColumn['default']);
        $this->assertEquals(255, $typeColumn['length']);
        $this->assertNull($typeColumn['relation']);
        $this->assertFalse($typeColumn['foreignKeyRequired']);
        $this->assertNull($typeColumn['referencedColumn']);

        // Check ID column
        $idColumn = $result['commentable_id'];
        $this->assertEquals('int', $idColumn['type']);
        $this->assertFalse($idColumn['nullable']);
        $this->assertNull($idColumn['default']);
        $this->assertNull($idColumn['length']);
        $this->assertEquals($relation, $idColumn['relation']);
        $this->assertFalse($idColumn['foreignKeyRequired']);
        $this->assertEquals('id', $idColumn['referencedColumn']);
    }

    public function testAddMorphToColumnsMergesWithExistingCompatibleTypeColumn(): void
    {
        $relation = $this->createMock(ReflectionRelation::class);
        $relation->method('getMorphTypeColumnName')->willReturn('taggable_type');
        $relation->method('getMorphIdColumnName')->willReturn('taggable_id');
        $relation->method('getReferencedColumnName')->willReturn('id');

        // Start with existing compatible type column
        $propertiesIndexed = [
            'taggable_type' => [
                'type' => 'string',
                'nullable' => false,
                'default' => null,
                'length' => 255,
                'relation' => null,
                'foreignKeyRequired' => false,
                'referencedColumn' => null,
                'generatorType' => null,
                'sequence' => null,
                'isPrimaryKey' => false,
                'isAutoIncrement' => false,
            ],
        ];

        $result = $this->comparator->addMorphToColumns($propertiesIndexed, $relation, 'tags');

        $this->assertArrayHasKey('taggable_type', $result);
        $this->assertArrayHasKey('taggable_id', $result);

        // Type column should remain unchanged
        $typeColumn = $result['taggable_type'];
        $this->assertEquals('string', $typeColumn['type']);
        $this->assertFalse($typeColumn['nullable']);
        $this->assertEquals(255, $typeColumn['length']);
    }

    public function testAddMorphToColumnsThrowsOnTypeColumnConflict(): void
    {
        $relation = $this->createMock(ReflectionRelation::class);
        $relation->method('getMorphTypeColumnName')->willReturn('taggable_type');
        $relation->method('getMorphIdColumnName')->willReturn('taggable_id');

        // Start with conflicting type column
        $propertiesIndexed = [
            'taggable_type' => [
                'type' => 'int', // Wrong type
                'nullable' => false,
                'default' => null,
                'length' => 255,
                'relation' => null,
                'foreignKeyRequired' => false,
                'referencedColumn' => null,
                'generatorType' => null,
                'sequence' => null,
                'isPrimaryKey' => false,
                'isAutoIncrement' => false,
            ],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Morph type column "taggable_type" conflicts on table "tags"');

        $this->comparator->addMorphToColumns($propertiesIndexed, $relation, 'tags');
    }

    public function testAddMorphToColumnsThrowsOnIdColumnConflict(): void
    {
        $relation = $this->createMock(ReflectionRelation::class);
        $relation->method('getMorphTypeColumnName')->willReturn('taggable_type');
        $relation->method('getMorphIdColumnName')->willReturn('taggable_id');

        // Start with conflicting ID column
        $propertiesIndexed = [
            'taggable_id' => [
                'type' => 'string', // Wrong type
                'nullable' => false,
                'default' => null,
                'length' => null,
                'relation' => null,
                'foreignKeyRequired' => false,
                'referencedColumn' => null,
                'generatorType' => null,
                'sequence' => null,
                'isPrimaryKey' => false,
                'isAutoIncrement' => false,
            ],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Morph ID column "taggable_id" conflicts on table "tags"');

        $this->comparator->addMorphToColumns($propertiesIndexed, $relation, 'tags');
    }

    public function testAddMorphToColumnsMergesNullableIdColumn(): void
    {
        $relation = $this->createMock(ReflectionRelation::class);
        $relation->method('getMorphTypeColumnName')->willReturn('taggable_type');
        $relation->method('getMorphIdColumnName')->willReturn('taggable_id');
        $relation->method('getReferencedColumnName')->willReturn('id');

        // Start with nullable ID column
        $propertiesIndexed = [
            'taggable_id' => [
                'type' => 'int',
                'nullable' => true, // Existing is nullable
                'default' => null,
                'length' => null,
                'relation' => null,
                'foreignKeyRequired' => false,
                'referencedColumn' => null,
                'generatorType' => null,
                'sequence' => null,
                'isPrimaryKey' => false,
                'isAutoIncrement' => false,
            ],
        ];

        $result = $this->comparator->addMorphToColumns($propertiesIndexed, $relation, 'tags');

        // ID column should be merged, but morph relations are not nullable
        $idColumn = $result['taggable_id'];
        $this->assertEquals('int', $idColumn['type']);
        $this->assertFalse($idColumn['nullable']); // Morph relations are not nullable: true && false = false
        $this->assertEquals($relation, $idColumn['relation']);
    }

    public function testAddMorphToColumnsMergesIdColumnWithAllExistingProperties(): void
    {
        $relation = $this->createMock(ReflectionRelation::class);
        $relation->method('getMorphTypeColumnName')->willReturn('commentable_type');
        $relation->method('getMorphIdColumnName')->willReturn('commentable_id');
        $relation->method('getReferencedColumnName')->willReturn('id');

        // Start with ID column that has all properties set
        $propertiesIndexed = [
            'commentable_id' => [
                'type' => 'int',
                'nullable' => true,
                'default' => '0',
                'length' => 11,
                'relation' => null, // Will be overridden by morph relation
                'foreignKeyRequired' => true, // Will be OR'd
                'referencedColumn' => 'uuid', // Will be overridden by morph relation
                'generatorType' => 'auto',
                'sequence' => 'comment_seq',
                'isPrimaryKey' => true,
                'isAutoIncrement' => true,
            ],
        ];

        $result = $this->comparator->addMorphToColumns($propertiesIndexed, $relation, 'comments');

        $idColumn = $result['commentable_id'];
        $this->assertEquals('int', $idColumn['type']); // Must stay int
        $this->assertFalse($idColumn['nullable']); // Morph relation is not nullable, so false
        $this->assertEquals('0', $idColumn['default']); // Existing default preserved (?? operator)
        $this->assertEquals(11, $idColumn['length']); // Existing length preserved (?? operator)
        $this->assertEquals($relation, $idColumn['relation']); // Morph relation takes precedence
        $this->assertTrue($idColumn['foreignKeyRequired']); // true || false = true
        $this->assertEquals('id', $idColumn['referencedColumn']); // Morph relation takes precedence
        $this->assertEquals('auto', $idColumn['generatorType']); // Existing preserved
        $this->assertEquals('comment_seq', $idColumn['sequence']); // Existing preserved
        $this->assertTrue($idColumn['isPrimaryKey']); // Existing preserved
        $this->assertTrue($idColumn['isAutoIncrement']); // Existing preserved
    }

    public function testAddMorphToColumnsMergesIdColumnWithNullExistingProperties(): void
    {
        $relation = $this->createMock(ReflectionRelation::class);
        $relation->method('getMorphTypeColumnName')->willReturn('commentable_type');
        $relation->method('getMorphIdColumnName')->willReturn('commentable_id');
        $relation->method('getReferencedColumnName')->willReturn('id');

        // Start with ID column that has null properties
        $propertiesIndexed = [
            'commentable_id' => [
                'type' => 'int',
                'nullable' => false,
                'default' => null,
                'length' => null,
                'relation' => null,
                'foreignKeyRequired' => false,
                'referencedColumn' => null,
                'generatorType' => null,
                'sequence' => null,
                'isPrimaryKey' => false,
                'isAutoIncrement' => false,
            ],
        ];

        $result = $this->comparator->addMorphToColumns($propertiesIndexed, $relation, 'comments');

        $idColumn = $result['commentable_id'];
        $this->assertEquals('int', $idColumn['type']);
        $this->assertFalse($idColumn['nullable']); // false && false = false
        $this->assertNull($idColumn['default']); // null ?? null = null
        $this->assertNull($idColumn['length']); // null ?? null = null
        $this->assertEquals($relation, $idColumn['relation']); // relation ?? null = relation
        $this->assertFalse($idColumn['foreignKeyRequired']); // false || false = false
        $this->assertEquals('id', $idColumn['referencedColumn']); // 'id' ?? null = 'id'
        $this->assertNull($idColumn['generatorType']); // null ?? null = null
        $this->assertNull($idColumn['sequence']); // null ?? null = null
        $this->assertFalse($idColumn['isPrimaryKey']); // false ?? false = false
        $this->assertFalse($idColumn['isAutoIncrement']); // false ?? false = false
    }

    // ===== normalizeTypeName Tests =====

    public function testNormalizeTypeNameWithNull(): void
    {
        $reflection = new ReflectionClass($this->comparator);
        $method = $reflection->getMethod('normalizeTypeName');
        $method->setAccessible(true);

        $result = $method->invoke($this->comparator, null);
        $this->assertNull($result);
    }

    public function testNormalizeTypeNameWithString(): void
    {
        $reflection = new ReflectionClass($this->comparator);
        $method = $reflection->getMethod('normalizeTypeName');
        $method->setAccessible(true);

        $result = $method->invoke($this->comparator, 'string');
        $this->assertEquals('string', $result);

        $result = $method->invoke($this->comparator, '?int');
        $this->assertEquals('int', $result);

        $result = $method->invoke($this->comparator, 'string|null');
        $this->assertEquals('string', $result);
    }

    public function testNormalizeTypeNameWithReflectionNamedType(): void
    {
        $reflection = new ReflectionClass($this->comparator);
        $method = $reflection->getMethod('normalizeTypeName');
        $method->setAccessible(true);

        $namedType = $this->createMock(\ReflectionNamedType::class);
        $namedType->method('getName')->willReturn('DateTime');

        $result = $method->invoke($this->comparator, $namedType);
        $this->assertEquals('DateTime', $result);
    }

    public function testNormalizeTypeNameWithReflectionUnionType(): void
    {
        $reflection = new ReflectionClass($this->comparator);
        $method = $reflection->getMethod('normalizeTypeName');
        $method->setAccessible(true);

        $stringType = $this->createMock(\ReflectionNamedType::class);
        $stringType->method('getName')->willReturn('string');

        $nullType = $this->createMock(\ReflectionNamedType::class);
        $nullType->method('getName')->willReturn('null');

        $unionType = $this->createMock(\ReflectionUnionType::class);
        $unionType->method('getTypes')->willReturn([$stringType, $nullType]);

        $result = $method->invoke($this->comparator, $unionType);
        $this->assertEquals('string', $result);
    }

    public function testNormalizeTypeNameWithReflectionUnionTypeAllNull(): void
    {
        $reflection = new ReflectionClass($this->comparator);
        $method = $reflection->getMethod('normalizeTypeName');
        $method->setAccessible(true);

        $nullType1 = $this->createMock(\ReflectionNamedType::class);
        $nullType1->method('getName')->willReturn('null');

        $nullType2 = $this->createMock(\ReflectionNamedType::class);
        $nullType2->method('getName')->willReturn('null');

        $unionType = $this->createMock(\ReflectionUnionType::class);
        $unionType->method('getTypes')->willReturn([$nullType1, $nullType2]);

        $result = $method->invoke($this->comparator, $unionType);
        $this->assertNull($result);
    }

    public function testNormalizeTypeNameWithOtherType(): void
    {
        $reflection = new ReflectionClass($this->comparator);
        $method = $reflection->getMethod('normalizeTypeName');
        $method->setAccessible(true);

        $result = $method->invoke($this->comparator, 123); // Some other type
        $this->assertEquals('123', $result); // This should use string casting for non-string types
    }

    // Additional targeted tests to ensure specific lines are covered

    public function testMergeColumnDefinitionThrowsWhenRelationConflictsWithScalar(): void
    {
        $existingProperty = $this->createMock(ReflectionProperty::class);
        $existingProperty->method('getType')->willReturn('int');
        $existingProperty->method('isNullable')->willReturn(false);
        $existingProperty->method('getDefaultValue')->willReturn(null);
        $existingProperty->method('getLength')->willReturn(null);

        $propertiesIndexed = $this->comparator->mergeColumnDefinition([], 'user_id', $existingProperty, 'posts');

        $relationProperty = $this->createMock(ReflectionRelation::class);
        $relationProperty->method('getType')->willReturn('int');
        $relationProperty->method('isNullable')->willReturn(false);
        $relationProperty->method('getDefaultValue')->willReturn(null);
        $relationProperty->method('getLength')->willReturn(null);
        $relationProperty->method('isForeignKeyRequired')->willReturn(true); // This makes it a relation - different from existing

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Column "user_id" on table "posts" conflicts between relation and scalar definitions');

        $this->comparator->mergeColumnDefinition($propertiesIndexed, 'user_id', $relationProperty, 'posts');
    }

    public function testMergeColumnDefinitionThrowsWhenRelationsPointToDifferentTargets(): void
    {
        $existingRelation = $this->createMock(ReflectionRelation::class);
        $existingRelation->method('getType')->willReturn('int');
        $existingRelation->method('isNullable')->willReturn(false);
        $existingRelation->method('getDefaultValue')->willReturn(null);
        $existingRelation->method('getLength')->willReturn(null);
        $existingRelation->method('isForeignKeyRequired')->willReturn(true);
        $existingRelation->method('getReferencedColumnName')->willReturn('id');
        $existingRelation->method('getTargetEntity')->willReturn('Articulate\\Tests\\Modules\\DatabaseSchemaComparator\\TestEntities\\TestEntity');

        $propertiesIndexed = $this->comparator->mergeColumnDefinition([], 'user_id', $existingRelation, 'posts');

        $conflictingRelation = $this->createMock(ReflectionRelation::class);
        $conflictingRelation->method('getType')->willReturn('int');
        $conflictingRelation->method('isNullable')->willReturn(false);
        $conflictingRelation->method('getDefaultValue')->willReturn(null);
        $conflictingRelation->method('getLength')->willReturn(null);
        $conflictingRelation->method('isForeignKeyRequired')->willReturn(true);
        $conflictingRelation->method('getReferencedColumnName')->willReturn('uuid'); // Different referenced column
        $conflictingRelation->method('getTargetEntity')->willReturn('Articulate\\Tests\\Modules\\DatabaseSchemaComparator\\TestEntities\\TestEntity'); // Same target, different column

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Relation column "user_id" on table "posts" points to different targets');

        $this->comparator->mergeColumnDefinition($propertiesIndexed, 'user_id', $conflictingRelation, 'posts');
    }

    public function testAddMorphToColumnsMergesExistingCompatibleTypeColumn(): void
    {
        $relation = $this->createMock(ReflectionRelation::class);
        $relation->method('getMorphTypeColumnName')->willReturn('taggable_type');
        $relation->method('getMorphIdColumnName')->willReturn('taggable_id');
        $relation->method('getReferencedColumnName')->willReturn('id');

        // Start with existing compatible type column
        $propertiesIndexed = [
            'taggable_type' => [
                'type' => 'string',
                'nullable' => false,
                'default' => null,
                'length' => 255,
                'relation' => null,
                'foreignKeyRequired' => false,
                'referencedColumn' => null,
                'generatorType' => null,
                'sequence' => null,
                'isPrimaryKey' => false,
                'isAutoIncrement' => false,
            ],
        ];

        $result = $this->comparator->addMorphToColumns($propertiesIndexed, $relation, 'tags');

        // Verify merge happened correctly
        $this->assertArrayHasKey('taggable_type', $result);
        $typeColumn = $result['taggable_type'];
        $this->assertEquals('string', $typeColumn['type']); // Existing type preserved
        $this->assertEquals(255, $typeColumn['length']); // Existing length preserved
    }

    public function testAddMorphToColumnsMergesExistingCompatibleIdColumn(): void
    {
        $relation = $this->createMock(ReflectionRelation::class);
        $relation->method('getMorphTypeColumnName')->willReturn('commentable_type');
        $relation->method('getMorphIdColumnName')->willReturn('commentable_id');
        $relation->method('getReferencedColumnName')->willReturn('id');

        // Start with existing compatible ID column
        $propertiesIndexed = [
            'commentable_id' => [
                'type' => 'int',
                'nullable' => true, // Will be merged with morph requirement
                'default' => null,
                'length' => null,
                'relation' => null,
                'foreignKeyRequired' => false,
                'referencedColumn' => null,
                'generatorType' => null,
                'sequence' => null,
                'isPrimaryKey' => false,
                'isAutoIncrement' => false,
            ],
        ];

        $result = $this->comparator->addMorphToColumns($propertiesIndexed, $relation, 'comments');

        // Verify merge happened correctly
        $this->assertArrayHasKey('commentable_id', $result);
        $idColumn = $result['commentable_id'];
        $this->assertEquals('int', $idColumn['type']); // Type must stay int
        $this->assertFalse($idColumn['nullable']); // Morph relation overrides to false
        $this->assertEquals($relation, $idColumn['relation']); // Morph relation assigned
    }

    // Additional test for edge case: ensure all merge paths are covered

    public function testMergeColumnDefinitionCoversAllNullCoalescing(): void
    {
        // Test case where existing has null values and incoming has non-null values
        $existingRelation = $this->createMock(ReflectionRelation::class);
        $existingRelation->method('getType')->willReturn('int');
        $existingRelation->method('isNullable')->willReturn(false);
        $existingRelation->method('getDefaultValue')->willReturn(null);
        $existingRelation->method('getLength')->willReturn(null);
        $existingRelation->method('isForeignKeyRequired')->willReturn(true);
        $existingRelation->method('getReferencedColumnName')->willReturn('id');
        $existingRelation->method('getTargetEntity')->willReturn('Articulate\\Tests\\Modules\\DatabaseSchemaComparator\\TestEntities\\TestEntity');

        $propertiesIndexed = $this->comparator->mergeColumnDefinition([], 'user_id', $existingRelation, 'posts');

        // Second relation with null values that should be overridden by existing non-null values
        $incomingRelation = $this->createMock(ReflectionRelation::class);
        $incomingRelation->method('getType')->willReturn('int');
        $incomingRelation->method('isNullable')->willReturn(false);
        $incomingRelation->method('getDefaultValue')->willReturn(null);
        $incomingRelation->method('getLength')->willReturn(null);
        $incomingRelation->method('isForeignKeyRequired')->willReturn(false); // Different, will be OR'd
        $incomingRelation->method('getReferencedColumnName')->willReturn('id'); // Same, no conflict
        $incomingRelation->method('getTargetEntity')->willReturn('Articulate\\Tests\\Modules\\DatabaseSchemaComparator\\TestEntities\\TestEntity'); // Same, no conflict

        $result = $this->comparator->mergeColumnDefinition($propertiesIndexed, 'user_id', $incomingRelation, 'posts');

        // Verify null coalescing worked correctly
        $this->assertTrue($result['user_id']['foreignKeyRequired']); // true || false = true
        $this->assertEquals('id', $result['user_id']['referencedColumn']); // 'id' ?? null = 'id'
        $this->assertEquals($existingRelation, $result['user_id']['relation']); // existing ?? incoming = existing
    }
}
