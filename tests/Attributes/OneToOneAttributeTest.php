<?php

namespace Articulate\Tests\Attributes;

use Articulate\Attributes\Relations\OneToOne;
use PHPUnit\Framework\TestCase;

class OneToOneAttributeTest extends TestCase
{
    public function testOneToOneAttributeDefaultConstructor(): void
    {
        $relation = new OneToOne();

        $this->assertNull($relation->targetEntity);
        $this->assertNull($relation->ownedBy);
        $this->assertNull($relation->referencedBy);
        $this->assertNull($relation->column);
        $this->assertTrue($relation->foreignKey);
    }

    public function testOneToOneAttributeWithTargetEntity(): void
    {
        $relation = new OneToOne(targetEntity: 'UserProfile');

        $this->assertEquals('UserProfile', $relation->targetEntity);
        $this->assertNull($relation->ownedBy);
        $this->assertNull($relation->referencedBy);
        $this->assertNull($relation->column);
        $this->assertTrue($relation->foreignKey);
    }

    public function testOneToOneAttributeWithOwnedBy(): void
    {
        $relation = new OneToOne(ownedBy: 'user');

        $this->assertNull($relation->targetEntity);
        $this->assertEquals('user', $relation->ownedBy);
        $this->assertNull($relation->referencedBy);
        $this->assertNull($relation->column);
        $this->assertFalse($relation->foreignKey); // ownedBy disables foreignKey
    }

    public function testOneToOneAttributeWithReferencedBy(): void
    {
        $relation = new OneToOne(referencedBy: 'profile_id');

        $this->assertNull($relation->targetEntity);
        $this->assertNull($relation->ownedBy);
        $this->assertEquals('profile_id', $relation->referencedBy);
        $this->assertNull($relation->column);
        $this->assertTrue($relation->foreignKey);
    }

    public function testOneToOneAttributeWithCustomColumn(): void
    {
        $relation = new OneToOne(column: 'custom_user_id');

        $this->assertNull($relation->targetEntity);
        $this->assertNull($relation->ownedBy);
        $this->assertNull($relation->referencedBy);
        $this->assertEquals('custom_user_id', $relation->column);
        $this->assertTrue($relation->foreignKey);
    }

    public function testOneToOneAttributeWithForeignKeyDisabled(): void
    {
        $relation = new OneToOne(foreignKey: false);

        $this->assertNull($relation->targetEntity);
        $this->assertNull($relation->ownedBy);
        $this->assertNull($relation->referencedBy);
        $this->assertNull($relation->column);
        $this->assertFalse($relation->foreignKey);
    }

    public function testOneToOneAttributeWithAllParameters(): void
    {
        $relation = new OneToOne(
            targetEntity: 'UserProfile',
            ownedBy: 'user',
            referencedBy: 'profile_id',
            column: 'custom_column',
            foreignKey: false
        );

        $this->assertEquals('UserProfile', $relation->targetEntity);
        $this->assertEquals('user', $relation->ownedBy);
        $this->assertEquals('profile_id', $relation->referencedBy);
        $this->assertEquals('custom_column', $relation->column);
        $this->assertFalse($relation->foreignKey); // ownedBy overrides foreignKey
    }

    public function testGetTargetEntity(): void
    {
        $relation = new OneToOne(targetEntity: 'TestEntity');

        $this->assertEquals('TestEntity', $relation->getTargetEntity());
    }

    public function testGetTargetEntityNull(): void
    {
        $relation = new OneToOne();

        $this->assertNull($relation->getTargetEntity());
    }

    public function testGetColumnReturnsColumnProperty(): void
    {
        $relation = new OneToOne(column: 'test_column');

        $this->assertEquals('test_column', $relation->getColumn());
    }

    public function testGetColumnReturnsOwnedByWhenNoColumn(): void
    {
        $relation = new OneToOne(ownedBy: 'owner_property');

        $this->assertEquals('owner_property', $relation->getColumn());
    }

    public function testGetColumnReturnsNullWhenNeitherSet(): void
    {
        $relation = new OneToOne();

        $this->assertNull($relation->getColumn());
    }

    public function testGetColumnPrefersColumnOverOwnedBy(): void
    {
        $relation = new OneToOne(
            ownedBy: 'owner_prop',
            column: 'explicit_column'
        );

        $this->assertEquals('explicit_column', $relation->getColumn());
    }

    public function testOwnedByDisablesForeignKey(): void
    {
        $relation = new OneToOne(ownedBy: 'test', foreignKey: true);

        $this->assertFalse($relation->foreignKey);
    }

    public function testForeignKeyRemainsTrueWithoutOwnedBy(): void
    {
        $relation = new OneToOne(foreignKey: true);

        $this->assertTrue($relation->foreignKey);
    }

    public function testForeignKeyDefaultsToTrue(): void
    {
        $relation = new OneToOne();

        $this->assertTrue($relation->foreignKey);
    }

    public function testImplementsRelationAttributeInterface(): void
    {
        $relation = new OneToOne();

        $this->assertInstanceOf(\Articulate\Attributes\Relations\RelationAttributeInterface::class, $relation);
    }
}