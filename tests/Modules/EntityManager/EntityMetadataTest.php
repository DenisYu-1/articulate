<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\OneToMany;
use Articulate\Modules\EntityManager\EntityMetadata;
use PHPUnit\Framework\TestCase;

#[Entity(tableName: 'test_users')]
class TestEntityMetadataUser
{
    #[PrimaryKey]
    public int $id;

    #[Property(name: 'first_name', nullable: false)]
    public string $firstName;

    #[Property(name: 'last_name')]
    public ?string $lastName;

    #[OneToMany(targetEntity: TestEntityMetadataPost::class, ownedBy: 'author')]
    public array $posts;
}

#[Entity]
class TestEntityMetadataPost
{
    #[PrimaryKey]
    public int $id;

    #[Property]
    public string $title;

    #[Property(type: 'text')]
    public string $content;

    #[Property(name: 'user_id')]
    public int $authorId;
}

class EntityMetadataTest extends TestCase
{
    public function testGetTableNameFromAttribute(): void
    {
        $metadata = new EntityMetadata(TestEntityMetadataUser::class);

        $this->assertEquals('test_users', $metadata->getTableName());
    }

    public function testGetTableNameFromConvention(): void
    {
        $metadata = new EntityMetadata(TestEntityMetadataPost::class);

        $this->assertEquals('test_entity_metadata_post', $metadata->getTableName());
    }

    public function testGetPrimaryKeyColumns(): void
    {
        $metadata = new EntityMetadata(TestEntityMetadataUser::class);

        $this->assertEquals(['id'], $metadata->getPrimaryKeyColumns());
    }

    public function testGetProperties(): void
    {
        $metadata = new EntityMetadata(TestEntityMetadataUser::class);

        $properties = $metadata->getProperties();

        $this->assertCount(3, $properties); // id, firstName, lastName
        $this->assertArrayHasKey('firstName', $properties);
        $this->assertArrayHasKey('lastName', $properties);
        $this->assertArrayHasKey('id', $properties);
    }

    public function testGetProperty(): void
    {
        $metadata = new EntityMetadata(TestEntityMetadataUser::class);

        $property = $metadata->getProperty('firstName');

        $this->assertNotNull($property);
        $this->assertEquals('first_name', $property->getColumnName());
        $this->assertFalse($property->isNullable());
    }

    public function testGetRelations(): void
    {
        $metadata = new EntityMetadata(TestEntityMetadataUser::class);

        $relations = $metadata->getRelations();

        $this->assertCount(1, $relations);
        $this->assertArrayHasKey('posts', $relations);
    }

    public function testGetRelation(): void
    {
        $metadata = new EntityMetadata(TestEntityMetadataUser::class);

        $relation = $metadata->getRelation('posts');

        $this->assertNotNull($relation);
        $this->assertEquals(TestEntityMetadataPost::class, $relation->getTargetEntity());
    }

    public function testHasProperty(): void
    {
        $metadata = new EntityMetadata(TestEntityMetadataUser::class);

        $this->assertTrue($metadata->hasProperty('firstName'));
        $this->assertFalse($metadata->hasProperty('nonexistent'));
    }

    public function testHasRelation(): void
    {
        $metadata = new EntityMetadata(TestEntityMetadataUser::class);

        $this->assertTrue($metadata->hasRelation('posts'));
        $this->assertFalse($metadata->hasRelation('nonexistent'));
    }

    public function testGetColumnName(): void
    {
        $metadata = new EntityMetadata(TestEntityMetadataUser::class);

        $this->assertEquals('first_name', $metadata->getColumnName('firstName'));
        $this->assertEquals('last_name', $metadata->getColumnName('lastName'));
        $this->assertNull($metadata->getColumnName('nonexistent'));
    }

    public function testGetPropertyNameForColumn(): void
    {
        $metadata = new EntityMetadata(TestEntityMetadataUser::class);

        $this->assertEquals('firstName', $metadata->getPropertyNameForColumn('first_name'));
        $this->assertNull($metadata->getPropertyNameForColumn('nonexistent'));
    }

    public function testGetColumnNames(): void
    {
        $metadata = new EntityMetadata(TestEntityMetadataUser::class);

        $columns = $metadata->getColumnNames();

        $this->assertContains('first_name', $columns);
        $this->assertContains('last_name', $columns);
    }

    public function testNonEntityClassThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is not an entity');

        new EntityMetadata(\stdClass::class);
    }
}
