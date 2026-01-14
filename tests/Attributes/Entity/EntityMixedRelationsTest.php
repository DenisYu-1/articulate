<?php

namespace Articulate\Tests\Attributes\Entity;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionManyToMany;
use Articulate\Attributes\Reflection\ReflectionRelation;
use Articulate\Attributes\Relations\ManyToMany;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Attributes\Relations\MappingTable;
use Articulate\Attributes\Relations\MappingTableProperty;
use Articulate\Attributes\Relations\MorphedByMany;
use Articulate\Attributes\Relations\MorphMany;
use Articulate\Attributes\Relations\MorphToMany;
use Articulate\Attributes\Relations\OneToMany;
use Articulate\Attributes\Relations\OneToOne;
use Articulate\Schema\SchemaNaming;
use Articulate\Tests\AbstractTestCase;

#[Entity]
class EntityMixedRelationsTest extends AbstractTestCase {
    #[Property]
    public int $id;

    // This is an owning side (no ownedBy/referencedBy)
    #[ManyToMany(
        targetEntity: self::class,
        referencedBy: 'inverseRelations',
        mappingTable: new MappingTable(name: 'mixed_relations_map')
    )]
    public array $owningRelations;

    // This is another owning side
    #[ManyToMany(
        targetEntity: self::class,
        referencedBy: 'inverseRelations2',
        mappingTable: new MappingTable(name: 'mixed_relations_map2')
    )]
    public array $owningRelations2;

    // This is an inverse side
    #[ManyToMany(
        ownedBy: 'owningRelations',
        targetEntity: self::class
    )]
    public array $inverseRelations;

    public function testMixedOwningAndInverseRelationsAreProcessed()
    {
        $entity = new ReflectionEntity(static::class);

        // Get all relations
        $relations = iterator_to_array($entity->getEntityRelationProperties());
        $manyToManyRelations = array_filter($relations, fn ($relation) => $relation instanceof ReflectionManyToMany);

        // Should have 3 ManyToMany relations
        $this->assertCount(3, $manyToManyRelations);

        // Two should be owning sides, one should be inverse side
        $owningSides = array_filter($manyToManyRelations, fn ($relation) => $relation->isOwningSide());
        $inverseSides = array_filter($manyToManyRelations, fn ($relation) => !$relation->isOwningSide());

        $this->assertCount(2, $owningSides, 'Should have exactly two owning side relations');
        $this->assertCount(1, $inverseSides, 'Should have exactly one inverse side relation');

        // Verify the owning sides have the correct property names
        $owningPropertyNames = array_map(fn ($relation) => $relation->getPropertyName(), $owningSides);
        sort($owningPropertyNames);
        $this->assertEquals(['owningRelations', 'owningRelations2'], $owningPropertyNames);

        // Verify the inverse side has the correct property name
        $inverseRelation = reset($inverseSides);
        $this->assertEquals('inverseRelations', $inverseRelation->getPropertyName());
    }

    public function testMappingTablePropertyDefaultValues(): void
    {
        // Test that MappingTableProperty has correct default values
        // Test that MappingTableProperty has correct default values for nullable field
        $property = new MappingTableProperty('test_name', 'string');

        $this->assertEquals('test_name', $property->name);
        $this->assertEquals('string', $property->type);
        $this->assertFalse($property->nullable, 'Default nullable value should be false');
        $this->assertNull($property->length);
        $this->assertNull($property->defaultValue);
    }

    public function testMorphManyDefaultForeignKeyValue(): void
    {
        // Test that MorphMany has correct default foreignKey value
        // Test that MorphMany has correct default foreignKey value
        $morphMany = new MorphMany(
            targetEntity: self::class,
            referencedBy: 'test'
        );

        // The default value should be true
        $this->assertTrue($morphMany->foreignKey);
    }

    public function testMorphToManyDefaultForeignKeyValue(): void
    {
        // Test that MorphToMany has correct default foreignKey value
        // Test that MorphToMany has correct default foreignKey value
        $morphToMany = new MorphToMany(
            targetEntity: self::class,
            name: 'test'
        );

        // The default value should be true
        $this->assertTrue($morphToMany->foreignKey);
    }

    public function testMorphedByManyDefaultForeignKeyValue(): void
    {
        // Test that MorphedByMany has correct default foreignKey value
        // Test that MorphedByMany has correct default foreignKey value
        $morphedByMany = new MorphedByMany(
            targetEntity: self::class,
            name: 'test'
        );

        // The default value should be true
        $this->assertTrue($morphedByMany->foreignKey);
    }

    public function testManyToManyIsForeignKeyRequired(): void
    {
        // Test that ManyToMany relations require foreign keys by default
        // Test that relation properties have correct default foreignKey value
        $schemaNaming = new SchemaNaming();
        $attribute = new ManyToMany(targetEntity: self::class);

        $reflectionProperty = new \ReflectionProperty($this, 'owningRelations');
        $reflection = new ReflectionRelation($attribute, $reflectionProperty, $schemaNaming);

        // ManyToMany should require foreign keys by default
        $this->assertTrue($reflection->isForeignKeyRequired());
    }

    public function testRelationTypeDetectionWorksCorrectly(): void
    {
        // Test that relation type detection methods work correctly
        // This covers various mutations in relation type checking logic
        $schemaNaming = new SchemaNaming();

        // Test OneToOne
        $oneToOneAttr = new OneToOne(targetEntity: self::class);
        $property = new \ReflectionProperty($this, 'owningRelations');
        $oneToOneRelation = new ReflectionRelation($oneToOneAttr, $property, $schemaNaming);

        $this->assertTrue($oneToOneRelation->isOneToOne());
        $this->assertFalse($oneToOneRelation->isOneToMany());
        $this->assertFalse($oneToOneRelation->isManyToOne());
        $this->assertFalse($oneToOneRelation->isMorphTo());
        $this->assertFalse($oneToOneRelation->isMorphOne());
        $this->assertFalse($oneToOneRelation->isMorphMany());

        // Test ManyToOne
        $manyToOneAttr = new ManyToOne(targetEntity: self::class);
        $manyToOneRelation = new ReflectionRelation($manyToOneAttr, $property, $schemaNaming);

        $this->assertFalse($manyToOneRelation->isOneToOne());
        $this->assertFalse($manyToOneRelation->isOneToMany());
        $this->assertTrue($manyToOneRelation->isManyToOne());
        $this->assertFalse($manyToOneRelation->isMorphTo());
        $this->assertFalse($manyToOneRelation->isMorphOne());
        $this->assertFalse($manyToOneRelation->isMorphMany());

        // Test OneToMany
        $oneToManyAttr = new OneToMany(targetEntity: self::class);
        $oneToManyRelation = new ReflectionRelation($oneToManyAttr, $property, $schemaNaming);

        $this->assertFalse($oneToManyRelation->isOneToOne());
        $this->assertTrue($oneToManyRelation->isOneToMany());
        $this->assertFalse($oneToManyRelation->isManyToOne());
        $this->assertFalse($oneToManyRelation->isMorphTo());
        $this->assertFalse($oneToManyRelation->isMorphOne());
        $this->assertFalse($oneToManyRelation->isMorphMany());
    }
}
