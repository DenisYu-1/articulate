<?php

namespace Articulate\Tests\Modules\EntityManager\Proxy;

use Articulate\Modules\EntityManager\Proxy\ProxyTrait;
use PHPUnit\Framework\TestCase;

class ProxyTraitTestEntity {
    use ProxyTrait;

    public ?int $id = null;

    // Note: $name is not declared here so __get will be triggered
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

    public function testMagicMethodsTriggerInitialization(): void
    {
        $initialized = false;
        $initializer = function ($proxy) use (&$initialized) {
            $initialized = true;
        };

        $entity = new ProxyTraitTestEntity();
        $entity->_initializeProxy(ProxyTraitTestEntity::class, 123, $initializer, null);

        // Test __get
        $this->assertFalse($initialized);
        $_ = $entity->name;
        $this->assertTrue($initialized);
    }
}
