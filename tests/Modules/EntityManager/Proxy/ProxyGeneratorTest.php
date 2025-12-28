<?php

namespace Articulate\Tests\Modules\EntityManager\Proxy;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Modules\EntityManager\EntityMetadataRegistry;
use Articulate\Modules\EntityManager\Proxy\ProxyGenerator;
use PHPUnit\Framework\TestCase;

#[Entity(tableName: 'test_proxy_entities')]
class ProxyGeneratorTestEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public ?string $name = null;
}

class ProxyGeneratorTest extends TestCase
{
    private ProxyGenerator $generator;

    private EntityMetadataRegistry $metadataRegistry;

    protected function setUp(): void
    {
        $this->metadataRegistry = new EntityMetadataRegistry();
        $this->generator = new ProxyGenerator($this->metadataRegistry);
        $this->generator->disableCaching(); // Disable caching for tests
    }

    public function testGenerateProxyClass(): void
    {
        $proxyClass = $this->generator->generateProxyClass(ProxyGeneratorTestEntity::class);

        $this->assertStringStartsWith('Proxy_', $proxyClass);
        $this->assertStringContainsString('ProxyGeneratorTestEntity', $proxyClass);
    }

    public function testGenerateProxyClassUnique(): void
    {
        $proxyClass1 = $this->generator->generateProxyClass(ProxyGeneratorTestEntity::class);
        $proxyClass2 = $this->generator->generateProxyClass(ProxyGeneratorTestEntity::class);

        // Since caching is disabled for tests, each call generates a new class
        $this->assertNotEquals($proxyClass1, $proxyClass2);
    }

    public function testCreateProxy(): void
    {
        $proxy = $this->generator->createProxy(ProxyGeneratorTestEntity::class, 123, fn () => null, $this);

        $this->assertInstanceOf(\Articulate\Modules\EntityManager\Proxy\ProxyInterface::class, $proxy);
        $this->assertEquals(ProxyGeneratorTestEntity::class, $proxy->getProxyEntityClass());
        $this->assertFalse($proxy->isProxyInitialized());
    }
}
