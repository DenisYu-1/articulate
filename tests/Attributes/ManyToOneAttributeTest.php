<?php

namespace Articulate\Tests\Attributes;

use Articulate\Attributes\Relations\ManyToOne;
use PHPUnit\Framework\TestCase;

class ManyToOneAttributeTest extends TestCase
{
    public function testManyToOneAttributeDefaultConstructor(): void
    {
        $relation = new ManyToOne();

        $this->assertNull($relation->targetEntity);
        $this->assertNull($relation->referencedBy);
        $this->assertNull($relation->column);
        $this->assertNull($relation->nullable);
        $this->assertTrue($relation->foreignKey);
    }

    public function testManyToOneAttributeWithTargetEntity(): void
    {
        $relation = new ManyToOne(targetEntity: 'User');

        $this->assertEquals('User', $relation->targetEntity);
        $this->assertNull($relation->referencedBy);
        $this->assertNull($relation->column);
        $this->assertNull($relation->nullable);
        $this->assertTrue($relation->foreignKey);
    }

    public function testManyToOneAttributeWithReferencedBy(): void
    {
        $relation = new ManyToOne(referencedBy: 'user_id');

        $this->assertNull($relation->targetEntity);
        $this->assertEquals('user_id', $relation->referencedBy);
        $this->assertNull($relation->column);
        $this->assertNull($relation->nullable);
        $this->assertTrue($relation->foreignKey);
    }

    public function testManyToOneAttributeWithColumn(): void
    {
        $relation = new ManyToOne(column: 'author_id');

        $this->assertNull($relation->targetEntity);
        $this->assertNull($relation->referencedBy);
        $this->assertEquals('author_id', $relation->column);
        $this->assertNull($relation->nullable);
        $this->assertTrue($relation->foreignKey);
    }

    public function testManyToOneAttributeWithNullable(): void
    {
        $relation = new ManyToOne(nullable: true);

        $this->assertNull($relation->targetEntity);
        $this->assertNull($relation->referencedBy);
        $this->assertNull($relation->column);
        $this->assertTrue($relation->nullable);
        $this->assertTrue($relation->foreignKey);
    }

    public function testManyToOneAttributeWithForeignKeyDisabled(): void
    {
        $relation = new ManyToOne(foreignKey: false);

        $this->assertNull($relation->targetEntity);
        $this->assertNull($relation->referencedBy);
        $this->assertNull($relation->column);
        $this->assertNull($relation->nullable);
        $this->assertFalse($relation->foreignKey);
    }

    public function testManyToOneAttributeWithAllParameters(): void
    {
        $relation = new ManyToOne(
            targetEntity: 'User',
            referencedBy: 'user_id',
            column: 'author_id',
            nullable: false,
            foreignKey: true
        );

        $this->assertEquals('User', $relation->targetEntity);
        $this->assertEquals('user_id', $relation->referencedBy);
        $this->assertEquals('author_id', $relation->column);
        $this->assertFalse($relation->nullable);
        $this->assertTrue($relation->foreignKey);
    }

    public function testGetTargetEntity(): void
    {
        $relation = new ManyToOne(targetEntity: 'TestEntity');

        $this->assertEquals('TestEntity', $relation->getTargetEntity());
    }

    public function testGetTargetEntityNull(): void
    {
        $relation = new ManyToOne();

        $this->assertNull($relation->getTargetEntity());
    }

    public function testGetColumnReturnsColumnProperty(): void
    {
        $relation = new ManyToOne(column: 'test_column');

        $this->assertEquals('test_column', $relation->getColumn());
    }

    public function testGetColumnReturnsNull(): void
    {
        $relation = new ManyToOne();

        $this->assertNull($relation->getColumn());
    }

    public function testNullableCanBeFalse(): void
    {
        $relation = new ManyToOne(nullable: false);

        $this->assertFalse($relation->nullable);
    }

    public function testNullableCanBeTrue(): void
    {
        $relation = new ManyToOne(nullable: true);

        $this->assertTrue($relation->nullable);
    }

    public function testImplementsRelationAttributeInterface(): void
    {
        $relation = new ManyToOne();

        $this->assertInstanceOf(\Articulate\Attributes\Relations\RelationAttributeInterface::class, $relation);
    }
}