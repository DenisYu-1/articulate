<?php

namespace Articulate\Tests\Attributes\Reflection;

use Articulate\Attributes\Reflection\ReflectionRelation;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Attributes\Relations\OneToMany;
use Articulate\Attributes\Relations\OneToOne;
use Articulate\Schema\SchemaNaming;
use Articulate\Tests\AbstractTestCase;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestEntity;
use ReflectionProperty;

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

        $this->expectException(\RuntimeException::class);
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

        $this->expectException(\Exception::class);
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
}
