<?php

namespace Articulate\Tests\Attributes;

use Articulate\Attributes\Indexes\AutoIncrement;
use Attribute;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class AutoIncrementAttributeTest extends TestCase {
    public function testAutoIncrementAttributeCanBeInstantiated(): void
    {
        $autoIncrement = new AutoIncrement();

        $this->assertInstanceOf(AutoIncrement::class, $autoIncrement);
    }

    public function testAutoIncrementAttributeIsAttribute(): void
    {
        $reflection = new ReflectionClass(AutoIncrement::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        $this->assertCount(1, $attributes);

        $attribute = $attributes[0]->newInstance();
        $this->assertEquals(Attribute::TARGET_PROPERTY, $attribute->flags);
    }

    public function testAutoIncrementAttributeTargetIsProperty(): void
    {
        $reflection = new ReflectionClass(AutoIncrement::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        $this->assertCount(1, $attributes);

        $attribute = $attributes[0]->newInstance();
        $this->assertTrue(($attribute->flags & Attribute::TARGET_PROPERTY) !== 0);
    }

    public function testAutoIncrementAttributeIsNotRepeatable(): void
    {
        $reflection = new ReflectionClass(AutoIncrement::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        $this->assertCount(1, $attributes);

        $attribute = $attributes[0]->newInstance();
        // Should not have IS_REPEATABLE flag set
        $this->assertFalse(($attribute->flags & Attribute::IS_REPEATABLE) !== 0);
    }

    public function testAutoIncrementAttributeDefaultConstructor(): void
    {
        $autoIncrement = new AutoIncrement();

        // Since AutoIncrement has no properties, just verify it can be created
        $this->assertNotNull($autoIncrement);
    }
}
