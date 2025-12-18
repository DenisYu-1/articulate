<?php

namespace Articulate\Tests\Attributes\Reflection;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionManyToMany;
use Articulate\Attributes\Relations\ManyToMany;
use Articulate\Attributes\Relations\MappingTable;
use Articulate\Schema\SchemaNaming;
use Articulate\Tests\AbstractTestCase;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestEntity;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestMultiPrimaryKeyEntity;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestPrimaryKeyEntity;
use ReflectionProperty;
use RuntimeException;

class ReflectionManyToManyTest extends AbstractTestCase
{
    /** @var array<int, string> */
    private array $testProperty;

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

        // TestMultiPrimaryKeyEntity has primary keys ['id', 'name'] (sorted alphabetically),
        $this->assertEquals('id', $reflection->getTargetPrimaryColumn());

        // Also verify that the entity actually has multiple primary keys
        $targetEntity = new ReflectionEntity(TestMultiPrimaryKeyEntity::class);
        $primaryKeys = $targetEntity->getPrimaryKeyColumns();
        $this->assertCount(2, $primaryKeys);
        $this->assertEquals(['id', 'name'], $primaryKeys);
    }

    public function testGetOwnerPrimaryColumnWithMultiPrimaryKey()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new ManyToMany(targetEntity: TestEntity::class);

        // Use TestMultiPrimaryKeyEntity as the declaring class by creating a property on it
        $multiKeyProperty = new ReflectionProperty(TestMultiPrimaryKeyEntity::class, 'id');
        $reflection = new ReflectionManyToMany($attribute, $multiKeyProperty, $schemaNaming);

        // TestMultiPrimaryKeyEntity has primary keys ['id', 'name'], so getOwnerPrimaryColumn should return 'id' (first one)
        $this->assertEquals('id', $reflection->getOwnerPrimaryColumn());
    }

    public function testInvalidPropertyTypeThrowsException()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new ManyToMany(targetEntity: TestEntity::class);

        // Create a property with invalid type (string instead of array/iterable)
        $invalidProperty = new ReflectionProperty(TestEntity::class, 'id'); // 'id' is typed as int
        $reflection = new ReflectionManyToMany($attribute, $invalidProperty, $schemaNaming);

        // This should throw an exception because 'id' is not a collection type
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Many-to-many property must be iterable collection');

        $reflection->getTargetEntity();
    }
}
