<?php

namespace Articulate\Tests\Attributes\OneToOne;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionRelation;
use Articulate\Attributes\Relations\OneToOne;
use Articulate\Tests\AbstractTestCase;
use RuntimeException;

class NonEntity
{
    #[Property]
    private int $id;
}

#[Entity]
class RelatedEntity
{
    #[Property]
    private int $id;
}

#[Entity]
class OneToOneRelatedEntity
{
    #[Property]
    private int $id;

    #[OneToOne]
    private OneToOneTest $oneToOne;
}

#[Entity(tableName: 'test')]
class OneToOneTest extends AbstractTestCase
{
    #[Property]
    private int $id;

    #[OneToOne(referencedBy: 'test')]
    private RelatedEntity $relatedEntity;

    public function testOneToOne()
    {
        $entity = new ReflectionEntity(static::class);
        $properties = $entity->getProperties();

        $propertyToTest = null;
        foreach ($properties as $property) {
            if ($property->getName() === 'relatedEntity') {
                /** @var OneToOne $attribute */
                $attribute = $property->getAttributes(OneToOne::class);
                $propertyToTest = new ReflectionRelation($attribute[0]->newInstance(), $property);

                break;
            }
        }

        $this->assertEquals(RelatedEntity::class, $propertyToTest->getTargetEntity());
        $this->assertTrue($propertyToTest->isForeignKeyRequired());
        $this->assertEquals('related_entity_id', $propertyToTest->getMappedBy());
        $this->assertEquals('test', $propertyToTest->getInversedBy());
    }

    #[OneToOne]
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
                /** @var OneToOne $attribute */
                $attribute = $property->getAttributes(OneToOne::class);
                $propertyToTest = new ReflectionRelation($attribute[0]->newInstance(), $property);

                break;
            }
        }

        $this->assertEquals(NonEntity::class, $propertyToTest->getTargetEntity());
    }

    #[OneToOne(targetEntity: RelatedEntity::class)]
    private OneToOneRelatedEntity $relatedEntity2;

    public function testOverwrittenRelatedEntity()
    {
        $entity = new ReflectionEntity(static::class);
        $properties = $entity->getProperties();

        $propertyToTest = null;
        foreach ($properties as $property) {
            if ($property->getName() === 'relatedEntity2') {
                /** @var OneToOne $attribute */
                $attribute = $property->getAttributes(OneToOne::class);
                $propertyToTest = new ReflectionRelation($attribute[0]->newInstance(), $property);

                break;
            }
        }

        $this->assertEquals(RelatedEntity::class, $propertyToTest->getTargetEntity());
    }

    #[OneToOne(ownedBy: 'relatedEntity')]
    private RelatedEntity $relatedEntity3;

    public function testMappedBySpecified()
    {
        $entity = new ReflectionEntity(static::class);
        $properties = $entity->getProperties();

        $propertyToTest = null;
        foreach ($properties as $property) {
            if ($property->getName() === 'relatedEntity3') {
                /** @var OneToOne $attribute */
                $attribute = $property->getAttributes(OneToOne::class);
                $propertyToTest = new ReflectionRelation($attribute[0]->newInstance(), $property);

                break;
            }
        }

        $this->assertEquals(RelatedEntity::class, $propertyToTest->getTargetEntity());
        $this->assertEquals('relatedEntity', $propertyToTest->getMappedBy());
        $this->assertEquals('one_to_one_test_id', $propertyToTest->getInversedBy());
    }

    #[OneToOne(referencedBy: 'oneToOneTest')]
    private RelatedEntity $relatedEntity4;

    public function testInversedBySpecified()
    {
        $entity = new ReflectionEntity(static::class);
        $properties = $entity->getProperties();

        $propertyToTest = null;
        foreach ($properties as $property) {
            if ($property->getName() === 'relatedEntity4') {
                /** @var OneToOne $attribute */
                $attribute = $property->getAttributes(OneToOne::class);
                $propertyToTest = new ReflectionRelation($attribute[0]->newInstance(), $property);

                break;
            }
        }

        $this->assertEquals(RelatedEntity::class, $propertyToTest->getTargetEntity());
        $this->assertEquals('related_entity4_id', $propertyToTest->getMappedBy());
        $this->assertEquals('oneToOneTest', $propertyToTest->getInversedBy());
    }
}
