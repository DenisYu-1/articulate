<?php

namespace Articulate\Tests\Attributes\Entity;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionProperty;
use Articulate\Tests\AbstractTestCase;

#[Entity]
class EntityDefaultTest extends AbstractTestCase
{
    #[Property]
    private int $propertyWithAttribute;
    private int $propertyWithoutAttribute;

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
        $properties = iterator_to_array($entity->getEntityProperties());
        $properties = iterator_to_array($entity->getEntityFieldsProperties());

        $this->assertEquals(1, count($properties));
        $this->assertEquals('property_with_attribute', $properties[0]->getColumnName());
    }
}
