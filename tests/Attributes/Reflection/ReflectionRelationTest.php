<?php

namespace Articulate\Tests\Attributes\Reflection;

use Articulate\Attributes\Reflection\ReflectionRelation;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Schema\SchemaNaming;
use Articulate\Tests\AbstractTestCase;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestEntity;

class ReflectionRelationTest extends AbstractTestCase
{
    public function testGetTargetEntity()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new ManyToOne(targetEntity: TestEntity::class);

        // Create a mock property for testing
        $mockProperty = $this->createMock(\ReflectionProperty::class);
        $mockProperty->method('getType')->willReturn(null);

        $reflection = new ReflectionRelation($attribute, $mockProperty, $schemaNaming);

        $this->assertEquals(TestEntity::class, $reflection->getTargetEntity());
    }

    public function testIsOneToMany()
    {
        $schemaNaming = new SchemaNaming();
        $attribute = new ManyToOne(targetEntity: TestEntity::class);

        $mockProperty = $this->createMock(\ReflectionProperty::class);
        $mockProperty->method('getType')->willReturn(null);

        $reflection = new ReflectionRelation($attribute, $mockProperty, $schemaNaming);

        $this->assertFalse($reflection->isOneToMany());
        $this->assertTrue($reflection->isManyToOne());
    }
}
