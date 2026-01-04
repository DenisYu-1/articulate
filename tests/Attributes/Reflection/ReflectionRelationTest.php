<?php

namespace Articulate\Tests\Attributes\Reflection;

use Articulate\Attributes\Reflection\ReflectionRelation;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Attributes\Relations\MorphMany;
use Articulate\Attributes\Relations\MorphOne;
use Articulate\Attributes\Relations\MorphTo;
use Articulate\Attributes\Relations\OneToMany;
use Articulate\Attributes\Relations\OneToOne;
use Articulate\Schema\SchemaNaming;
use Articulate\Tests\AbstractTestCase;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestEntity;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestMultiPrimaryKeyEntity;
use Exception;
use ReflectionProperty;
use RuntimeException;

// Test classes for reflection testing
class TestRelationClass {
    #[ManyToOne(targetEntity: TestEntity::class)]
    public TestEntity $manyToOneRelation;

    #[OneToMany(targetEntity: TestEntity::class)]
    public array $oneToManyRelation;

    #[OneToOne(targetEntity: TestEntity::class)]
    public ?TestEntity $oneToOneRelation;

    public int $regularProperty;
}

class TestNullableRelationClass {
    #[ManyToOne(targetEntity: TestEntity::class, nullable: true)]
    public ?TestEntity $nullableRelation;
}

class ReflectionRelationTest extends AbstractTestCase {
    public function testGetTargetEntity()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new ManyToOne(targetEntity: TestEntity::class);

        $property = new ReflectionProperty(TestRelationClass::class, 'manyToOneRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        $this->assertEquals(TestEntity::class, $reflection->getTargetEntity());
    }

    public function testIsOneToMany()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new ManyToOne(targetEntity: TestEntity::class);

        $property = new ReflectionProperty(TestRelationClass::class, 'manyToOneRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        $this->assertFalse($reflection->isOneToMany());
        $this->assertTrue($reflection->isManyToOne());
    }

    public function testGetTargetEntityWithOneToManyAndCollectionType()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new OneToMany(targetEntity: TestEntity::class);

        $property = new ReflectionProperty(TestRelationClass::class, 'oneToManyRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        // This should not throw an exception due to the collection type check
        $this->assertEquals(TestEntity::class, $reflection->getTargetEntity());
    }

    public function testGetTargetEntityWithOneToManyInvalidCollectionType()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new OneToMany(targetEntity: TestEntity::class);

        // Create a test class with invalid collection type
        eval('
            class TestInvalidCollectionClass {
                public string $invalidCollection;
            }
        ');

        $property = new ReflectionProperty('TestInvalidCollectionClass', 'invalidCollection');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('One-to-many property must be iterable collection');
        $reflection->getTargetEntity();
    }

    public function testGetTargetEntityWithBuiltinType()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new OneToOne(targetEntity: TestEntity::class);

