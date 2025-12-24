<?php

namespace Articulate\Modules\DatabaseSchemaComparator\RelationValidators;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionMorphedByMany;
use Articulate\Attributes\Reflection\ReflectionMorphToMany;
use Articulate\Attributes\Reflection\RelationInterface;
use RuntimeException;

class MorphToManyRelationValidator implements RelationValidatorInterface
{
    public function validate(RelationInterface $relation): void
    {
        if ($relation instanceof ReflectionMorphToMany) {
            $this->validateMorphToMany($relation);
        } elseif ($relation instanceof ReflectionMorphedByMany) {
            $this->validateMorphedByMany($relation);
        }
    }

    public function supports(RelationInterface $relation): bool
    {
        return $relation instanceof ReflectionMorphToMany || $relation instanceof ReflectionMorphedByMany;
    }

    private function validateMorphToMany(ReflectionMorphToMany $relation): void
    {
        // Validate that the target entity exists
        $targetEntity = new ReflectionEntity($relation->getTargetEntity());
        if (!$targetEntity->isEntity()) {
            throw new RuntimeException("MorphToMany target entity '{$relation->getTargetEntity()}' is not a valid entity");
        }

        // Validate that the target entity has the inverse relationship
        $this->validateInverseRelationExists($relation);
    }

    private function validateMorphedByMany(ReflectionMorphedByMany $relation): void
    {
        // Validate that the target entity exists
        $targetEntity = new ReflectionEntity($relation->getTargetEntity());
        if (!$targetEntity->isEntity()) {
            throw new RuntimeException("MorphedByMany target entity '{$relation->getTargetEntity()}' is not a valid entity");
        }

        // For MorphedByMany, we need to ensure there are corresponding MorphToMany relations
        // This is more complex as multiple entities can have MorphToMany to the same target
        // We'll validate this during the schema comparison phase
    }

    private function validateInverseRelationExists(ReflectionMorphToMany $relation): void
    {
        $targetEntity = new ReflectionEntity($relation->getTargetEntity());
        $morphName = $relation->getMorphName();

        // Look for a MorphedByMany property on the target entity with the same morph name
        foreach ($targetEntity->getProperties() as $property) {
            $morphedByManyAttributes = $property->getAttributes(\Articulate\Attributes\Relations\MorphedByMany::class);
            if (!empty($morphedByManyAttributes)) {
                $attribute = $morphedByManyAttributes[0]->newInstance();
                if ($attribute->getMorphName() === $morphName) {
                    // Found matching inverse relation
                    return;
                }
            }
        }

        throw new RuntimeException(
            "MorphToMany relation on '{$relation->getDeclaringClassName()}::{$relation->getPropertyName()}' " .
            "with morph name '{$morphName}' has no corresponding MorphedByMany relation on target entity '{$relation->getTargetEntity()}'"
        );
    }
}


