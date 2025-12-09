<?php

namespace Articulate\Tests\Attributes\ManyToOne;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionRelation;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Attributes\Relations\OneToMany;
use Articulate\Tests\AbstractTestCase;
use Articulate\Tests\Attributes\OneToOne\OneToOneRelatedEntity;
use RuntimeException;

class NonEntity {
    #[Property]
    private int $id;
}

#[Entity]
class RelatedEntity {
    #[Property]
    private int $id;

    #[OneToMany(mappedBy: 'customColumn', targetEntity: ManyToOneTest::class)]
    private ManyToOneTest $owners;

    #[OneToMany(mappedBy: 'relatedEntity3', targetEntity: ManyToOneTest::class)]
    private ManyToOneTest $inverseRelated;
}

#[Entity(tableName: 'test')]
class ManyToOneTest extends AbstractTestCase
{
    #[Property]
    private int $id;

    #[ManyToOne]
    private RelatedEntity $relatedEntity;

    #[ManyToOne(nullable: true, foreignKey: false)]
    private RelatedEntity $nullableNoFk;

    #[ManyToOne]
    private ?RelatedEntity $nullableByType;

    #[ManyToOne(column: 'custom_fk', inversedBy: 'owners')]
    private RelatedEntity $customColumn;

    #[ManyToOne(inversedBy: 'inverseRelated')]
    private RelatedEntity $relatedEntity3;

    #[ManyToOne(targetEntity: RelatedEntity::class)]
    private OneToOneRelatedEntity $relatedEntity2;

    #[ManyToOne(nullable: true)]
    private NonEntity $relatedNonEntity;

    public function testDefaultColumnAndForeignKey()
    {
        $relation = $this->relation('relatedEntity');

        $this->assertSame(RelatedEntity::class, $relation->getTargetEntity());
        $this->assertSame('related_entity_id', $relation->getColumnName());
        $this->assertTrue($relation->isForeignKeyRequired());
        $this->assertFalse($relation->isNullable());
        $this->assertNull($relation->getInversedBy());
    }

    public function testRelationToNonEntity()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Non-entity found in relation');

        $this->relation('relatedNonEntity')->getTargetEntity();
    }

    public function testNullableFromAttributeAndForeignKeyToggle()
    {
        $relation = $this->relation('nullableNoFk');

        $this->assertTrue($relation->isNullable());
        $this->assertFalse($relation->isForeignKeyRequired());
    }

    public function testNullableFromType()
    {
        $relation = $this->relation('nullableByType');

        $this->assertTrue($relation->isNullable());
    }

    public function testCustomColumnAndInverse()
    {
        $relation = $this->relation('customColumn');

        $this->assertSame('custom_fk', $relation->getColumnName());
        $this->assertSame('owners', $relation->getInversedBy());
    }

    public function testOverwrittenRelatedEntity()
    {
        $this->assertSame(RelatedEntity::class, $this->relation('relatedEntity2')->getTargetEntity());
    }

    public function testInversedBySpecified()
    {
        $relation = $this->relation('relatedEntity3');

        $this->assertSame(RelatedEntity::class, $relation->getTargetEntity());
        $this->assertSame('inverseRelated', $relation->getInversedBy());
    }

    private function relation(string $propertyName): ReflectionRelation
    {
        $entity = new ReflectionEntity(static::class);
        $property = $entity->getProperty($propertyName);
        /** @var ManyToOne $attribute */
        $attribute = $property->getAttributes(ManyToOne::class)[0]->newInstance();

        return new ReflectionRelation($attribute, $property);
    }
}
