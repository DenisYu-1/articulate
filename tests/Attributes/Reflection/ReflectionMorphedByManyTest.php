<?php

namespace Articulate\Tests\Attributes\Reflection;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionMorphedByMany;
use Articulate\Attributes\Relations\MorphedByMany;
use Articulate\Attributes\Relations\MappingTable;
use Articulate\Collection\MappingCollection;
use Articulate\Schema\SchemaNaming;
use Articulate\Tests\AbstractTestCase;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestEntity;
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
            TestEntity::class,
            'taggable'
        );

        $reflection = new ReflectionMorphedByMany($attribute, $this->reflectionProperty, $schemaNaming);

        $this->assertEquals('test_entity_id', $reflection->getTargetJoinColumn());
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
}