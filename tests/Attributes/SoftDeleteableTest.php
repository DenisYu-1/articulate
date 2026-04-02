<?php

namespace Articulate\Tests\Attributes;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\SoftDeleteable;
use PHPUnit\Framework\TestCase;

#[Entity]
#[SoftDeleteable]
class SoftDeleteableDefaultEntity {
    #[PrimaryKey]
    public int $id;

    #[Property]
    public ?\DateTime $deletedAt = null;
}

#[Entity]
#[SoftDeleteable(fieldName: 'archivedAt', columnName: 'archived_at')]
class SoftDeleteableCustomEntity {
    #[PrimaryKey]
    public int $id;

    #[Property]
    public ?\DateTime $archivedAt = null;
}

#[Entity]
class EntityWithoutSoftDeleteable {
    #[PrimaryKey]
    public int $id;
}

class SoftDeleteableTest extends TestCase {
    public function testDefaultFieldAndColumnNames(): void
    {
        $attribute = new SoftDeleteable();

        $this->assertSame('deletedAt', $attribute->fieldName);
        $this->assertSame('deleted_at', $attribute->columnName);
    }

    public function testCustomFieldAndColumnNames(): void
    {
        $attribute = new SoftDeleteable(fieldName: 'archivedAt', columnName: 'archived_at');

        $this->assertSame('archivedAt', $attribute->fieldName);
        $this->assertSame('archived_at', $attribute->columnName);
    }

    public function testGetSoftDeleteableAttributeWithDefaults(): void
    {
        $entity = new ReflectionEntity(SoftDeleteableDefaultEntity::class);
        $attribute = $entity->getSoftDeleteableAttribute();

        $this->assertNotNull($attribute);
        $this->assertSame('deletedAt', $attribute->fieldName);
        $this->assertSame('deleted_at', $attribute->columnName);
    }

    public function testGetSoftDeleteableAttributeWithCustomValues(): void
    {
        $entity = new ReflectionEntity(SoftDeleteableCustomEntity::class);
        $attribute = $entity->getSoftDeleteableAttribute();

        $this->assertNotNull($attribute);
        $this->assertSame('archivedAt', $attribute->fieldName);
        $this->assertSame('archived_at', $attribute->columnName);
    }

    public function testGetSoftDeleteableAttributeReturnsNullForNonSoftDeleteable(): void
    {
        $entity = new ReflectionEntity(EntityWithoutSoftDeleteable::class);

        $this->assertNull($entity->getSoftDeleteableAttribute());
    }
}
