<?php

namespace Norm\Tests\Attributes\ManyToOne;

use Norm\Attributes\Entity;
use Norm\Attributes\Property;
use Norm\Attributes\Reflection\ReflectionEntity;
use Norm\Attributes\Reflection\ReflectionRelation;
use Norm\Attributes\Relations\ManyToOne;
use Norm\Attributes\Relations\OneToMany;
use Norm\Tests\AbstractTestCase;
use Norm\Tests\Attributes\OneToOne\OneToOneRelatedEntity;
use RuntimeException;

class NonEntity {
    #[Property]
    private int $id;
}

#[Entity]
class RelatedEntity {
    #[Property]
    private int $id;
}

#[Entity]
class ManyToOneRelatedEntity {
    #[Property]
    private int $id;

    #[OneToMany]
    private ManyToOneTest $oneToMany;
}

#[Entity(tableName: 'test')]
class ManyToOneTest extends AbstractTestCase
{
    #[Property]
    private int $id;

    #[ManyToOne]
    private RelatedEntity $relatedEntity;

    public function testManyToOne()
    {
        $entity = new ReflectionEntity(static::class);
        $properties = $entity->getProperties();

        $propertyToTest = null;
        foreach ($properties as $property) {
            if ($property->getName() === 'relatedEntity') {
                /** @var ManyToOne $attribute */
                $attribute = $property->getAttributes(ManyToOne::class);
                $propertyToTest = new ReflectionRelation($attribute[0]->newInstance(), $property);
                break;
            }
        }

        $this->assertEquals(RelatedEntity::class, $propertyToTest->getTargetEntity());
        $this->assertTrue($propertyToTest->isForeignKeyRequired());
        $this->assertEquals('many_to_one_test_id', $propertyToTest->getInversedBy());
    }

    #[ManyToOne]
    private NonEntity $relatedNonEntity;

    public function testRelationToNonEntity()
    {
        $entity = new ReflectionEntity(static::class);
        $properties = $entity->getProperties();

        $propertyToTest = null;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Non-entity found in relation');
        foreach ($properties as $property) {
            if ($property->getName() === 'relatedNonEntity') {
                /** @var ManyToOne $attribute */
                $attribute = $property->getAttributes(ManyToOne::class);
                $propertyToTest = new ReflectionRelation($attribute[0]->newInstance(), $property);
                break;
            }
        }

        $this->assertEquals(NonEntity::class, $propertyToTest->getTargetEntity());
    }

    #[ManyToOne(targetEntity: RelatedEntity::class)]
    private OneToOneRelatedEntity $relatedEntity2;

    public function testOverwrittenRelatedEntity()
    {
        $entity = new ReflectionEntity(static::class);
        $properties = $entity->getProperties();

        $propertyToTest = null;
        foreach ($properties as $property) {
            if ($property->getName() === 'relatedEntity2') {
                /** @var ManyToOne $attribute */
                $attribute = $property->getAttributes(ManyToOne::class);
                $propertyToTest = new ReflectionRelation($attribute[0]->newInstance(), $property);
                break;
            }
        }

        $this->assertEquals(RelatedEntity::class, $propertyToTest->getTargetEntity());
    }

    #[ManyToOne(inversedBy: 'relatedEntity')]
    private RelatedEntity $relatedEntity3;

    public function testInversedBySpecified()
    {
        $entity = new ReflectionEntity(static::class);
        $properties = $entity->getProperties();

        $propertyToTest = null;
        foreach ($properties as $property) {
            if ($property->getName() === 'relatedEntity3') {
                /** @var ManyToOne $attribute */
                $attribute = $property->getAttributes(ManyToOne::class);
                $propertyToTest = new ReflectionRelation($attribute[0]->newInstance(), $property);
                break;
            }
        }

        $this->assertEquals(RelatedEntity::class, $propertyToTest->getTargetEntity());
        $this->assertEquals('relatedEntity', $propertyToTest->getInversedBy());
    }
}
