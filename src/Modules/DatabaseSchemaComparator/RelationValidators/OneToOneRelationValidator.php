<?php

namespace Articulate\Modules\DatabaseSchemaComparator\RelationValidators;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionRelation;
use Articulate\Attributes\Reflection\RelationInterface;
use Articulate\Attributes\Relations\OneToOne;
use RuntimeException;

class OneToOneRelationValidator implements RelationValidatorInterface
{
    public function validate(RelationInterface $relation): void
    {
        if (! $relation->isForeignKeyRequired()) {
            return;
        }
        if (! $relation->isOwningSide()) {
            return;
        }

        $targetEntity = new ReflectionEntity($relation->getTargetEntity());
        $inversedPropertyName = $relation->getInversedBy();

        if (! $inversedPropertyName) {
            return;
        }

        if (! $targetEntity->hasProperty($inversedPropertyName)) {
            throw new RuntimeException('One-to-one inverse side misconfigured: property not found');
        }

        $property = $targetEntity->getProperty($inversedPropertyName);
        $attributes = $property->getAttributes(OneToOne::class);

        if (empty($attributes)) {
            throw new RuntimeException('One-to-one inverse side misconfigured: attribute missing');
        }

        $targetProperty = $attributes[0]->newInstance();

        $inverseRequestsForeignKey = $targetProperty->ownedBy !== null && $targetProperty->foreignKey;

        if ($inverseRequestsForeignKey) {
            $ownerClass = $relation->getDeclaringClassName();
            $ownerProperty = $relation->getPropertyName();
            $inverseClass = $targetEntity->getName();

            throw new RuntimeException(sprintf(
                'One-to-one inverse side misconfigured: inverse side requests foreign key (%s::%s <-> %s::%s)',
                $ownerClass,
                $ownerProperty,
                $inverseClass,
                $inversedPropertyName,
            ));
        }

        if ($targetProperty->ownedBy === null) {
            throw new RuntimeException('One-to-one inverse side misconfigured: ownedBy is required on inverse side');
        }

        if ($targetProperty->ownedBy !== $relation->getPropertyName()) {
            throw new RuntimeException('One-to-one inverse side misconfigured: ownedBy does not reference owning property');
        }
    }

    public function supports(RelationInterface $relation): bool
    {
        return $relation instanceof ReflectionRelation && $relation->isOneToOne();
    }
}