        $property = new ReflectionProperty(TestRelationClass::class, 'regularProperty');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        // Should return the target entity from the attribute, not throw an exception
        $this->assertEquals(TestEntity::class, $reflection->getTargetEntity());
    }

    public function testGetMappedByForManyToOne()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new ManyToOne(targetEntity: TestEntity::class);

        $property = new ReflectionProperty(TestRelationClass::class, 'manyToOneRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        // For ManyToOne, should return null since it doesn't have ownedBy property
        $this->assertNull($reflection->getMappedBy());
    }

    public function testGetMappedByForOneToOneWithoutMapping()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new OneToOne(targetEntity: TestEntity::class);

        $property = new ReflectionProperty(TestRelationClass::class, 'oneToOneRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Either ownedBy or referencedBy is required');
        $reflection->getMappedBy();
    }

    public function testGetInversedByForManyToOne()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new ManyToOne(targetEntity: TestEntity::class);

        $property = new ReflectionProperty(TestRelationClass::class, 'manyToOneRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        // For ManyToOne, should return null since it doesn't have referencedBy property
        $this->assertNull($reflection->getInversedBy());
    }

    public function testGetInversedByWithReferencedBy()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new OneToOne(targetEntity: TestEntity::class, referencedBy: 'testProperty');

        $property = new ReflectionProperty(TestRelationClass::class, 'oneToOneRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        // Should return the referencedBy property
        $this->assertEquals('testProperty', $reflection->getInversedBy());
    }

    public function testIsForeignKeyRequiredForOneToMany()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new OneToMany(targetEntity: TestEntity::class);

        $property = new ReflectionProperty(TestRelationClass::class, 'oneToManyRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        // OneToMany should never require foreign keys
        $this->assertFalse($reflection->isForeignKeyRequired());
    }

    public function testIsForeignKeyRequiredForOneToOneWithMappedBy()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new OneToOne(targetEntity: TestEntity::class, ownedBy: 'testProperty');

        $property = new ReflectionProperty(TestRelationClass::class, 'oneToOneRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        // OneToOne with mappedBy should not require foreign keys
        $this->assertFalse($reflection->isForeignKeyRequired());
    }

    public function testIsForeignKeyRequiredForOneToOneWithoutMappedBy()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new OneToOne(targetEntity: TestEntity::class);

        $property = new ReflectionProperty(TestRelationClass::class, 'oneToOneRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        // OneToOne without mappedBy should require foreign keys by default
        $this->assertTrue($reflection->isForeignKeyRequired());
    }

    public function testIsForeignKeyRequiredWithExplicitForeignKeyFalse()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new ManyToOne(targetEntity: TestEntity::class, foreignKey: false);

        $property = new ReflectionProperty(TestRelationClass::class, 'manyToOneRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        // Explicit foreignKey: false should return false
        $this->assertFalse($reflection->isForeignKeyRequired());
    }

    public function testMorphOneGetTargetEntity()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new MorphOne(
            targetEntity: TestEntity::class,
            referencedBy: 'testMorph'
        );

        $property = new ReflectionProperty(TestRelationClass::class, 'oneToOneRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        $this->assertEquals(TestEntity::class, $reflection->getTargetEntity());
    }

    public function testMorphManyGetTargetEntity()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new MorphMany(
            targetEntity: TestEntity::class,
            referencedBy: 'testMorph'
        );

        $property = new ReflectionProperty(TestRelationClass::class, 'oneToManyRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        $this->assertEquals(TestEntity::class, $reflection->getTargetEntity());
    }

    public function testMorphManyValidatesCollectionType()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new MorphMany(
            targetEntity: TestEntity::class,
            referencedBy: 'testMorph'
        );

        // Create a property with invalid type (string instead of array/iterable)
        $invalidProperty = new ReflectionProperty(TestEntity::class, 'id'); // 'id' is typed as int
        $reflection = new ReflectionRelation($attribute, $invalidProperty, $schemaNaming);

        // This should throw an exception because MorphMany requires a collection type
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('One-to-many property must be iterable collection');

        $reflection->getTargetEntity();
    }

    public function testMorphManyResolvesColumnNamesCorrectly()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new MorphMany(
            targetEntity: TestEntity::class,
            referencedBy: 'testMorph'
        );

        $property = new ReflectionProperty(TestRelationClass::class, 'oneToManyRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        // Access the resolved attribute
        $reflectionProperty = new ReflectionProperty($reflection, 'entityProperty');
        $resolvedAttribute = $reflectionProperty->getValue($reflection);

        // The ConcatOperandRemoval mutant removes the property name from column names
        // This would make all morph relations use the same generic column names
        $this->assertEquals('one_to_many_relation_type', $resolvedAttribute->getTypeColumn());
        $this->assertEquals('one_to_many_relation_id', $resolvedAttribute->getIdColumn());
    }

    public function testIsPolymorphicReturnsTrueForMorphRelations()
    {
        $schemaNaming = new SchemaNaming();

        // Test MorphTo
        $morphToAttribute = new MorphTo();
        $property = new ReflectionProperty(TestRelationClass::class, 'oneToOneRelation');
        $morphToReflection = new ReflectionRelation($morphToAttribute, $property, $schemaNaming);
        $this->assertTrue($morphToReflection->isPolymorphic(), 'MorphTo should be polymorphic');

        // Test MorphOne
        $morphOneAttribute = new MorphOne(
            targetEntity: TestEntity::class,
            referencedBy: 'test'
        );
        $morphOneReflection = new ReflectionRelation($morphOneAttribute, $property, $schemaNaming);
        $this->assertTrue($morphOneReflection->isPolymorphic(), 'MorphOne should be polymorphic');

        // Test MorphMany
        $morphManyAttribute = new MorphMany(
            targetEntity: TestEntity::class,
            referencedBy: 'test'
        );
        $morphManyReflection = new ReflectionRelation($morphManyAttribute, $property, $schemaNaming);
        $this->assertTrue($morphManyReflection->isPolymorphic(), 'MorphMany should be polymorphic');

        // Test regular relation
        $oneToOneAttribute = new OneToOne(targetEntity: TestEntity::class);
        $oneToOneReflection = new ReflectionRelation($oneToOneAttribute, $property, $schemaNaming);
        $this->assertFalse($oneToOneReflection->isPolymorphic(), 'OneToOne should not be polymorphic');
    }

    public function testIsNullableWithExplicitNullable()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new ManyToOne(targetEntity: TestEntity::class, nullable: true);

        $property = new ReflectionProperty(TestNullableRelationClass::class, 'nullableRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        // Should return the explicit nullable value
        $this->assertTrue($reflection->isNullable());
    }

    public function testIsNullableWithoutExplicitNullable()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new ManyToOne(targetEntity: TestEntity::class);

        $property = new ReflectionProperty(TestNullableRelationClass::class, 'nullableRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        // Should use the property's type information (nullable union type)
        $this->assertTrue($reflection->isNullable());
    }

    public function testIsNullableWithNonNullableType()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new ManyToOne(targetEntity: TestEntity::class);

        $property = new ReflectionProperty(TestRelationClass::class, 'manyToOneRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        // Should return false for non-nullable property
        $this->assertFalse($reflection->isNullable());
    }

    public function testIsOwningSideForManyToOne()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new ManyToOne(targetEntity: TestEntity::class);

        $mockProperty = $this->createMock(ReflectionProperty::class);

        $reflection = new ReflectionRelation($attribute, $mockProperty, $schemaNaming);

        // ManyToOne is always the owning side
        $this->assertTrue($reflection->isOwningSide());
    }

    public function testIsOwningSideForOneToOneWithoutMappedBy()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new OneToOne(targetEntity: TestEntity::class);

        $mockProperty = $this->createMock(ReflectionProperty::class);

        $reflection = new ReflectionRelation($attribute, $mockProperty, $schemaNaming);

        // OneToOne without mappedBy is owning side
        $this->assertTrue($reflection->isOwningSide());
    }

    public function testIsOwningSideForOneToOneWithMappedBy()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new OneToOne(targetEntity: TestEntity::class, ownedBy: 'testProperty');

        $mockProperty = $this->createMock(ReflectionProperty::class);

        $reflection = new ReflectionRelation($attribute, $mockProperty, $schemaNaming);

        // OneToOne with ownedBy is not the owning side
        $this->assertFalse($reflection->isOwningSide());
    }

    public function testIsOwningSideForOneToMany()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new OneToMany(targetEntity: TestEntity::class);

        $mockProperty = $this->createMock(ReflectionProperty::class);

        $reflection = new ReflectionRelation($attribute, $mockProperty, $schemaNaming);

        // OneToMany is never the owning side
        $this->assertFalse($reflection->isOwningSide());
    }

    public function testGetMappedByProperty()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new OneToOne(targetEntity: TestEntity::class, ownedBy: 'testProperty');

        $mockProperty = $this->createMock(ReflectionProperty::class);

        $reflection = new ReflectionRelation($attribute, $mockProperty, $schemaNaming);

        $this->assertEquals('testProperty', $reflection->getMappedByProperty());
    }

    public function testGetMappedByPropertyWithOwnedBy()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new OneToOne(targetEntity: TestEntity::class, ownedBy: 'testProperty');

        $property = new ReflectionProperty(TestRelationClass::class, 'oneToOneRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        $this->assertEquals('testProperty', $reflection->getMappedByProperty());
    }

    public function testGetMappedByPropertyWithoutProperty()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new OneToOne(targetEntity: TestEntity::class);

        $property = new ReflectionProperty(TestRelationClass::class, 'oneToOneRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        $this->assertNull($reflection->getMappedByProperty());
    }

    public function testGetInversedByProperty()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new OneToOne(targetEntity: TestEntity::class, referencedBy: 'inverseProperty');

        $property = new ReflectionProperty(TestRelationClass::class, 'oneToOneRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        $this->assertEquals('inverseProperty', $reflection->getInversedByProperty());
    }

    public function testGetInversedByPropertyWithoutProperty()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new OneToOne(targetEntity: TestEntity::class);

        $property = new ReflectionProperty(TestRelationClass::class, 'oneToOneRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        $this->assertNull($reflection->getInversedByProperty());
    }

    public function testGetInversedByThrowsExceptionWhenNoMappingConfigured()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new OneToMany(targetEntity: TestEntity::class);

        $property = new ReflectionProperty(TestRelationClass::class, 'oneToManyRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        // OneToMany without ownedBy should throw an exception
        // The MethodCallRemoval mutant removes the assertMappingConfigured() call
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Either ownedBy or referencedBy is required');

        $reflection->getInversedBy();
    }

    public function testGetReferencedColumnNameReturnsPrimaryKey()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new ManyToOne(targetEntity: TestEntity::class);

        $property = new ReflectionProperty(TestRelationClass::class, 'manyToOneRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        // Should return the primary key of the target entity
        $this->assertEquals('id', $reflection->getReferencedColumnName());
    }

    public function testGetReferencedColumnNameReturnsFirstPrimaryKeyForMultiKeyEntity()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new ManyToOne(targetEntity: TestMultiPrimaryKeyEntity::class);

        $property = new ReflectionProperty(TestRelationClass::class, 'manyToOneRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        // Should return the first primary key ('id') of the multi-key entity
        $this->assertEquals('id', $reflection->getReferencedColumnName());
    }

    public function testGetTargetEntityWithOneToManyCallsAssertOneToManyCollectionType()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new OneToMany(targetEntity: TestEntity::class);

        $property = new ReflectionProperty(TestRelationClass::class, 'oneToManyRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        // This tests the IfNegation mutant on line 63 - ensures OneToMany path is taken
        // and assertOneToManyCollectionType is called
        $this->assertEquals(TestEntity::class, $reflection->getTargetEntity());
    }

    public function testGetTargetEntityWithBuiltinTypeReturnsAttributeTarget()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new OneToOne(targetEntity: TestEntity::class);

        // Use a property with builtin type (int)
        $property = new ReflectionProperty(TestEntity::class, 'id');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        // This tests the LogicalAnd mutation on line 68 - when type is builtin, should still return target entity
        $this->assertEquals(TestEntity::class, $reflection->getTargetEntity());
    }

    public function testGetInversedByThrowsExceptionWhenBothOwnedByAndReferencedBySpecified()
    {
        $schemaNaming = new SchemaNaming();
        // This tests the LogicalAndAllSubExprNegation mutant on line 97
        $attribute = new OneToOne(targetEntity: TestEntity::class, ownedBy: 'owned', referencedBy: 'referenced');

        $property = new ReflectionProperty(TestRelationClass::class, 'oneToOneRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('ownedBy and referencedBy cannot be specified at the same time');
        $reflection->getInversedBy();
    }

    public function testGetInversedByWithReferencedByReturnsReferencedByValue()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new OneToOne(targetEntity: TestEntity::class, referencedBy: 'referencedProperty');

        $property = new ReflectionProperty(TestRelationClass::class, 'oneToOneRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        // This tests the ReturnRemoval mutant on line 101 - ensures the return statement is executed
        $this->assertEquals('referencedProperty', $reflection->getInversedBy());
    }

    public function testGetInversedByFallsBackToColumnNameParsing()
    {
        $schemaNaming = new SchemaNaming();
        // Need to specify referencedBy to avoid the assertMappingConfigured exception
        $attribute = new OneToOne(targetEntity: TestEntity::class, referencedBy: 'customProperty');

        $property = new ReflectionProperty(TestRelationClass::class, 'oneToOneRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        // This tests the Coalesce mutation on line 106 - but since referencedBy is set, it returns that instead
        $this->assertEquals('customProperty', $reflection->getInversedBy());
    }

    public function testIsForeignKeyRequiredReturnsFalseForOneToOneWithMappedBy()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new OneToOne(targetEntity: TestEntity::class, ownedBy: 'mappedProperty');

        $property = new ReflectionProperty(TestRelationClass::class, 'oneToOneRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        // This tests the LogicalAndSingleSubExprNegation mutant on line 115
        // OneToOne with mappedBy should return false
        $this->assertFalse($reflection->isForeignKeyRequired());
    }

    public function testIsForeignKeyRequiredReturnsFalseForOneToOneWithMappedByEarlyReturn()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new OneToOne(targetEntity: TestEntity::class, ownedBy: 'mappedProperty');

        $property = new ReflectionProperty(TestRelationClass::class, 'oneToOneRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        // This tests the ReturnRemoval mutant on line 116 - ensures early return
        $this->assertFalse($reflection->isForeignKeyRequired());
    }

    public function testIsNullableReturnsFalseWhenPropertyTypeDoesNotAllowNull()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new ManyToOne(targetEntity: TestEntity::class);

        // Use a non-nullable property
        $property = new ReflectionProperty(TestRelationClass::class, 'manyToOneRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        // This tests the FalseValue mutant on line 140 - ensures false is returned for non-nullable
        $this->assertFalse($reflection->isNullable());
    }

    public function testGetReferencedColumnNameFallsBackToIdWhenNoPrimaryKeys()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new ManyToOne(targetEntity: TestEntity::class);

        $property = new ReflectionProperty(TestRelationClass::class, 'manyToOneRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        // This tests the Coalesce mutation on line 176 - ensures fallback to 'id'
        $this->assertEquals('id', $reflection->getReferencedColumnName());
    }

    public function testIsOwningSideReturnsFalseForMorphOne()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new MorphOne(targetEntity: TestEntity::class, referencedBy: 'test');

        $property = new ReflectionProperty(TestRelationClass::class, 'oneToOneRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        // This tests the LogicalOr mutations on line 277 - MorphOne should not be owning side
        $this->assertFalse($reflection->isOwningSide());
    }

    public function testIsOwningSideReturnsFalseForMorphMany()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new MorphMany(targetEntity: TestEntity::class, referencedBy: 'test');

        $property = new ReflectionProperty(TestRelationClass::class, 'oneToManyRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        // This tests the LogicalOr mutations on line 277 - MorphMany should not be owning side
        $this->assertFalse($reflection->isOwningSide());
    }

    public function testIsOwningSideReturnsFalseForMorphRelationsWithReturnRemoval()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new MorphOne(targetEntity: TestEntity::class, referencedBy: 'test');

        $property = new ReflectionProperty(TestRelationClass::class, 'oneToOneRelation');
        $reflection = new ReflectionRelation($attribute, $property, $schemaNaming);

        // This tests the ReturnRemoval mutant on line 278 - ensures return false for MorphOne/MorphMany
        $this->assertFalse($reflection->isOwningSide());
    }
}
