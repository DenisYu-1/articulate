<?php

namespace Articulate\Modules\Database\SchemaComparator\RelationValidators;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionRelation;
use Articulate\Attributes\Reflection\RelationInterface;
use Articulate\Attributes\Relations\ManyToOne;
use RuntimeException;

class OneToManyRelationValidator implements RelationValidatorInterface {
    public function validate(RelationInterface $relation): void
    {
        if (!$relation instanceof ReflectionRelation || !$relation->isOneToMany()) {
            return;
        }

        $mappedBy = $relation->getMappedBy();
        if (!$mappedBy) {
            throw new RuntimeException('One-to-many inverse side misconfigured: ownedBy is required');
        }

        $targetEntity = new ReflectionEntity($relation->getTargetEntity());
        if (!$targetEntity->hasProperty($mappedBy)) {
            throw new RuntimeException('One-to-many inverse side misconfigured: owning property not found');
        }

        $targetProperty = $targetEntity->getProperty($mappedBy);
        $attributes = $targetProperty->getAttributes(ManyToOne::class);

        if (empty($attributes)) {
            throw new RuntimeException('One-to-many inverse side misconfigured: owning property not many-to-one');
        }

        $owningRelation = new ReflectionRelation($attributes[0]->newInstance(), $targetProperty);

        if ($owningRelation->getTargetEntity() !== $relation->getDeclaringClassName()) {
            throw new RuntimeException('One-to-many inverse side misconfigured: target entity mismatch');
        }

        $inversedBy = $owningRelation->getInversedBy();
        if ($inversedBy && $inversedBy !== $relation->getPropertyName()) {
            throw new RuntimeException('One-to-many inverse side misconfigured: inversedBy does not reference inverse property');
        }
    }

    public function supports(RelationInterface $relation): bool
    {
        return $relation instanceof ReflectionRelation && $relation->isOneToMany();
    }
}
