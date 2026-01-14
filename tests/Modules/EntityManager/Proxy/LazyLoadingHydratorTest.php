<?php

namespace Articulate\Tests\Modules\EntityManager\Proxy;

use Articulate\Modules\EntityManager\EntityMetadata;
use Articulate\Modules\EntityManager\EntityMetadataRegistry;
use Articulate\Modules\EntityManager\Proxy\LazyLoadingHydrator;
use Articulate\Modules\EntityManager\Proxy\ProxyInterface;
use Articulate\Modules\EntityManager\Proxy\ProxyManager;
use Articulate\Modules\EntityManager\UnitOfWork;
use Articulate\Tests\AbstractTestCase;
use ReflectionClass;
use RuntimeException;

class LazyLoadingHydratorTest extends AbstractTestCase {
    private LazyLoadingHydrator $hydrator;

    private UnitOfWork $unitOfWork;

    private ProxyManager $proxyManager;

    private EntityMetadataRegistry $metadataRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitOfWork = $this->createMock(UnitOfWork::class);
        $this->proxyManager = $this->createMock(ProxyManager::class);
        $this->metadataRegistry = $this->createMock(EntityMetadataRegistry::class);

        $this->hydrator = new LazyLoadingHydrator(
            $this->unitOfWork,
            $this->proxyManager,
            $this->metadataRegistry
        );
    }

    public function testHydrateCreatesProxyWhenIdentifierFound(): void
    {
        $entityClass = TestEntity::class;
        $data = ['id' => 42, 'name' => 'Test Entity'];
        $expectedProxy = $this->createMock(ProxyInterface::class);

        $metadata = $this->createMock(EntityMetadata::class);
        $metadata->method('getPrimaryKeyColumns')->willReturn(['id']);
        $metadata->method('getPropertyNameForColumn')->with('id')->willReturn('id');

        $this->metadataRegistry->expects($this->once())
            ->method('getMetadata')
            ->with($entityClass)
            ->willReturn($metadata);

        $this->proxyManager->expects($this->once())
            ->method('createProxy')
            ->with($entityClass, 42)
            ->willReturn($expectedProxy);

        $result = $this->hydrator->hydrate($entityClass, $data);

        $this->assertSame($expectedProxy, $result);
    }

    public function testHydrateCreatesRealEntityWhenNoIdentifier(): void
    {
        $entityClass = TestEntity::class;
        $data = ['name' => 'Test Entity'];

        $metadata = $this->createMock(EntityMetadata::class);
        $metadata->method('getPrimaryKeyColumns')->willReturn([]);

        $this->metadataRegistry->expects($this->once())
            ->method('getMetadata')
            ->with($entityClass)
            ->willReturn($metadata);

        $this->unitOfWork->expects($this->once())
            ->method('registerManaged')
            ->with($this->isInstanceOf(TestEntity::class), $data);

        $result = $this->hydrator->hydrate($entityClass, $data);

        $this->assertInstanceOf(TestEntity::class, $result);
        $this->assertEquals('Test Entity', $result->name);
    }

    public function testHydrateReturnsExistingEntity(): void
    {
        $entityClass = TestEntity::class;
        $data = ['id' => 42];
        $existingEntity = new TestEntity();

        $result = $this->hydrator->hydrate($entityClass, $data, $existingEntity);

        $this->assertSame($existingEntity, $result);
    }

    public function testExtractThrowsExceptionForUninitializedProxy(): void
    {
        $proxy = $this->createMock(ProxyInterface::class);
        $proxy->method('isProxyInitialized')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot extract data from uninitialized proxy');

        $this->hydrator->extract($proxy);
    }

    public function testExtractReturnsDataFromInitializedProxy(): void
    {
        $entity = new TestEntity();
        $entity->id = 42;
        $entity->name = 'Test Entity';

        $proxy = $this->createMock(ProxyInterface::class);
        $proxy->method('isProxyInitialized')->willReturn(true);

        // Mock reflection behavior
        $reflection = new ReflectionClass($entity);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);

        $expectedData = [];
        foreach ($properties as $property) {
            $name = $property->getName();
            $expectedData[$name] = $property->getValue($entity);
        }

        $result = $this->hydrator->extract($entity);

        $this->assertEquals($expectedData, $result);
    }

    public function testExtractReturnsDataFromRegularEntity(): void
    {
        $entity = new TestEntity();
        $entity->id = 42;
        $entity->name = 'Test Entity';

        $result = $this->hydrator->extract($entity);

        $this->assertEquals([
            'id' => 42,
            'name' => 'Test Entity',
            'userName' => null,
        ], $result);
    }

    public function testHydratePartialDoesNothing(): void
    {
        $entity = new TestEntity();
        $data = ['name' => 'Updated Name'];

        // This should not modify the entity
        $this->hydrator->hydratePartial($entity, $data);

        // Entity should remain unchanged
        $this->assertNull($entity->name);
    }

    public function testExtractIdentifierReturnsNullWhenNoPrimaryKeys(): void
    {
        $entityClass = TestEntity::class;
        $data = ['name' => 'Test'];

        $metadata = $this->createMock(EntityMetadata::class);
        $metadata->method('getPrimaryKeyColumns')->willReturn([]);

        $this->metadataRegistry->expects($this->once())
            ->method('getMetadata')
            ->willReturn($metadata);

        $reflection = new ReflectionClass($this->hydrator);
        $method = $reflection->getMethod('extractIdentifier');
        $method->setAccessible(true);

        $result = $method->invoke($this->hydrator, $entityClass, $data);

        $this->assertNull($result);
    }

    public function testExtractIdentifierReturnsValueFromPropertyName(): void
    {
        $entityClass = TestEntity::class;
        $data = ['id' => 42, 'name' => 'Test'];

        $metadata = $this->createMock(EntityMetadata::class);
        $metadata->method('getPrimaryKeyColumns')->willReturn(['id']);
        $metadata->method('getPropertyNameForColumn')->with('id')->willReturn('id');

        $this->metadataRegistry->expects($this->once())
            ->method('getMetadata')
            ->willReturn($metadata);

        $reflection = new ReflectionClass($this->hydrator);
        $method = $reflection->getMethod('extractIdentifier');
        $method->setAccessible(true);

        $result = $method->invoke($this->hydrator, $entityClass, $data);

        $this->assertEquals(42, $result);
    }

    public function testExtractIdentifierReturnsValueFromColumnName(): void
    {
        $entityClass = TestEntity::class;
        $data = ['user_id' => 42, 'name' => 'Test'];

        $metadata = $this->createMock(EntityMetadata::class);
        $metadata->method('getPrimaryKeyColumns')->willReturn(['user_id']);
        $metadata->method('getPropertyNameForColumn')->with('user_id')->willReturn('id');

        $this->metadataRegistry->expects($this->once())
            ->method('getMetadata')
            ->willReturn($metadata);

        $reflection = new ReflectionClass($this->hydrator);
        $method = $reflection->getMethod('extractIdentifier');
        $method->setAccessible(true);

        $result = $method->invoke($this->hydrator, $entityClass, $data);

        $this->assertEquals(42, $result);
    }

    public function testExtractIdentifierReturnsNullWhenNoDataFound(): void
    {
        $entityClass = TestEntity::class;
        $data = ['name' => 'Test'];

        $metadata = $this->createMock(EntityMetadata::class);
        $metadata->method('getPrimaryKeyColumns')->willReturn(['id']);
        $metadata->method('getPropertyNameForColumn')->with('id')->willReturn('id');

        $this->metadataRegistry->expects($this->once())
            ->method('getMetadata')
            ->willReturn($metadata);

        $reflection = new ReflectionClass($this->hydrator);
        $method = $reflection->getMethod('extractIdentifier');
        $method->setAccessible(true);

        $result = $method->invoke($this->hydrator, $entityClass, $data);

        $this->assertNull($result);
    }

    public function testCreateRealEntitySetsPropertiesAndRegistersInUnitOfWork(): void
    {
        $entityClass = TestEntity::class;
        $data = ['id' => 42, 'user_name' => 'Test User'];

        $this->unitOfWork->expects($this->once())
            ->method('registerManaged')
            ->with($this->isInstanceOf(TestEntity::class), $data);

        $reflection = new ReflectionClass($this->hydrator);
        $method = $reflection->getMethod('createRealEntity');
        $method->setAccessible(true);

        $result = $method->invoke($this->hydrator, $entityClass, $data);

        $this->assertInstanceOf(TestEntity::class, $result);
        $this->assertEquals(42, $result->id);
        $this->assertEquals('Test User', $result->userName);
    }

    public function testSnakeToCamelConvertsCorrectly(): void
    {
        $reflection = new ReflectionClass($this->hydrator);
        $method = $reflection->getMethod('snakeToCamel');
        $method->setAccessible(true);

        $testCases = [
            'simple' => 'simple',
            'snake_case' => 'snakeCase',
            'very_long_snake_case_string' => 'veryLongSnakeCaseString',
            'user_id' => 'userId',
            'test_123_value' => 'test123Value',
        ];

        foreach ($testCases as $input => $expected) {
            $result = $method->invoke($this->hydrator, $input);
            $this->assertEquals($expected, $result, "Failed for input: $input");
        }
    }
}

/**
 * Test entity for hydrator testing.
 */
class TestEntity {
    public ?int $id = null;

    public ?string $name = null;

    public ?string $userName = null;
}
