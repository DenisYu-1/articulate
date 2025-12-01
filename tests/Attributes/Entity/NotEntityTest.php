<?php

namespace Norm\Tests\Attributes\Entity;

use Norm\Attributes\Property;
use Norm\Attributes\Reflection\ReflectionEntity;
use Norm\Tests\AbstractTestCase;

class NotEntityTest extends AbstractTestCase
{
    #[Property]
    private int $propertyWithAttribute;

    public function testNotEntity()
    {
        $entity = new ReflectionEntity(static::class);

        $this->assertFalse($entity->isEntity());
    }

    public function testNotEntityWillNotReturnEntityProperties()
    {
        $entity = new ReflectionEntity(static::class);

        $result = iterator_to_array($entity->getEntityProperties());
        $this->assertEmpty($result);
    }

    public function testWillNotReturnTableName()
    {
        $entity = new ReflectionEntity(static::class);

        $this->assertEmpty($entity->getTableName());
    }
}
