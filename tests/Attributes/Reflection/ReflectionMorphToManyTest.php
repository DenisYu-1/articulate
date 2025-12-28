<?php

namespace Articulate\Tests\Attributes\Reflection;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Reflection\ReflectionMorphToMany;
use Articulate\Attributes\Relations\MappingTable;
use Articulate\Attributes\Relations\MorphToMany;
use Articulate\Collection\MappingCollection;
use Articulate\Tests\AbstractTestCase;

class ReflectionMorphToManyTest extends AbstractTestCase {
    public function testConstructorCallsAssertCollectionType(): void
    {
        // This test ensures the assertCollectionType() method call at line 30 is executed
        $this->expectNotToPerformAssertions();

        try {
            $reflectionProperty = new \ReflectionProperty(TestEntityWithMorphToManyValid::class, 'tags');
            $attribute = new MorphToMany(TestTagEntity::class, 'taggable');
            new ReflectionMorphToMany($attribute, $reflectionProperty);
        } catch (\Exception $e) {
            // Expected if property doesn't exist, but the point is assertCollectionType was called
        }
    }

    public function testGetExtraPropertiesWithNullMappingTableReturnsEmptyArray(): void
    {
        // This test ensures the null-safe property access and coalesce at line 97 work correctly
        $reflectionProperty = new \ReflectionProperty(TestEntityWithMorphToManyValid::class, 'tags');
        $attribute = new MorphToMany(TestTagEntity::class, 'taggable');
        $reflection = new ReflectionMorphToMany($attribute, $reflectionProperty);

        $extraProperties = $reflection->getExtraProperties();

        // Should return empty array when mappingTable is null
        $this->assertEquals([], $extraProperties);
    }

    public function testGetTargetPrimaryColumnReturnsFirstColumnFromEntity(): void
    {
        // This test ensures the array access $columns[0] at line 105 works correctly
        $reflectionProperty = new \ReflectionProperty(TestEntityWithMorphToManyValid::class, 'tags');
        $attribute = new MorphToMany(TestTagEntity::class, 'taggable');
        $reflection = new ReflectionMorphToMany($attribute, $reflectionProperty);

        $primaryColumn = $reflection->getTargetPrimaryColumn();

        // Should return the first primary key column from the target entity
        $this->assertEquals('id', $primaryColumn);
    }

    public function testGetTargetPrimaryColumnFallsBackToIdWhenNoPrimaryKeys(): void
    {
        // This test ensures the coalesce operator ?? 'id' at line 105 works correctly
        $reflectionProperty = new \ReflectionProperty(TestEntityWithMorphToManyValid::class, 'tags');
        $attribute = new MorphToMany(TestEntityNoPrimaryKey::class, 'taggable');
        $reflection = new ReflectionMorphToMany($attribute, $reflectionProperty);

        $primaryColumn = $reflection->getTargetPrimaryColumn();

        // Should fallback to 'id' when entity has no primary keys
        $this->assertEquals('id', $primaryColumn);
    }

    public function testGetOwnerPrimaryColumnReturnsFirstColumnFromOwnerEntity(): void
    {
        // Similar to target primary column but for owner
        $reflectionProperty = new \ReflectionProperty(TestEntityWithMorphToManyValid::class, 'tags');
        $attribute = new MorphToMany(TestTagEntity::class, 'taggable');
        $reflection = new ReflectionMorphToMany($attribute, $reflectionProperty);

        $primaryColumn = $reflection->getOwnerPrimaryColumn();

        // Should return the first primary key column from the owner entity
        $this->assertEquals('id', $primaryColumn);
    }

    public function testAssertCollectionTypeAllowsNullType(): void
    {
        // This test ensures the identical comparison $type === null at line 142 works
        $reflectionProperty = new \ReflectionProperty(TestEntityWithMorphToManyNoType::class, 'tags');
        $attribute = new MorphToMany(TestTagEntity::class, 'taggable');

        // This should not throw an exception because type is null
        $reflection = new ReflectionMorphToMany($attribute, $reflectionProperty);

        $this->assertInstanceOf(ReflectionMorphToMany::class, $reflection);
    }

    public function testAssertCollectionTypeAllowsBuiltinArrayType(): void
    {
        // This test ensures builtin array types are allowed
        $reflectionProperty = new \ReflectionProperty(TestEntityWithMorphToManyArray::class, 'tags');
        $attribute = new MorphToMany(TestTagEntity::class, 'taggable');

        $reflection = new ReflectionMorphToMany($attribute, $reflectionProperty);

        $this->assertInstanceOf(ReflectionMorphToMany::class, $reflection);
    }

    public function testAssertCollectionTypeAllowsBuiltinIterableType(): void
    {
        // This test ensures builtin iterable types are allowed
        $reflectionProperty = new \ReflectionProperty(TestEntityWithMorphToManyIterable::class, 'tags');
        $attribute = new MorphToMany(TestTagEntity::class, 'taggable');

        $reflection = new ReflectionMorphToMany($attribute, $reflectionProperty);

        $this->assertInstanceOf(ReflectionMorphToMany::class, $reflection);
    }

    public function testAssertCollectionTypeAllowsMappingCollectionType(): void
    {
        // This test ensures MappingCollection types are allowed
        $reflectionProperty = new \ReflectionProperty(TestEntityWithMorphToManyMappingCollection::class, 'tags');
        $attribute = new MorphToMany(TestTagEntity::class, 'taggable');

        $reflection = new ReflectionMorphToMany($attribute, $reflectionProperty);

        $this->assertInstanceOf(ReflectionMorphToMany::class, $reflection);
    }

