<?php

namespace Articulate\Tests\Attributes\Reflection;

use Articulate\Attributes\Property;
use Articulate\Attributes\Reflection\ReflectionProperty;
use Articulate\Tests\AbstractTestCase;

class ReflectionPropertyTest extends AbstractTestCase
{
    private $testProperty;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testProperty = new \ReflectionProperty($this, 'testProperty');
    }

    public function testDefaultAutoIncrementIsFalse()
    {
        $propertyAttr = new Property();

        $reflection = new ReflectionProperty($propertyAttr, $this->testProperty);

        $this->assertFalse($reflection->isAutoIncrement());
    }

    public function testDefaultPrimaryKeyIsFalse()
    {
        $propertyAttr = new Property();

        $reflection = new ReflectionProperty($propertyAttr, $this->testProperty);

        $this->assertFalse($reflection->isPrimaryKey());
    }

    public function testAutoIncrementCanBeSetToTrue()
    {
        $propertyAttr = new Property();

        $reflection = new ReflectionProperty($propertyAttr, $this->testProperty, true, false);

        $this->assertTrue($reflection->isAutoIncrement());
    }

    public function testPrimaryKeyCanBeSetToTrue()
    {
        $propertyAttr = new Property();

        $reflection = new ReflectionProperty($propertyAttr, $this->testProperty, false, true);

        $this->assertTrue($reflection->isPrimaryKey());
    }
}
