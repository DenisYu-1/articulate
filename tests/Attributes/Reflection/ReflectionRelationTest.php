<?php

namespace Articulate\Tests\Attributes\Reflection;

use Articulate\Attributes\Reflection\ReflectionRelation;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Attributes\Relations\MorphMany;
use Articulate\Attributes\Relations\MorphOne;
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
class TestRelationClass
{
    #[ManyToOne(targetEntity: TestEntity::class)]
    public TestEntity $manyToOneRelation;

    #[OneToMany(targetEntity: TestEntity::class)]
    public array $oneToManyRelation;

    #[OneToOne(targetEntity: TestEntity::class)]
    public ?TestEntity $oneToOneRelation;

    public int $regularProperty;
}

class TestNullableRelationClass
{
    #[ManyToOne(targetEntity: TestEntity::class, nullable: true)]
    public ?TestEntity $nullableRelation;
}

class ReflectionRelationTest extends AbstractTestCase
{
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
        $morphToAttribute = new \Articulate\Attributes\Relations\MorphTo();
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
}
