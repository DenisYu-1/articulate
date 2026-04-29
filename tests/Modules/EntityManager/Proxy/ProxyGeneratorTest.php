<?php

namespace Articulate\Tests\Modules\EntityManager\Proxy;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\EntityManager\Proxy\ProxyGenerator;
use Articulate\Modules\EntityManager\Proxy\ProxyInterface;
use Articulate\Modules\EntityManager\Proxy\ProxyManager;
use Articulate\Schema\EntityMetadataRegistry;
use PHPUnit\Framework\TestCase;

#[Entity(tableName: 'test_proxy_entities')]
class ProxyGeneratorTestEntity {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public ?string $name = null;
}

#[Entity(tableName: 'test_proxy_relation_targets')]
class ProxyGeneratorRelationTestRelatedEntity {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public ?string $name = null;
}

#[Entity(tableName: 'test_proxy_relation_entities')]
class ProxyGeneratorRelationTestEntity {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public ?string $name = null;

    #[ManyToOne(targetEntity: ProxyGeneratorRelationTestRelatedEntity::class)]
    public ?ProxyGeneratorRelationTestRelatedEntity $relatedTarget = null;
}

class ProxyGeneratorTest extends TestCase {
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

    public function testGenerateProxyClassIsIdempotent(): void
    {
        $proxyClass1 = $this->generator->generateProxyClass(ProxyGeneratorTestEntity::class);
        $proxyClass2 = $this->generator->generateProxyClass(ProxyGeneratorTestEntity::class);

        $this->assertEquals($proxyClass1, $proxyClass2);
    }

    public function testCreateProxy(): void
    {
        $proxy = $this->generator->createProxy(ProxyGeneratorTestEntity::class, 123, fn () => null, $this);

        $this->assertInstanceOf(ProxyInterface::class, $proxy);
        $this->assertEquals(ProxyGeneratorTestEntity::class, $proxy->getProxyEntityClass());
        $this->assertFalse($proxy->isProxyInitialized());
    }

    public function testRelationAccessLoadsThroughRelationLoaderWithoutInitializingScalarData(): void
    {
        $entityManager = $this->createMock(EntityManager::class);
        $proxyGenerator = new ProxyGenerator($this->metadataRegistry);
        $proxyGenerator->disableCaching();
        $proxyManager = new ProxyManager($entityManager, $proxyGenerator);

        $proxy = $proxyManager->createProxy(ProxyGeneratorRelationTestEntity::class, 123);

        $relatedEntity = new ProxyGeneratorRelationTestRelatedEntity();
        $relatedEntity->id = 456;
        $relatedEntity->name = 'related';

        $entityManager->expects($this->once())
            ->method('loadRelation')
            ->with(
                $this->isInstanceOf(ProxyInterface::class),
                'relatedTarget'
            )
            ->willReturn($relatedEntity);

        $entityManager->expects($this->never())->method('find');

        $result = $proxy->relatedTarget;

        $this->assertSame($relatedEntity, $result);
        $this->assertFalse($proxy->isProxyInitialized());
    }

    public function testRelationAccessLoadsRelationOnlyOncePerProxyInstance(): void
    {
        $entityManager = $this->createMock(EntityManager::class);
        $proxyGenerator = new ProxyGenerator($this->metadataRegistry);
        $proxyGenerator->disableCaching();
        $proxyManager = new ProxyManager($entityManager, $proxyGenerator);

        $proxy = $proxyManager->createProxy(ProxyGeneratorRelationTestEntity::class, 123);

        $relatedEntity = new ProxyGeneratorRelationTestRelatedEntity();
        $relatedEntity->id = 456;

        $entityManager->expects($this->once())
            ->method('loadRelation')
            ->with(
                $this->isInstanceOf(ProxyInterface::class),
                'relatedTarget'
            )
            ->willReturn($relatedEntity);

        $entityManager->expects($this->never())->method('find');

        $first = $proxy->relatedTarget;
        $second = $proxy->relatedTarget;

        $this->assertSame($relatedEntity, $first);
        $this->assertSame($first, $second);
    }

    public function testNonRelationAccessStillInitializesProxy(): void
    {
        $entityManager = $this->createMock(EntityManager::class);
        $proxyGenerator = new ProxyGenerator($this->metadataRegistry);
        $proxyGenerator->disableCaching();
        $proxyManager = new ProxyManager($entityManager, $proxyGenerator);

        $proxy = $proxyManager->createProxy(ProxyGeneratorRelationTestEntity::class, 123);

        $loadedEntity = new ProxyGeneratorRelationTestEntity();
        $loadedEntity->id = 123;
        $loadedEntity->name = 'loaded';

        $entityManager->expects($this->once())
            ->method('find')
            ->with(ProxyGeneratorRelationTestEntity::class, 123)
            ->willReturn($loadedEntity);

        $entityManager->expects($this->never())->method('loadRelation');

        $this->assertEquals('loaded', $proxy->name);
        $this->assertTrue($proxy->isProxyInitialized());
    }

    public function testGenerateProxyClassWithInvalidClassNameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->generator->generateProxyClass('Invalid!Class');
    }
}
