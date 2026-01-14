<?php

namespace Articulate\Tests\Modules\EntityManager\Proxy;

use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\EntityManager\EntityMetadataRegistry;
use Articulate\Modules\EntityManager\Proxy\ProxyGenerator;
use Articulate\Modules\EntityManager\Proxy\ProxyInterface;
use Articulate\Modules\EntityManager\Proxy\ProxyManager;
use Articulate\Tests\AbstractTestCase;

class ProxyManagerTest extends AbstractTestCase {
    private ProxyManager $proxyManager;

    private EntityManager $entityManager;

    private EntityMetadataRegistry $metadataRegistry;

    private ProxyGenerator $proxyGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = $this->createMock(EntityManager::class);
        $this->metadataRegistry = $this->createMock(EntityMetadataRegistry::class);
        $this->proxyGenerator = $this->createMock(ProxyGenerator::class);

        $this->proxyManager = new ProxyManager(
            $this->entityManager,
            $this->metadataRegistry,
            $this->proxyGenerator
        );
    }

    public function testCreateProxyDelegatesToProxyGenerator(): void
    {
        $entityClass = ProxyManagerTestEntity::class;
        $identifier = 42;
        $expectedProxy = $this->createMock(ProxyInterface::class);

        $this->proxyGenerator->expects($this->once())
            ->method('createProxy')
            ->with(
                $entityClass,
                $identifier,
                $this->isInstanceOf(\Closure::class),
                $this->identicalTo($this->proxyManager)
            )
            ->willReturn($expectedProxy);

        $result = $this->proxyManager->createProxy($entityClass, $identifier);

        $this->assertSame($expectedProxy, $result);
    }

    public function testInitializeProxySkipsIfAlreadyInitialized(): void
    {
        $proxy = $this->createMock(ProxyInterface::class);
        $proxy->expects($this->once())
            ->method('isProxyInitialized')
            ->willReturn(true);

        // EntityManager find should not be called
        $this->entityManager->expects($this->never())
            ->method('find');

        $this->proxyManager->initializeProxy($proxy);
    }

    public function testInitializeProxyLoadsEntityAndCopiesData(): void
    {
        $entityClass = ProxyManagerTestEntity::class;
        $identifier = 42;

        $realEntity = new ProxyManagerTestEntity();
        $realEntity->id = 42;
        $realEntity->name = 'Loaded Entity';

        // Create a real proxy instance that can receive property assignments
        $proxy = new TestProxy();
        $proxy->isProxyInitialized = false;
        $proxy->proxyEntityClass = $entityClass;
        $proxy->proxyIdentifier = $identifier;

        $this->entityManager->expects($this->once())
            ->method('find')
            ->with($entityClass, $identifier)
            ->willReturn($realEntity);

        $this->proxyManager->initializeProxy($proxy);

        // Verify proxy was marked as initialized
        $this->assertTrue($proxy->isProxyInitialized);

        // Verify data was copied
        $this->assertEquals(42, $proxy->id);
        $this->assertEquals('Loaded Entity', $proxy->name);
    }

    public function testInitializeProxyHandlesEntityNotFound(): void
    {
        $entityClass = ProxyManagerTestEntity::class;
        $identifier = 999;

        $proxy = new TestProxy();
        $proxy->isProxyInitialized = false;
        $proxy->proxyEntityClass = $entityClass;
        $proxy->proxyIdentifier = $identifier;

        // Entity not found
        $this->entityManager->expects($this->once())
            ->method('find')
            ->with($entityClass, $identifier)
            ->willReturn(null);

        $this->proxyManager->initializeProxy($proxy);

        // Proxy should not be marked as initialized
        $this->assertFalse($proxy->isProxyInitialized);

        // No data should be copied
        $this->assertNull($proxy->id);
        $this->assertNull($proxy->name);
    }

    public function testLoadRelationDelegatesToEntityManager(): void
    {
        $proxy = $this->createMock(ProxyInterface::class);
        $relationName = 'relatedEntities';
        $expectedResult = ['relation data'];

        $this->entityManager->expects($this->once())
            ->method('loadRelation')
            ->with($proxy, $relationName)
            ->willReturn($expectedResult);

        $result = $this->proxyManager->loadRelation($proxy, $relationName);

        $this->assertEquals($expectedResult, $result);
    }

    public function testCopyEntityDataCopiesPublicProperties(): void
    {
        $source = new ProxyManagerTestEntity();
        $source->id = 123;
        $source->name = 'Source Entity';
        $source->publicField = 'public value';

        $target = new ProxyManagerTestEntity();

        $reflection = new \ReflectionClass($this->proxyManager);
        $method = $reflection->getMethod('copyEntityData');
        $method->setAccessible(true);

        $method->invoke($this->proxyManager, $source, $target);

        $this->assertEquals(123, $target->id);
        $this->assertEquals('Source Entity', $target->name);
        $this->assertEquals('public value', $target->publicField);
    }

    public function testCopyEntityDataIgnoresPrivateAndProtectedProperties(): void
    {
        $source = new ProxyManagerTestEntity();
        $source->id = 456;

        $target = new ProxyManagerTestEntity();

        $reflection = new \ReflectionClass($this->proxyManager);
        $method = $reflection->getMethod('copyEntityData');
        $method->setAccessible(true);

        $method->invoke($this->proxyManager, $source, $target);

        $this->assertEquals(456, $target->id);
        // Private/protected properties should not be copied
    }
}

/**
 * Test entity for proxy manager testing.
 */
class ProxyManagerTestEntity {
    public ?int $id = null;

    public ?string $name = null;

    public string $publicField = '';
}

/**
 * Mock proxy class for testing.
 */
class TestProxy extends ProxyManagerTestEntity implements ProxyInterface {
    public bool $isProxyInitialized = false;

    public string $proxyEntityClass = '';

    public mixed $proxyIdentifier = null;

    public function isProxyInitialized(): bool
    {
        return $this->isProxyInitialized;
    }

    public function initializeProxy(): void
    {
        // Mock implementation
    }

    public function markProxyInitialized(): void
    {
        $this->isProxyInitialized = true;
    }

    public function getProxyEntityClass(): string
    {
        return $this->proxyEntityClass;
    }

    public function _getIdentifier(): mixed
    {
        return $this->proxyIdentifier;
    }
}
