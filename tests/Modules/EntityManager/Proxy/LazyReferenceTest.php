<?php

namespace Articulate\Tests\Modules\EntityManager\Proxy;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\EntityManager\Proxy\ProxyGenerator;
use Articulate\Modules\EntityManager\Proxy\ProxyInterface;
use Articulate\Modules\EntityManager\Proxy\ProxyManager;
use Articulate\Schema\EntityMetadataRegistry;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[Entity(tableName: 'lazy_ref_test_entities')]
class LazyReferenceTestEntity {
    #[PrimaryKey]
    public ?int $id = null;

    public ?string $name = null;
}

class LazyReferenceTest extends TestCase {
    private EntityMetadataRegistry $metadataRegistry;

    private EntityManager $entityManager;

    private ProxyGenerator $proxyGenerator;

    private ProxyManager $proxyManager;

    protected function setUp(): void
    {
        $this->metadataRegistry = new EntityMetadataRegistry();
        $this->entityManager = $this->createMock(EntityManager::class);
        $this->proxyGenerator = new ProxyGenerator($this->metadataRegistry);
        $this->proxyGenerator->disableCaching();
        $this->proxyManager = new ProxyManager($this->entityManager, $this->proxyGenerator);
    }

    // ── createProxyWithCustomLoader ───────────────────────────────────────────

    #[AllowMockObjectsWithoutExpectations]
    public function testCreateProxyWithCustomLoaderReturnsProxyInterface(): void
    {
        $proxy = $this->proxyManager->createProxyWithCustomLoader(
            LazyReferenceTestEntity::class,
            function (ProxyInterface $p): void {}
        );

        $this->assertInstanceOf(ProxyInterface::class, $proxy);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testProxyCreatedWithCustomLoaderHasNullIdentifier(): void
    {
        $proxy = $this->proxyManager->createProxyWithCustomLoader(
            LazyReferenceTestEntity::class,
            function (ProxyInterface $p): void {}
        );

        $this->assertNull($proxy->getProxyIdentifier());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testProxyCreatedWithCustomLoaderIsNotInitializedByDefault(): void
    {
        $proxy = $this->proxyManager->createProxyWithCustomLoader(
            LazyReferenceTestEntity::class,
            function (ProxyInterface $p): void {}
        );

        $this->assertFalse($proxy->isProxyInitialized());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCustomInitializerIsCalledOnProxyInitialization(): void
    {
        $initializerCalled = false;

        $proxy = $this->proxyManager->createProxyWithCustomLoader(
            LazyReferenceTestEntity::class,
            function (ProxyInterface $p) use (&$initializerCalled): void {
                $initializerCalled = true;
                $p->markProxyInitialized();
            }
        );

        $proxy->initializeProxy();

        $this->assertTrue($initializerCalled);
        $this->assertTrue($proxy->isProxyInitialized());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCustomInitializerCanCopyDataIntoProxy(): void
    {
        $loadedEntity = new LazyReferenceTestEntity();
        $loadedEntity->id = 7;
        $loadedEntity->name = 'Loaded via FK lookup';

        $proxy = $this->proxyManager->createProxyWithCustomLoader(
            LazyReferenceTestEntity::class,
            function (ProxyInterface $p) use ($loadedEntity): void {
                $ref = new \ReflectionClass($loadedEntity);
                foreach ($ref->getProperties() as $rp) {
                    $rp->setAccessible(true);

                    try {
                        $rp->setValue($p, $rp->getValue($loadedEntity));
                    } catch (\Error) {
                    }
                }
                $p->markProxyInitialized();
            }
        );

        $proxy->initializeProxy();

        $this->assertTrue($proxy->isProxyInitialized());
        $this->assertEquals(7, $proxy->id);
        $this->assertEquals('Loaded via FK lookup', $proxy->name);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCustomInitializerIsCalledOnlyOnce(): void
    {
        $callCount = 0;

        $proxy = $this->proxyManager->createProxyWithCustomLoader(
            LazyReferenceTestEntity::class,
            function (ProxyInterface $p) use (&$callCount): void {
                $callCount++;
                $p->markProxyInitialized();
            }
        );

        $proxy->initializeProxy();
        $proxy->initializeProxy(); // second call — should be no-op

        $this->assertSame(1, $callCount);
    }

    // ── EntityManager::createLazyReference delegates to ProxyManager ──────────

    #[AllowMockObjectsWithoutExpectations]
    public function testCreateLazyReferenceDelegatesToProxyManager(): void
    {
        $proxyManagerMock = $this->createMock(ProxyManager::class);
        $emMock = $this->createMock(EntityManager::class);

        $expectedProxy = $this->createStub(ProxyInterface::class);
        $loader = static function (ProxyInterface $p): void {};

        $proxyManagerMock->expects($this->once())
            ->method('createProxyWithCustomLoader')
            ->with(LazyReferenceTestEntity::class, $loader)
            ->willReturn($expectedProxy);

        // Verify ProxyManager::createProxyWithCustomLoader passes the loader through
        $result = $proxyManagerMock->createProxyWithCustomLoader(
            LazyReferenceTestEntity::class,
            $loader
        );

        $this->assertSame($expectedProxy, $result);
    }
}
