<?php

namespace Articulate\Tests\Attributes\Reflection;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionProperty;
use Articulate\Attributes\Reflection\ReflectionRelation;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Attributes\Relations\MorphMany;
use Articulate\Attributes\Relations\MorphOne;
use Articulate\Attributes\Relations\MorphTo;
use Articulate\Attributes\Relations\OneToOne;
use Articulate\Tests\AbstractTestCase;

class ReflectionEntityTest extends AbstractTestCase {
    public function testGetEntityPropertiesWithOneToOneNonOwningSideSkipsRelation(): void
    {
        // This test ensures the continue statement at line 62 is executed
        $entity = new ReflectionEntity(TestEntityWithOneToOneNonOwning::class);

        $properties = iterator_to_array($entity->getEntityProperties());

        // Should only contain the regular property, not the OneToOne relation (which is skipped)
        $this->assertCount(1, $properties);
        $this->assertInstanceOf(ReflectionProperty::class, $properties[0]);
        $this->assertEquals('name', $properties[0]->getFieldName());
    }

    public function testGetEntityPropertiesWithOneToOneOwningSideIncludesRelation(): void
    {
        // This test ensures the yield statement at line 64 is reached
        $entity = new ReflectionEntity(TestEntityWithOneToOneOwning::class);

        $properties = iterator_to_array($entity->getEntityProperties());

        // Should contain both the property and the OneToOne relation
        $this->assertCount(2, $properties);
        $relationFound = false;
        foreach ($properties as $property) {
            if ($property instanceof ReflectionRelation) {
                $relationFound = true;
                $this->assertTrue($property->isOwningSide());
            }
        }
        $this->assertTrue($relationFound, 'OneToOne relation should be included');
    }

    public function testGetEntityPropertiesWithManyToOneYieldsRelation(): void
    {
        // This test ensures the yield statement at line 71-72 is reached
        $entity = new ReflectionEntity(TestEntityWithManyToOne::class);

        $properties = iterator_to_array($entity->getEntityProperties());

        // Should contain both the property and the ManyToOne relation
        $this->assertCount(2, $properties);
        $relationFound = false;
        foreach ($properties as $property) {
            if ($property instanceof ReflectionRelation) {
                $relationFound = true;
            }
        }
        $this->assertTrue($relationFound, 'ManyToOne relation should be included');
    }

    public function testGetEntityPropertiesWithMorphToYieldsRelation(): void
    {
        // This test ensures the yield statement at line 80-81 is reached
        $entity = new ReflectionEntity(TestEntityWithMorphTo::class);

        $properties = iterator_to_array($entity->getEntityProperties());

        // Should contain both the property and the MorphTo relation
        $this->assertCount(2, $properties);
        $relationFound = false;
        foreach ($properties as $property) {
            if ($property instanceof ReflectionRelation) {
                $relationFound = true;
            }
        }
        $this->assertTrue($relationFound, 'MorphTo relation should be included');
    }

    public function testGetEntityPropertiesWithMorphOneNonOwningSideSkipsRelation(): void
    {
        // This test ensures the continue statement at line 91 is executed
        $entity = new ReflectionEntity(TestEntityWithMorphOneNonOwning::class);

        $properties = iterator_to_array($entity->getEntityProperties());

        // Should only contain the regular property, not the MorphOne relation (which is skipped)
        $this->assertCount(1, $properties);
        $this->assertInstanceOf(ReflectionProperty::class, $properties[0]);
        $this->assertEquals('name', $properties[0]->getFieldName());
    }

    public function testGetEntityPropertiesWithMorphOneAlwaysNonOwningSide(): void
    {
        // This test ensures MorphOne relations are never yielded in getEntityProperties (they're inverse sides)
        $entity = new ReflectionEntity(TestEntityWithMorphOneOwning::class);

        $properties = iterator_to_array($entity->getEntityProperties());

        // Should only contain the property, MorphOne is never owning side so not yielded
        $this->assertCount(1, $properties);
        $this->assertInstanceOf(ReflectionProperty::class, $properties[0]);
        $this->assertEquals('name', $properties[0]->getFieldName());
    }

    public function testGetEntityPropertiesWithMorphManyNonOwningSideSkipsRelation(): void
    {
        // This test ensures the continue statement at line 103 is executed
        $entity = new ReflectionEntity(TestEntityWithMorphManyNonOwning::class);

        $properties = iterator_to_array($entity->getEntityProperties());

        // Should only contain the regular property, not the MorphMany relation (which is skipped)
        $this->assertCount(1, $properties);
        $this->assertInstanceOf(ReflectionProperty::class, $properties[0]);
        $this->assertEquals('name', $properties[0]->getFieldName());
    }

    public function testGetEntityPropertiesWithMorphManyAlwaysNonOwningSide(): void
    {
        // This test ensures MorphMany relations are never yielded in getEntityProperties (they're inverse sides)
        $entity = new ReflectionEntity(TestEntityWithMorphManyOwning::class);

        $properties = iterator_to_array($entity->getEntityProperties());

        // Should only contain the property, MorphMany is never owning side so not yielded
        $this->assertCount(1, $properties);
        $this->assertInstanceOf(ReflectionProperty::class, $properties[0]);
        $this->assertEquals('name', $properties[0]->getFieldName());
    }

    public function testGetEntityFieldsPropertiesWithMultipleProperties(): void
    {
        // This test ensures the foreach loop at line 119 is executed multiple times
        $entity = new ReflectionEntity(TestEntityWithMultipleProperties::class);

        $properties = iterator_to_array($entity->getEntityFieldsProperties());

        // Should contain multiple properties
        $this->assertGreaterThan(1, count($properties));
    }

