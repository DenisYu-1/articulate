<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Utils\ReflectionCache;
use PHPUnit\Framework\TestCase;

class ReflectionCacheTest extends TestCase {
    public function testGetClassReturnsSameInstance(): void
    {
        $first = ReflectionCache::getClass(\stdClass::class);
        $second = ReflectionCache::getClass(\stdClass::class);

        $this->assertSame($first, $second);
    }

    public function testGetClassReturnsCorrectClass(): void
    {
        $rc = ReflectionCache::getClass(\stdClass::class);

        $this->assertInstanceOf(\ReflectionClass::class, $rc);
        $this->assertSame(\stdClass::class, $rc->getName());
    }

    public function testGetPropertyReturnsSameInstance(): void
    {
        $first = ReflectionCache::getProperty(ReflectionCacheStubEntity::class, 'name');
        $second = ReflectionCache::getProperty(ReflectionCacheStubEntity::class, 'name');

        $this->assertSame($first, $second);
    }

    public function testGetPropertyReturnsCorrectProperty(): void
    {
        $rp = ReflectionCache::getProperty(ReflectionCacheStubEntity::class, 'name');

        $this->assertInstanceOf(\ReflectionProperty::class, $rp);
        $this->assertSame('name', $rp->getName());
    }

    public function testGetClassCachesDifferentClasses(): void
    {
        $a = ReflectionCache::getClass(\stdClass::class);
        $b = ReflectionCache::getClass(ReflectionCacheStubEntity::class);

        $this->assertNotSame($a, $b);
    }

    public function testGetPropertyCachesDifferentProperties(): void
    {
        $a = ReflectionCache::getProperty(ReflectionCacheStubEntity::class, 'name');
        $b = ReflectionCache::getProperty(ReflectionCacheStubEntity::class, 'value');

        $this->assertNotSame($a, $b);
    }
}

class ReflectionCacheStubEntity {
    public string $name = '';

    public int $value = 0;
}
