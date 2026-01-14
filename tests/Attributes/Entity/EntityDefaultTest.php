<?php

namespace Articulate\Tests\Attributes\Entity;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\AutoIncrement;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionProperty;
use Articulate\Tests\AbstractTestCase;

#[Entity]
class EntityDefaultTest extends AbstractTestCase {
    #[Property]
    private int $propertyWithAttribute;

    private int $propertyWithoutAttribute;

    #[PrimaryKey(name: 'custom_pk')]
    public int $id;

    #[AutoIncrement]
    #[Property]
    public int $autoIncrementId;

    public function testEntity()
    {
        $entity = new ReflectionEntity(static::class);

        $this->assertTrue($entity->isEntity());
    }

    public function testTableNameOverwrite()
    {
        $entity = new ReflectionEntity(static::class);

        $this->assertEquals('entity_default_test', $entity->getTableName());
    }

    public function testNameOverwrite()
    {
        $entity = new ReflectionEntity(static::class);

        $this->assertEquals('entity_default_test', $entity->getTableName());
    }

    public function testEntityProperties()
    {
        $entity = new ReflectionEntity(static::class);

        /** @var ReflectionProperty[] $properties */
        $properties = iterator_to_array($entity->getEntityFieldsProperties());

        $this->assertEquals(3, count($properties)); // propertyWithAttribute, id, autoIncrementId
        $this->assertEquals('property_with_attribute', $properties[0]->getColumnName());
        $this->assertEquals('custom_pk', $properties[1]->getColumnName());
        $this->assertEquals('auto_increment_id', $properties[2]->getColumnName());
    }

    public function testPrimaryKeyColumnsUseColumnName()
    {
        $entity = new ReflectionEntity(static::class);

        $primaryKeyColumns = $entity->getPrimaryKeyColumns();

        $this->assertEquals(['custom_pk'], $primaryKeyColumns);
    }

    public function testAutoIncrementAttributeDetection()
    {
        $entity = new ReflectionEntity(static::class);
        $properties = iterator_to_array($entity->getEntityProperties());

        $autoIncrementProperty = array_filter($properties, fn ($prop) => $prop->getFieldName() === 'autoIncrementId');
        $this->assertCount(1, $autoIncrementProperty);

        $property = reset($autoIncrementProperty);
        $this->assertTrue($property->isAutoIncrement(), 'AutoIncrement attribute should be detected');

        // Test that property without AutoIncrement returns false
        $regularProperty = array_filter($properties, fn ($prop) => $prop->getFieldName() === 'propertyWithAttribute');
        $this->assertCount(1, $regularProperty);

        $property = reset($regularProperty);
        $this->assertFalse($property->isAutoIncrement(), 'Property without AutoIncrement attribute should return false');
    }

    public function testPrimaryKeyAttributeDetection()
    {
        $entity = new ReflectionEntity(static::class);
        $properties = iterator_to_array($entity->getEntityProperties());

        $primaryKeyProperty = array_filter($properties, fn ($prop) => $prop->getFieldName() === 'id');
        $this->assertCount(1, $primaryKeyProperty);

        $property = reset($primaryKeyProperty);
        $this->assertTrue($property->isPrimaryKey(), 'PrimaryKey attribute should be detected');

        // Test that property without PrimaryKey returns false
        $regularProperty = array_filter($properties, fn ($prop) => $prop->getFieldName() === 'autoIncrementId');
        $this->assertCount(1, $regularProperty);

        $property = reset($regularProperty);
        $this->assertFalse($property->isPrimaryKey(), 'Property without PrimaryKey attribute should return false');
    }
}
