<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Modules\EntityManager\EntityMetadataRegistry;
use PHPUnit\Framework\TestCase;

#[Entity(tableName: 'registry_test_users')]
class RegistryTestUser
{
    public int $id;

    public string $name;
}

class EntityMetadataRegistryTest extends TestCase
{
    private EntityMetadataRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new EntityMetadataRegistry();
    }

    public function testGetMetadata(): void
    {
        $metadata = $this->registry->getMetadata(RegistryTestUser::class);

        $this->assertEquals('registry_test_users', $metadata->getTableName());
        $this->assertEquals(RegistryTestUser::class, $metadata->getClassName());
    }

    public function testGetMetadataCaching(): void
    {
        $metadata1 = $this->registry->getMetadata(RegistryTestUser::class);
        $metadata2 = $this->registry->getMetadata(RegistryTestUser::class);

        // Should be the same instance (cached)
        $this->assertSame($metadata1, $metadata2);
    }

    public function testHasMetadata(): void
    {
        $this->assertFalse($this->registry->hasMetadata(RegistryTestUser::class));

        $this->registry->getMetadata(RegistryTestUser::class);

        $this->assertTrue($this->registry->hasMetadata(RegistryTestUser::class));
    }

    public function testClearMetadata(): void
    {
        $this->registry->getMetadata(RegistryTestUser::class);
        $this->assertTrue($this->registry->hasMetadata(RegistryTestUser::class));

        $this->registry->clearMetadata(RegistryTestUser::class);
        $this->assertFalse($this->registry->hasMetadata(RegistryTestUser::class));
    }

    public function testClearAll(): void
    {
        $this->registry->getMetadata(RegistryTestUser::class);
        $this->assertTrue($this->registry->hasMetadata(RegistryTestUser::class));

        $this->registry->clearAll();
        $this->assertFalse($this->registry->hasMetadata(RegistryTestUser::class));
    }

    public function testGetTableName(): void
    {
        $tableName = $this->registry->getTableName(RegistryTestUser::class);

        $this->assertEquals('registry_test_users', $tableName);
    }

    public function testIsEntity(): void
    {
        $this->assertTrue($this->registry->isEntity(RegistryTestUser::class));
        $this->assertFalse($this->registry->isEntity(\stdClass::class));
    }
}
