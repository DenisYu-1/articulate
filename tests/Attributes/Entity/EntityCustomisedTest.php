<?php

namespace Articulate\Tests\Attributes\Entity;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Tests\AbstractTestCase;

#[Entity(tableName: 'test')]
class EntityCustomisedTest extends AbstractTestCase {
    public function testTableNameOverwrite()
    {
        $entity = new ReflectionEntity(static::class);

        $this->assertEquals('test', $entity->getTableName());
    }
}
