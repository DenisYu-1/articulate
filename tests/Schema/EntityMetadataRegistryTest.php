<?php

namespace Articulate\Tests\Schema;

use Articulate\Schema\EntityMetadata;
use Articulate\Schema\EntityMetadataRegistry;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestEntity;
use Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities\TestSecondEntity;
use PHPUnit\Framework\TestCase;

class EntityMetadataRegistryTest extends TestCase {
    public function testGetMetadataReturnsMetadata(): void
    {
        $registry = new EntityMetadataRegistry();

        $metadata = $registry->getMetadata(TestEntity::class);

        $this->assertInstanceOf(EntityMetadata::class, $metadata);
    }

    public function testGetMetadataCachesResult(): void
    {
        $registry = new EntityMetadataRegistry();

        $first = $registry->getMetadata(TestEntity::class);
        $second = $registry->getMetadata(TestEntity::class);

        $this->assertSame($first, $second);
    }

    public function testHasMetadata(): void
    {
        $registry = new EntityMetadataRegistry();

        $this->assertFalse($registry->hasMetadata(TestEntity::class));

        $registry->getMetadata(TestEntity::class);

        $this->assertTrue($registry->hasMetadata(TestEntity::class));
    }

    public function testClearMetadata(): void
    {
        $registry = new EntityMetadataRegistry();

        $registry->getMetadata(TestEntity::class);
        $this->assertTrue($registry->hasMetadata(TestEntity::class));

        $registry->clearMetadata(TestEntity::class);
        $this->assertFalse($registry->hasMetadata(TestEntity::class));
    }

    public function testGetTableName(): void
    {
        $registry = new EntityMetadataRegistry();

        $tableName = $registry->getTableName(TestSecondEntity::class);

        $this->assertEquals('test_entity', $tableName);
    }
}
