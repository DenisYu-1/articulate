<?php

namespace Articulate\Modules\Database\SchemaComparator\RelationValidators;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionRelation;
use Articulate\Attributes\Reflection\RelationInterface;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Attributes\Relations\OneToMany;
use RuntimeException;

class ManyToOneRelationValidator implements RelationValidatorInterface
{
    public function validate(RelationInterface $relation): void
    {
        if (!$relation->isManyToOne()) {
            return;
        }

        $inversedPropertyName = $relation->getInversedBy();
        if (!$inversedPropertyName) {
            return;
        }

        $targetEntity = new ReflectionEntity($relation->getTargetEntity());
        if (!$targetEntity->hasProperty($inversedPropertyName)) {
            throw new RuntimeException('Many-to-one inverse side misconfigured: property not found');
        }

        $targetProperty = $targetEntity->getProperty($inversedPropertyName);
        if (!empty($targetProperty->getAttributes(ManyToOne::class))) {
            throw new RuntimeException('Many-to-one inverse side misconfigured: inverse side marked as owner');
        }

        $attributes = $targetProperty->getAttributes(OneToMany::class);

        if (empty($attributes)) {
            throw new RuntimeException('Many-to-one inverse side misconfigured: attribute missing');
        }

        $inverseRelation = new ReflectionRelation($attributes[0]->newInstance(), $targetProperty);
        $mappedBy = $inverseRelation->getMappedBy();

        if ($mappedBy !== $relation->getPropertyName()) {
            throw new RuntimeException('Many-to-one inverse side misconfigured: ownedBy does not reference owning property');
        }

        if ($inverseRelation->getTargetEntity() !== $relation->getDeclaringClassName()) {
            throw new RuntimeException('Many-to-one inverse side misconfigured: target entity mismatch');
        }
    }

    public function supports(RelationInterface $relation): bool
    {
        return $relation instanceof ReflectionRelation && $relation->isManyToOne();
    }
}
