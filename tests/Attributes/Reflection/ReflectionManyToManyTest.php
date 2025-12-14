<?php

namespace Articulate\Tests\Attributes\Reflection;

use Articulate\Attributes\Reflection\ReflectionManyToMany;
use Articulate\Attributes\Relations\ManyToMany;
use Articulate\Attributes\Relations\MappingTable;
use Articulate\Schema\SchemaNaming;
use Articulate\Tests\AbstractTestCase;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestEntity;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestMultiPrimaryKeyEntity;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestPrimaryKeyEntity;

class ReflectionManyToManyTest extends AbstractTestCase
{
    /** @var array<int, string> */
    private array $testProperty;

    private \ReflectionProperty $reflectionProperty;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reflectionProperty = new \ReflectionProperty($this, 'testProperty');
    }

    public function testGetTableNameWithMappingTable()
    {
        $schemaNaming = new SchemaNaming();
        $mappingTable = new MappingTable('custom_mapping_table');
        $attribute = new ManyToMany(
            targetEntity: TestEntity::class,
            mappingTable: $mappingTable
        );

        $reflection = new ReflectionManyToMany($attribute, $this->reflectionProperty, $schemaNaming);

        $this->assertEquals('custom_mapping_table', $reflection->getTableName());
    }

    public function testGetExtraPropertiesWithoutMappingTable()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new ManyToMany(targetEntity: TestEntity::class);

        $reflection = new ReflectionManyToMany($attribute, $this->reflectionProperty, $schemaNaming);

        $extraProperties = $reflection->getExtraProperties();
        $this->assertIsArray($extraProperties);
        $this->assertEmpty($extraProperties);
    }

    public function testGetExtraPropertiesWithMappingTable()
    {
        $schemaNaming = new SchemaNaming();
        $mappingTable = new MappingTable('test_table', ['extra_prop' => 'value']);
        $attribute = new ManyToMany(
            targetEntity: TestEntity::class,
            mappingTable: $mappingTable
        );

        $reflection = new ReflectionManyToMany($attribute, $this->reflectionProperty, $schemaNaming);

        $extraProperties = $reflection->getExtraProperties();
        $this->assertEquals(['extra_prop' => 'value'], $extraProperties);
    }

    public function testGetTargetPrimaryColumn()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new ManyToMany(targetEntity: TestPrimaryKeyEntity::class);

        $reflection = new ReflectionManyToMany($attribute, $this->reflectionProperty, $schemaNaming);

        // TestPrimaryKeyEntity has a primary key 'id', so this should return 'id'
        $this->assertEquals('id', $reflection->getTargetPrimaryColumn());
    }

    public function testGetOwnerPrimaryColumn()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new ManyToMany(targetEntity: TestEntity::class);

        $reflection = new ReflectionManyToMany($attribute, $this->reflectionProperty, $schemaNaming);

        // Since TestEntity::class is not an entity, this should fall back to default
        $this->assertEquals('id', $reflection->getOwnerPrimaryColumn());
    }

    public function testGetTargetEntity()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new ManyToMany(targetEntity: TestEntity::class);

        $reflection = new ReflectionManyToMany($attribute, $this->reflectionProperty, $schemaNaming);

        // This should call assertCollectionType and validate the collection type
        $this->assertEquals(TestEntity::class, $reflection->getTargetEntity());
    }

    public function testGetTargetPrimaryColumnWithMultiPrimaryKey()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new ManyToMany(targetEntity: TestMultiPrimaryKeyEntity::class);

        $reflection = new ReflectionManyToMany($attribute, $this->reflectionProperty, $schemaNaming);

        // TestMultiPrimaryKeyEntity has primary keys ['id', 'name'], so this should return 'id' (first one)
        $this->assertEquals('id', $reflection->getTargetPrimaryColumn());
    }
}
