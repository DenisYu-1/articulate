<?php

namespace Articulate\Tests\Attributes;

use Articulate\Attributes\Property;
use PHPUnit\Framework\TestCase;

class PropertyAttributeTest extends TestCase
{
    public function testPropertyAttributeDefaultConstructor(): void
    {
        $property = new Property();

        $this->assertNull($property->name);
        $this->assertNull($property->type);
        $this->assertNull($property->nullable);
        $this->assertNull($property->defaultValue);
        $this->assertNull($property->maxLength);
    }

    public function testPropertyAttributeWithAllParameters(): void
    {
        $property = new Property(
            name: 'custom_column',
            type: 'string',
            nullable: true,
            defaultValue: 'default_value',
            maxLength: 255
        );

        $this->assertEquals('custom_column', $property->name);
        $this->assertEquals('string', $property->type);
        $this->assertTrue($property->nullable);
        $this->assertEquals('default_value', $property->defaultValue);
        $this->assertEquals(255, $property->maxLength);
    }

    public function testPropertyAttributeWithPartialParameters(): void
    {
        $property = new Property(
            name: 'test_name',
            type: 'int'
        );

        $this->assertEquals('test_name', $property->name);
        $this->assertEquals('int', $property->type);
        $this->assertNull($property->nullable);
        $this->assertNull($property->defaultValue);
        $this->assertNull($property->maxLength);
    }

    public function testPropertyAttributeWithNullValues(): void
    {
        $property = new Property(
            name: null,
            type: null,
            nullable: null,
            defaultValue: null,
            maxLength: null
        );

        $this->assertNull($property->name);
        $this->assertNull($property->type);
        $this->assertNull($property->nullable);
        $this->assertNull($property->defaultValue);
        $this->assertNull($property->maxLength);
    }

    public function testPropertyAttributeWithFalseNullable(): void
    {
        $property = new Property(nullable: false);

        $this->assertFalse($property->nullable);
        $this->assertNull($property->name);
        $this->assertNull($property->type);
        $this->assertNull($property->defaultValue);
        $this->assertNull($property->maxLength);
    }

    public function testPropertyAttributeWithZeroMaxLength(): void
    {
        $property = new Property(maxLength: 0);

        $this->assertEquals(0, $property->maxLength);
    }

    public function testPropertyAttributeWithLargeMaxLength(): void
    {
        $property = new Property(maxLength: 65535);

        $this->assertEquals(65535, $property->maxLength);
    }

    public function testPropertyAttributePropertiesArePublic(): void
    {
        $property = new Property('test', 'string', true, 'default', 100);

        // Test modification
        $property->name = 'modified';
        $property->type = 'int';
        $property->nullable = false;
        $property->defaultValue = 'new_default';
        $property->maxLength = 200;

        $this->assertEquals('modified', $property->name);
        $this->assertEquals('int', $property->type);
        $this->assertFalse($property->nullable);
        $this->assertEquals('new_default', $property->defaultValue);
        $this->assertEquals(200, $property->maxLength);
    }
}