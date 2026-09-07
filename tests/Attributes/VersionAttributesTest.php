<?php

namespace Articulate\Tests\Attributes;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Version;
use Articulate\Attributes\VersionAware;
use Articulate\Schema\EntityMetadata;
use PHPUnit\Framework\TestCase;

#[Entity(tableName: 'version_attr_checked')]
class VersionAttrCheckedEntity {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    #[Version]
    public int $version = 0;
}

#[Entity(tableName: 'version_attr_aware')]
#[VersionAware(['version'])]
class VersionAttrAwareOnlyEntity {
    #[PrimaryKey]
    public ?int $id = null;
}

#[Entity(tableName: 'version_attr_both')]
#[VersionAware(['version'])]
class VersionAttrContradictingEntity {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    #[Version]
    public int $version = 0;
}

#[Entity(tableName: 'version_attr_bad_type')]
class VersionAttrBadTypeEntity {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    #[Version]
    public string $version = '0';
}

#[Entity(tableName: 'version_attr_none')]
class VersionAttrPlainEntity {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public string $name = '';
}

class VersionAttributesTest extends TestCase {
    public function testCheckedColumnComesFromVersionProperty(): void
    {
        $metadata = new EntityMetadata(VersionAttrCheckedEntity::class);

        $this->assertSame(['version'], $metadata->getCheckedVersionColumns());
        $this->assertSame(['version'], $metadata->getVersionColumns());
    }

    public function testVersionAwareBumpsButNeverChecks(): void
    {
        $metadata = new EntityMetadata(VersionAttrAwareOnlyEntity::class);

        $this->assertSame(['version'], $metadata->getVersionColumns());
        $this->assertSame([], $metadata->getCheckedVersionColumns());
    }

    public function testPlainEntityHasNoVersionColumns(): void
    {
        $metadata = new EntityMetadata(VersionAttrPlainEntity::class);

        $this->assertSame([], $metadata->getVersionColumns());
        $this->assertSame([], $metadata->getCheckedVersionColumns());
    }

    public function testSameColumnInOwnVersionAndOwnVersionAwareThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new EntityMetadata(VersionAttrContradictingEntity::class);
    }

    public function testNonIntVersionPropertyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new EntityMetadata(VersionAttrBadTypeEntity::class);
    }

    public function testVersionPropertyDefaultsToZeroWhenNoExplicitDefaultGiven(): void
    {
        $metadata = new EntityMetadata(VersionAttrCheckedEntity::class);

        $this->assertSame('0', $metadata->getProperty('version')->getDefaultValue());
    }
}
