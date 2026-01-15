<?php

namespace Articulate\Tests\Attributes;

use Articulate\Attributes\Relations\OneToMany;
use Articulate\Attributes\Relations\RelationAttributeInterface;
use PHPUnit\Framework\TestCase;

class OneToManyAttributeTest extends TestCase {
    public function testOneToManyAttributeDefaultConstructor(): void
    {
        $relation = new OneToMany();

        $this->assertNull($relation->targetEntity);
        $this->assertNull($relation->ownedBy);
    }

    public function testOneToManyAttributeWithTargetEntity(): void
    {
        $relation = new OneToMany(targetEntity: 'Comment');

        $this->assertEquals('Comment', $relation->targetEntity);
        $this->assertNull($relation->ownedBy);
    }

    public function testOneToManyAttributeWithOwnedBy(): void
    {
        $relation = new OneToMany(ownedBy: 'post');

        $this->assertNull($relation->targetEntity);
        $this->assertEquals('post', $relation->ownedBy);
    }

    public function testOneToManyAttributeWithAllParameters(): void
    {
        $relation = new OneToMany(
            targetEntity: 'Comment',
            ownedBy: 'post'
        );

        $this->assertEquals('Comment', $relation->targetEntity);
        $this->assertEquals('post', $relation->ownedBy);
    }

    public function testGetTargetEntity(): void
    {
        $relation = new OneToMany(targetEntity: 'TestEntity');

        $this->assertEquals('TestEntity', $relation->getTargetEntity());
    }

    public function testGetTargetEntityNull(): void
    {
        $relation = new OneToMany();

        $this->assertNull($relation->getTargetEntity());
    }

    public function testGetColumnAlwaysReturnsNull(): void
    {
        $relation = new OneToMany();
        $this->assertNull($relation->getColumn());

        $relation = new OneToMany(ownedBy: 'test');
        $this->assertNull($relation->getColumn());
    }

    public function testImplementsRelationAttributeInterface(): void
    {
        $relation = new OneToMany();

        $this->assertInstanceOf(RelationAttributeInterface::class, $relation);
    }
}