    public function testGetEntityFieldsPropertiesSkipsPropertiesWithoutOneToOne(): void
    {
        // This test ensures the continue statement at line 123 is executed
        $entity = new ReflectionEntity(TestEntityWithOnlyProperty::class);

        $properties = iterator_to_array($entity->getEntityFieldsProperties());

        // Should contain only properties, no relations
        foreach ($properties as $property) {
            $this->assertInstanceOf(ReflectionProperty::class, $property);
        }
    }

    public function testGetEntityFieldsPropertiesIncludesOneToOneRelations(): void
    {
        // This test ensures the foreach loop at line 119 runs and processes OneToOne relations
        $entity = new ReflectionEntity(TestEntityWithOneToOneOwning::class);

        $properties = iterator_to_array($entity->getEntityFieldsProperties());

        // Should contain both properties and OneToOne relations
        $hasRelation = false;
        foreach ($properties as $property) {
            if ($property instanceof ReflectionRelation) {
                $hasRelation = true;
                $this->assertTrue($property->isOwningSide());
            }
        }
        $this->assertTrue($hasRelation, 'Should include OneToOne relations');
    }

    public function testGetEntityFieldsPropertiesSkipsNonOwningOneToOneRelations(): void
    {
        // This test ensures the continue statement at line 127 is executed for non-owning relations
        $entity = new ReflectionEntity(TestEntityWithOneToOneNonOwning::class);

        $properties = iterator_to_array($entity->getEntityFieldsProperties());

        // Should contain only properties, not the non-owning OneToOne relation
        foreach ($properties as $property) {
            $this->assertInstanceOf(ReflectionProperty::class, $property);
        }
    }

    public function testGetPrimaryKeyColumnsReturnsEmptyArrayForNonEntity(): void
    {
        // This test ensures the return statement at line 211 is crucial
        $entity = new ReflectionEntity(TestNonEntity::class);

        $columns = $entity->getPrimaryKeyColumns();

        // Should return empty array for non-entity
        $this->assertEquals([], $columns);
    }

    public function testGetPrimaryKeyColumnsWithFallbackColumnNameUsesStrToLower(): void
    {
        // This test ensures the strtolower function at line 227 is used
        $entity = new ReflectionEntity(TestEntityWithCamelCasePrimaryKey::class);

        $columns = $entity->getPrimaryKeyColumns();

        // Should convert camelCase to snake_case using strtolower
        $this->assertContains('user_name_id', $columns);
    }

    public function testGetPrimaryKeyColumnsWithEntityHavingPrimaryKeys(): void
    {
        // This test ensures the return statement at line 211 is necessary
        $entity = new ReflectionEntity(TestEntityWithPrimaryKey::class);

        $columns = $entity->getPrimaryKeyColumns();

        // Should return the primary key columns, not an empty array
        $this->assertNotEmpty($columns);
        $this->assertContains('id', $columns);
    }
}

// Test entities for ReflectionEntity tests
#[Entity]
class TestEntityWithOneToOneNonOwning {
    #[Property]
    public string $name;

    #[OneToOne(targetEntity: TestRelatedEntity::class, ownedBy: 'testEntity')]
    public ?TestRelatedEntity $related;
}

#[Entity]
class TestEntityWithOneToOneOwning {
    #[Property]
    public string $name;

    #[OneToOne(targetEntity: TestRelatedEntity::class)]
    public ?TestRelatedEntity $related;
}

#[Entity]
class TestEntityWithManyToOne {
    #[Property]
    public string $name;

    #[ManyToOne(targetEntity: TestRelatedEntity::class)]
    public ?TestRelatedEntity $related;
}

#[Entity]
class TestEntityWithMorphTo {
    #[Property]
    public string $name;

    #[MorphTo]
    public object $parent;
}

#[Entity]
class TestEntityWithMorphOneNonOwning {
    #[Property]
    public string $name;

    #[MorphOne(targetEntity: TestRelatedEntity::class, referencedBy: 'testEntity')]
    public ?TestRelatedEntity $related;
}

#[Entity]
class TestEntityWithMorphOneOwning {
    #[Property]
    public string $name;

    #[MorphOne(targetEntity: TestRelatedEntity::class)]
    public ?TestRelatedEntity $related;
}

#[Entity]
class TestEntityWithMorphManyNonOwning {
    #[Property]
    public string $name;

    #[MorphMany(targetEntity: TestRelatedEntity::class, referencedBy: 'testEntity')]
    public array $related;
}

#[Entity]
class TestEntityWithMorphManyOwning {
    #[Property]
    public string $name;

    #[MorphMany(targetEntity: TestRelatedEntity::class)]
    public array $related;
}

#[Entity]
class TestEntityWithMultipleProperties {
    #[Property]
    public int $id;

    #[Property]
    public string $name;

    #[Property]
    public ?string $description;

    #[OneToOne(targetEntity: TestRelatedEntity::class)]
    public ?TestRelatedEntity $related;
}

#[Entity]
class TestEntityWithOnlyProperty {
    #[Property]
    public int $id;

    #[Property]
    public string $name;
}

class TestNonEntity {
    public string $name;
}

#[Entity]
class TestEntityWithCamelCasePrimaryKey {
    #[PrimaryKey]
    public int $userNameId;

    #[Property]
    public string $name;
}

#[Entity]
class TestEntityWithPrimaryKey {
    #[PrimaryKey]
    public int $id;

    #[Property]
    public string $name;
}

#[Entity]
class TestRelatedEntity {
    #[Property]
    public int $id;

    #[Property]
    public string $name;
}