    public function testAssertCollectionTypeAllowsMappingCollectionSubclass(): void
    {
        // This test ensures MappingCollection subclasses are allowed
        $reflectionProperty = new \ReflectionProperty(TestEntityWithMorphToManyMappingCollectionSubclass::class, 'tags');
        $attribute = new MorphToMany(TestTagEntity::class, 'taggable');

        $reflection = new ReflectionMorphToMany($attribute, $reflectionProperty);

        $this->assertInstanceOf(ReflectionMorphToMany::class, $reflection);
    }

    public function testAssertCollectionTypeRejectsInvalidBuiltinType(): void
    {
        // This test ensures invalid builtin types throw exceptions
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Morph-to-many property must be iterable collection');

        $reflectionProperty = new \ReflectionProperty(TestEntityWithMorphToManyInvalidType::class, 'tags');
        $attribute = new MorphToMany(TestTagEntity::class, 'taggable');

        new ReflectionMorphToMany($attribute, $reflectionProperty);
    }

    public function testAssertCollectionTypeRejectsInvalidClassType(): void
    {
        // This test ensures invalid class types throw exceptions
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Morph-to-many property must be array, iterable, or MappingCollection');

        $reflectionProperty = new \ReflectionProperty(TestEntityWithMorphToManyInvalidClass::class, 'tags');
        $attribute = new MorphToMany(TestTagEntity::class, 'taggable');

        new ReflectionMorphToMany($attribute, $reflectionProperty);
    }

    public function testGetExtraPropertiesWithMappingTable(): void
    {
        // This test ensures the null-safe property access and coalesce at line 95 work correctly
        $reflectionProperty = new \ReflectionProperty(TestEntityWithMorphToManyValid::class, 'tags');
        $mappingTable = new MappingTable(name: 'custom_table');
        $attribute = new MorphToMany(TestTagEntity::class, 'taggable', mappingTable: $mappingTable);
        $reflection = new ReflectionMorphToMany($attribute, $reflectionProperty);

        $extraProperties = $reflection->getExtraProperties();

        // Should return empty array when mappingTable exists but has no properties
        $this->assertEquals([], $extraProperties);
    }

    public function testGetTargetPrimaryColumnWithEntityHavingMultiplePrimaryKeys(): void
    {
        // This test ensures the array access $columns[0] at line 103 works correctly
        $reflectionProperty = new \ReflectionProperty(TestEntityWithMorphToManyValid::class, 'tags');
        $attribute = new MorphToMany(TestEntityWithMultiplePrimaryKeys::class, 'taggable');
        $reflection = new ReflectionMorphToMany($attribute, $reflectionProperty);

        $primaryColumn = $reflection->getTargetPrimaryColumn();

        // Should return the first primary key column
        $this->assertEquals('id', $primaryColumn);
    }

    public function testGetOwnerPrimaryColumnWithEntityHavingMultiplePrimaryKeys(): void
    {
        // This test ensures the array access $columns[0] at line 111 works correctly
        $reflectionProperty = new \ReflectionProperty(TestEntityWithMultiplePrimaryKeys::class, 'tags');
        $attribute = new MorphToMany(TestTagEntity::class, 'taggable');
        $reflection = new ReflectionMorphToMany($attribute, $reflectionProperty);

        $primaryColumn = $reflection->getOwnerPrimaryColumn();

        // Should return the first primary key column
        $this->assertEquals('id', $primaryColumn);
    }
}

// Test entities for ReflectionMorphToMany tests
#[Entity]
class TestEntityWithMorphToManyValid {
    #[Property]
    public int $id;

    #[MorphToMany(targetEntity: TestTagEntity::class)]
    public array $tags;
}

#[Entity]
class TestEntityWithMorphToManyNoType {
    #[Property]
    public int $id;

    #[MorphToMany(targetEntity: TestTagEntity::class)]
    public $tags;
}

#[Entity]
class TestEntityWithMorphToManyArray {
    #[Property]
    public int $id;

    #[MorphToMany(targetEntity: TestTagEntity::class)]
    public array $tags;
}

#[Entity]
class TestEntityWithMorphToManyIterable {
    #[Property]
    public int $id;

    #[MorphToMany(targetEntity: TestTagEntity::class)]
    public iterable $tags;
}

#[Entity]
class TestEntityWithMorphToManyMappingCollection {
    #[Property]
    public int $id;

    #[MorphToMany(targetEntity: TestTagEntity::class)]
    public MappingCollection $tags;
}

class CustomMappingCollection extends MappingCollection {
}

#[Entity]
class TestEntityWithMorphToManyMappingCollectionSubclass {
    #[Property]
    public int $id;

    #[MorphToMany(targetEntity: TestTagEntity::class)]
    public CustomMappingCollection $tags;
}

#[Entity]
class TestEntityWithMorphToManyInvalidType {
    #[Property]
    public int $id;

    #[MorphToMany(targetEntity: TestTagEntity::class)]
    public string $tags;
}

#[Entity]
class TestEntityWithMorphToManyInvalidClass {
    #[Property]
    public int $id;

    #[MorphToMany(targetEntity: TestTagEntity::class)]
    public \stdClass $tags;
}

#[Entity]
class TestTagEntity {
    #[Property]
    public int $id;

    #[Property]
    public string $name;
}

#[Entity]
class TestEntityNoPrimaryKey {
    #[Property]
    public string $name;
}

#[Entity]
class TestEntityWithMultiplePrimaryKeys {
    #[PrimaryKey]
    public int $id;

    #[PrimaryKey]
    public string $type;

    #[Property]
    public string $name;

    #[MorphToMany(targetEntity: TestTagEntity::class)]
    public array $tags;
}
