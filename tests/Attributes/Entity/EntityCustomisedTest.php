<?php

namespace Norm\Tests\Attributes\Entity;

use Norm\Attributes\Entity;
use Norm\Attributes\Reflection\ReflectionEntity;
use Norm\Tests\AbstractTestCase;

#[Entity(tableName: 'test')]
class EntityCustomisedTest extends AbstractTestCase
{
    public function testTableNameOverwrite()
    {
        $entity = new ReflectionEntity(static::class);

        $this->assertEquals('test', $entity->getTableName());
    }
}
