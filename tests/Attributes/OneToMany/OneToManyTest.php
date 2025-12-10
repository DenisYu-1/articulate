<?php

namespace Articulate\Tests\Attributes\OneToMany;

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
}

#[Entity]
class OneToManyRelatedEntity {
    #[Property]
    private int $id;

    #[ManyToOne]
    private OneToManyTest $oneToMany;
}

#[Entity(tableName: 'test')]
class OneToManyTest extends AbstractTestCase
{
    #[Property]
    private int $id;

    #[OneToMany(mappedBy: 'test', targetEntity: RelatedEntity::class)]
    private array $relatedEntity;

    public function testOneToMany()
    {
        $entity = new ReflectionEntity(static::class);
        $properties = $entity->getProperties();

        $propertyToTest = null;
        foreach ($properties as $property) {
            if ($property->getName() === 'relatedEntity') {
                /** @var OneToMany $attribute */
                $attribute = $property->getAttributes(OneToMany::class);
                $propertyToTest = new ReflectionRelation($attribute[0]->newInstance(), $property);
                break;
            }
        }

        $this->assertEquals(RelatedEntity::class, $propertyToTest->getTargetEntity());
        $this->assertFalse($propertyToTest->isForeignKeyRequired());
        $this->assertEquals('test', $propertyToTest->getMappedBy());
        $this->assertEquals('one_to_many_test_id', $propertyToTest->getInversedBy());
    }

    #[OneToMany(targetEntity: NonEntity::class)]
    private array $relatedNonEntity;

    public function testRelationToNonEntity()
    {
        $entity = new ReflectionEntity(static::class);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Non-entity found in relation');
        $property = $entity->getProperty('relatedNonEntity');
        /** @var OneToMany $attribute */
        $attribute = $property->getAttributes(OneToMany::class)[0]->newInstance();
        $relation = new ReflectionRelation($attribute, $property);

        $relation->getTargetEntity();
    }

    #[OneToMany(targetEntity: RelatedEntity::class)]
    private array $relatedEntity2;

    public function testOverwrittenRelatedEntity()
    {
        $entity = new ReflectionEntity(static::class);
        $properties = $entity->getProperties();

        $propertyToTest = null;
        foreach ($properties as $property) {
            if ($property->getName() === 'relatedEntity2') {
                /** @var OneToMany $attribute */
                $attribute = $property->getAttributes(OneToMany::class);
                $propertyToTest = new ReflectionRelation($attribute[0]->newInstance(), $property);
                break;
            }
        }

        $this->assertEquals(RelatedEntity::class, $propertyToTest->getTargetEntity());
    }

    #[OneToMany(mappedBy: 'relatedEntity', targetEntity: RelatedEntity::class)]
    private array $relatedEntity3;

    #[OneToMany(targetEntity: RelatedEntity::class)]
    private int $invalidCollection;

    public function testMappedBySpecified()
    {
        $entity = new ReflectionEntity(static::class);
        $properties = $entity->getProperties();

        $propertyToTest = null;
        foreach ($properties as $property) {
            if ($property->getName() === 'relatedEntity3') {
                /** @var OneToMany $attribute */
                $attribute = $property->getAttributes(OneToMany::class);
                $propertyToTest = new ReflectionRelation($attribute[0]->newInstance(), $property);
                break;
            }
        }

        $this->assertEquals(RelatedEntity::class, $propertyToTest->getTargetEntity());
        $this->assertEquals('relatedEntity', $propertyToTest->getMappedBy());
        $this->assertEquals('one_to_many_test_id', $propertyToTest->getInversedBy());
    }

    public function testInvalidCollectionType()
    {
        $entity = new ReflectionEntity(static::class);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('One-to-many property must be iterable collection');

        $property = $entity->getProperty('invalidCollection');
        $attribute = $property->getAttributes(OneToMany::class)[0]->newInstance();
        $relation = new ReflectionRelation($attribute, $property);
        $relation->getTargetEntity();
    }
}
