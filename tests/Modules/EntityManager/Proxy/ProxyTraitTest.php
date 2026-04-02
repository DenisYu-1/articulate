<?php

namespace Articulate\Tests\Modules\EntityManager\Proxy;

use Articulate\Modules\EntityManager\Proxy\ProxyTrait;
use PHPUnit\Framework\TestCase;

class ProxyTraitTestEntity {
    use ProxyTrait;

    public ?int $id = null;
}

class ProxyTraitBaseEntity {
    public function baseMethod(): string
    {
        return 'base';
    }
}

class ProxyTraitCallTestEntity extends ProxyTraitBaseEntity {
    use ProxyTrait;

    public ?int $id = null;
}

class ProxyTraitTest extends TestCase {
    public function testProxyInitialization(): void
    {
        $entity = new ProxyTraitTestEntity();
        $entity->_initializeProxy(ProxyTraitTestEntity::class, 123, null, null);

        $this->assertFalse($entity->isProxyInitialized());
        $this->assertEquals(ProxyTraitTestEntity::class, $entity->getProxyEntityClass());
        $this->assertEquals(123, $entity->getProxyIdentifier());
    }

    public function testLazyInitialization(): void
    {
        $initialized = false;
        $initializer = function ($proxy) use (&$initialized) {
            $initialized = true;
        };

        $entity = new ProxyTraitTestEntity();
        $entity->_initializeProxy(ProxyTraitTestEntity::class, 123, $initializer, null);

        // Access property to trigger initialization
        $entity->name;

        $this->assertTrue($initialized);
        $this->assertTrue($entity->isProxyInitialized());
    }

    public function testGetTriggersInitialization(): void
    {
        $initialized = false;
        $initializer = function ($proxy) use (&$initialized) {
            $initialized = true;
        };

        $entity = new ProxyTraitTestEntity();
        $entity->_initializeProxy(ProxyTraitTestEntity::class, 123, $initializer, null);

        $this->assertFalse($initialized);
        $_ = $entity->name;
        $this->assertTrue($initialized);
    }

    public function testSetTriggersInitialization(): void
    {
        $initialized = false;
        $initializer = function ($proxy) use (&$initialized) {
            $initialized = true;
        };

        $entity = new ProxyTraitTestEntity();
        $entity->_initializeProxy(ProxyTraitTestEntity::class, 123, $initializer, null);

        $this->assertFalse($initialized);
        $entity->name = 'test';
        $this->assertTrue($initialized);
    }

    public function testIssetTriggersInitialization(): void
    {
        $initialized = false;
        $initializer = function ($proxy) use (&$initialized) {
            $initialized = true;
        };

        $entity = new ProxyTraitTestEntity();
        $entity->_initializeProxy(ProxyTraitTestEntity::class, 123, $initializer, null);

        $this->assertFalse($initialized);
        isset($entity->name);
        $this->assertTrue($initialized);
    }

    public function testUnsetTriggersInitialization(): void
    {
        $initialized = false;
        $initializer = function ($proxy) use (&$initialized) {
            $initialized = true;
        };

        $entity = new ProxyTraitTestEntity();
        $entity->_initializeProxy(ProxyTraitTestEntity::class, 123, $initializer, null);

        $this->assertFalse($initialized);
        unset($entity->name);
        $this->assertTrue($initialized);
    }

    public function testCallThrowsForNonExistentMethod(): void
    {
        $entity = new ProxyTraitCallTestEntity();
        $entity->_initializeProxy(ProxyTraitCallTestEntity::class, 1, null, null);

        $this->expectException(\BadMethodCallException::class);
        $entity->nonExistentMethod();
    }
}
