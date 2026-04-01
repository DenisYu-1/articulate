<?php

namespace Articulate\Tests\Attributes;

use Articulate\Attributes\Lifecycle\PostLoad;
use Articulate\Attributes\Lifecycle\PostPersist;
use Articulate\Attributes\Lifecycle\PostRemove;
use Articulate\Attributes\Lifecycle\PostUpdate;
use Articulate\Attributes\Lifecycle\PrePersist;
use Articulate\Attributes\Lifecycle\PreRemove;
use Articulate\Attributes\Lifecycle\PreUpdate;
use Attribute;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LifecycleAttributeTest extends TestCase {
    /** @return array<string, array{class-string}> */
    public static function lifecycleAttributeProvider(): array
    {
        return [
            'PrePersist'  => [PrePersist::class],
            'PostPersist' => [PostPersist::class],
            'PreUpdate'   => [PreUpdate::class],
            'PostUpdate'  => [PostUpdate::class],
            'PreRemove'   => [PreRemove::class],
            'PostRemove'  => [PostRemove::class],
            'PostLoad'    => [PostLoad::class],
        ];
    }

    #[DataProvider('lifecycleAttributeProvider')]
    public function testAttributeInstantiation(string $attributeClass): void
    {
        $instance = new $attributeClass();

        $this->assertInstanceOf($attributeClass, $instance);
    }

    #[DataProvider('lifecycleAttributeProvider')]
    public function testAttributeTarget(string $attributeClass): void
    {
        $attributes = (new \ReflectionClass($attributeClass))->getAttributes(Attribute::class);

        $this->assertNotEmpty($attributes);

        $attributeInstance = $attributes[0]->newInstance();
        $this->assertSame(Attribute::TARGET_METHOD, $attributeInstance->flags);
    }
}
