<?php

namespace Articulate\Tests\Attributes\Reflection;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Reflection\ReflectionMorphedByMany;
use Articulate\Attributes\Relations\MappingTable;
use Articulate\Attributes\Relations\MappingTableProperty;
use Articulate\Attributes\Relations\MorphedByMany;
use Articulate\Collection\MappingCollection;
use Articulate\Schema\SchemaNaming;
use Articulate\Tests\AbstractTestCase;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestEntity;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestPolymorphicManyToManyPost;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestPolymorphicManyToManyTag;
use ReflectionProperty;
use RuntimeException;

class ReflectionMorphedByManyTest extends AbstractTestCase {
    /** @var MappingCollection<int, TestEntity> */
    private MappingCollection $testProperty;

    private ReflectionProperty $reflectionProperty;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reflectionProperty = new ReflectionProperty($this, 'testProperty');
    }

    public function testGetTableNameWithMappingTable()
    {
        $schemaNaming = new SchemaNaming();
        $mappingTable = new MappingTable('custom_mapping_table');
        $attribute = new MorphedByMany(
            TestEntity::class,
            'taggable',
            mappingTable: $mappingTable
        );

        $reflection = new ReflectionMorphedByMany($attribute, $this->reflectionProperty, $schemaNaming);

        $this->assertEquals('custom_mapping_table', $reflection->getTableName());
    }

    public function testGetTableNameWithoutMappingTable()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new MorphedByMany(
            TestEntity::class,
            'taggable'
        );

        $reflection = new ReflectionMorphedByMany($attribute, $this->reflectionProperty, $schemaNaming);

        $this->assertEquals('taggables', $reflection->getTableName());
    }

    public function testGetTargetEntityWithExplicitTarget()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new MorphedByMany(
            TestEntity::class,
            'taggable'
        );

        $reflection = new ReflectionMorphedByMany($attribute, $this->reflectionProperty, $schemaNaming);

        $this->assertEquals(TestEntity::class, $reflection->getTargetEntity());
    }

    public function testGetDeclaringClassName()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new MorphedByMany(
            TestEntity::class,
            'taggable'
        );

        $reflection = new ReflectionMorphedByMany($attribute, $this->reflectionProperty, $schemaNaming);

        $this->assertEquals(self::class, $reflection->getDeclaringClassName());
    }

    public function testIsOwningSideReturnsFalse()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new MorphedByMany(
            TestEntity::class,
            'taggable'
        );

        $reflection = new ReflectionMorphedByMany($attribute, $this->reflectionProperty, $schemaNaming);

        $this->assertFalse($reflection->isOwningSide());
    }

    public function testGetMappedByReturnsNull()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new MorphedByMany(
            TestEntity::class,
            'taggable'
        );

        $reflection = new ReflectionMorphedByMany($attribute, $this->reflectionProperty, $schemaNaming);

        $this->assertNull($reflection->getMappedBy());
    }

    public function testGetInversedByReturnsNull()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new MorphedByMany(
            TestEntity::class,
            'taggable'
        );

        $reflection = new ReflectionMorphedByMany($attribute, $this->reflectionProperty, $schemaNaming);

        $this->assertNull($reflection->getInversedBy());
    }

    public function testGetOwnerJoinColumn()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new MorphedByMany(
            TestEntity::class,
            'taggable'
        );

        $reflection = new ReflectionMorphedByMany($attribute, $this->reflectionProperty, $schemaNaming);

        $this->assertEquals('taggable_id', $reflection->getOwnerJoinColumn());
    }

    public function testGetTargetJoinColumn()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new MorphedByMany(
            TestPolymorphicManyToManyPost::class,
            'taggable'
        );

        // Property declared on TestPolymorphicManyToManyTag → column must point back to that declaring entity
        $property = new ReflectionProperty(TestPolymorphicManyToManyTag::class, 'posts');
        $reflection = new ReflectionMorphedByMany($attribute, $property, $schemaNaming);

        $this->assertEquals('test_polymorphic_many_to_many_tag_id', $reflection->getTargetJoinColumn());
    }

    public function testGetTypeColumn()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new MorphedByMany(
            TestEntity::class,
            'taggable'
        );

        $reflection = new ReflectionMorphedByMany($attribute, $this->reflectionProperty, $schemaNaming);

        $this->assertEquals('taggable_type', $reflection->getTypeColumn());
    }

    public function testGetExtraPropertiesWithoutMappingTable()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new MorphedByMany(
            TestEntity::class,
            'taggable'
        );

        $reflection = new ReflectionMorphedByMany($attribute, $this->reflectionProperty, $schemaNaming);

        $this->assertEquals([], $reflection->getExtraProperties());
    }

    public function testGetExtraPropertiesWithMappingTable()
    {
        $schemaNaming = new SchemaNaming();
        $mappingTableProperty = new MappingTableProperty('position', 'int');
        $mappingTable = new MappingTable('custom_mapping_table', [$mappingTableProperty]);
        $attribute = new MorphedByMany(
            TestEntity::class,
            'taggable',
            mappingTable: $mappingTable
        );

        $reflection = new ReflectionMorphedByMany($attribute, $this->reflectionProperty, $schemaNaming);

        $this->assertSame([$mappingTableProperty], $reflection->getExtraProperties());
    }

    public function testGetTargetPrimaryColumn()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new MorphedByMany(
            TestEntity::class,
            'taggable'
        );

        $reflection = new ReflectionMorphedByMany($attribute, $this->reflectionProperty, $schemaNaming);

        $this->assertEquals('id', $reflection->getTargetPrimaryColumn());
    }

    public function testGetOwnerPrimaryColumn()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new MorphedByMany(
            TestEntity::class,
            'taggable'
        );

        $reflection = new ReflectionMorphedByMany($attribute, $this->reflectionProperty, $schemaNaming);

        $this->assertEquals('id', $reflection->getOwnerPrimaryColumn());
    }

    public function testGetPrimaryColumns()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new MorphedByMany(
            TestEntity::class,
            'taggable'
        );

        $reflection = new ReflectionMorphedByMany($attribute, $this->reflectionProperty, $schemaNaming);

        $this->assertEquals(['taggable_type', 'taggable_id'], $reflection->getPrimaryColumns());
    }

    public function testGetPropertyName()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new MorphedByMany(
            TestEntity::class,
            'taggable'
        );

        $reflection = new ReflectionMorphedByMany($attribute, $this->reflectionProperty, $schemaNaming);

        $this->assertEquals('testProperty', $reflection->getPropertyName());
    }

    public function testGetAttribute()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new MorphedByMany(
            TestEntity::class,
            'taggable'
        );

        $reflection = new ReflectionMorphedByMany($attribute, $this->reflectionProperty, $schemaNaming);

        $this->assertSame($attribute, $reflection->getAttribute());
    }

    public function testGetMorphName()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new MorphedByMany(
            TestEntity::class,
            'taggable'
        );

        $reflection = new ReflectionMorphedByMany($attribute, $this->reflectionProperty, $schemaNaming);

        $this->assertEquals('taggable', $reflection->getMorphName());
    }

    public function testGetTargetPrimaryColumnUsesFirstNonDefaultPrimaryKey()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new MorphedByMany(
            TestMorphedByManyNonDefaultPrimaryTarget::class,
            'taggable'
        );
        $property = new ReflectionProperty(TestMorphedByManyNonDefaultPrimaryOwner::class, 'targets');
        $reflection = new ReflectionMorphedByMany($attribute, $property, $schemaNaming);

        $this->assertSame('code', $reflection->getTargetPrimaryColumn());
    }

    public function testGetOwnerPrimaryColumnUsesFirstNonDefaultPrimaryKey()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new MorphedByMany(
            TestMorphedByManyNonDefaultPrimaryTarget::class,
            'taggable'
        );
        $property = new ReflectionProperty(TestMorphedByManyNonDefaultPrimaryOwner::class, 'targets');
        $reflection = new ReflectionMorphedByMany($attribute, $property, $schemaNaming);

        $this->assertSame('owner_code', $reflection->getOwnerPrimaryColumn());
    }

    public function testGetTargetEntityRejectsInvalidBuiltinCollectionType()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new MorphedByMany(TestEntity::class, 'taggable');
        $property = new ReflectionProperty(TestMorphedByManyInvalidBuiltinOwner::class, 'targets');
        $reflection = new ReflectionMorphedByMany($attribute, $property, $schemaNaming);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Morphed-by-many property must be iterable collection');

        $reflection->getTargetEntity();
    }

    public function testGetTargetEntityRejectsInvalidClassCollectionType()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new MorphedByMany(TestEntity::class, 'taggable');
        $property = new ReflectionProperty(TestMorphedByManyInvalidClassOwner::class, 'targets');
        $reflection = new ReflectionMorphedByMany($attribute, $property, $schemaNaming);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Morphed-by-many property must be array, iterable, or MappingCollection');

        $reflection->getTargetEntity();
    }
}

#[Entity]
class TestMorphedByManyNonDefaultPrimaryTarget {
    #[PrimaryKey]
    public int $tenant_id;

    #[PrimaryKey]
    public string $code;
}

#[Entity]
class TestMorphedByManyNonDefaultPrimaryOwner {
    #[PrimaryKey]
    public string $owner_code;

    #[PrimaryKey]
    public int $owner_tenant_id;

    /** @var MappingCollection<int, TestMorphedByManyNonDefaultPrimaryTarget> */
    #[MorphedByMany(TestMorphedByManyNonDefaultPrimaryTarget::class, 'taggable')]
    public MappingCollection $targets;
}

#[Entity]
class TestMorphedByManyInvalidBuiltinOwner {
    #[PrimaryKey]
    public int $id;

    #[MorphedByMany(TestEntity::class, 'taggable')]
    public string $targets;
}

#[Entity]
class TestMorphedByManyInvalidClassOwner {
    #[PrimaryKey]
    public int $id;

    #[MorphedByMany(TestEntity::class, 'taggable')]
    public \stdClass $targets;
}
