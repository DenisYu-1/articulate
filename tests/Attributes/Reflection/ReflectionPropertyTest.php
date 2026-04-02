<?php

namespace Articulate\Tests\Attributes\Reflection;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionProperty;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestCustomPrimaryKeyEntity;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestPrimaryKeyEntity;
use PHPUnit\Framework\TestCase;

class ReflectionPropertyTest extends TestCase {
    /**
     * @return ReflectionProperty[]
     */
    private function getPropertiesForEntity(string $entityClass): array
    {
        $reflectionEntity = new ReflectionEntity($entityClass);
        $properties = [];
        foreach ($reflectionEntity->getEntityProperties() as $property) {
            if ($property instanceof ReflectionProperty) {
                $properties[$property->getFieldName()] = $property;
            }
        }

        return $properties;
    }

    public function testGetValue(): void
    {
        $properties = $this->getPropertiesForEntity(TestPrimaryKeyEntity::class);
        $entity = new TestPrimaryKeyEntity();
        $entity->name = 'test_value';

        $this->assertSame('test_value', $properties['name']->getValue($entity));
    }

    public function testSetValue(): void
    {
        $properties = $this->getPropertiesForEntity(TestPrimaryKeyEntity::class);
        $entity = new TestPrimaryKeyEntity();

        $properties['name']->setValue($entity, 'new_value');

        $this->assertSame('new_value', $entity->name);
    }

    public function testGetColumnName(): void
    {
        $properties = $this->getPropertiesForEntity(TestCustomPrimaryKeyEntity::class);

        $this->assertSame('custom_id', $properties['id']->getColumnName());
        $this->assertSame('name', $properties['name']->getColumnName());
    }

    public function testGetFieldName(): void
    {
        $properties = $this->getPropertiesForEntity(TestPrimaryKeyEntity::class);

        $this->assertSame('id', $properties['id']->getFieldName());
        $this->assertSame('name', $properties['name']->getFieldName());
    }

    public function testIsPrimaryKey(): void
    {
        $properties = $this->getPropertiesForEntity(TestPrimaryKeyEntity::class);

        $this->assertTrue($properties['id']->isPrimaryKey());
        $this->assertFalse($properties['name']->isPrimaryKey());
    }

    public function testIsNullable(): void
    {
        $properties = $this->getPropertiesForEntity(TestPrimaryKeyEntity::class);

        $this->assertFalse($properties['id']->isNullable());
        $this->assertFalse($properties['name']->isNullable());
    }

    public function testGetGeneratorType(): void
    {
        $properties = $this->getPropertiesForEntity(TestPrimaryKeyEntity::class);

        $this->assertNull($properties['id']->getGeneratorType());
        $this->assertNull($properties['name']->getGeneratorType());
    }
}
