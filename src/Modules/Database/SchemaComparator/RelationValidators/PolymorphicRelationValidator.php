<?php

namespace Articulate\Modules\Database\SchemaComparator\RelationValidators;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionRelation;
use Articulate\Attributes\Reflection\RelationInterface;
use Articulate\Attributes\Relations\MorphTo;
use RuntimeException;

class PolymorphicRelationValidator implements RelationValidatorInterface
{
    public function validate(RelationInterface $relation): void
    {
        if ($relation->isMorphTo()) {
            $this->validateMorphTo($relation);
        } elseif ($relation->isMorphOne()) {
            $this->validateMorphOne($relation);
        } elseif ($relation->isMorphMany()) {
            $this->validateMorphMany($relation);
        }
    }

    public function supports(RelationInterface $relation): bool
    {
        return $relation instanceof ReflectionRelation &&
               ($relation->isMorphTo() || $relation->isMorphOne() || $relation->isMorphMany());
    }

    private function validateMorphTo(RelationInterface $relation): void
    {
        // MorphTo relations are open-ended and don't specify target entities upfront
        // They validate that the morph columns exist and are properly configured
        // Additional validation happens when relations are actually used at runtime

        // Ensure the relation has proper column names resolved
        $typeColumn = $relation->getMorphTypeColumnName();
        $idColumn = $relation->getMorphIdColumnName();

        if (empty($typeColumn) || str_contains($typeColumn, '__UNRESOLVED__')) {
            throw new RuntimeException("MorphTo relation on '{$relation->getDeclaringClassName()}::{$relation->getPropertyName()}' has unresolved type column name");
        }

        if (empty($idColumn) || str_contains($idColumn, '__UNRESOLVED__')) {
            throw new RuntimeException("MorphTo relation on '{$relation->getDeclaringClassName()}::{$relation->getPropertyName()}' has unresolved ID column name");
        }
    }

    private function validateMorphOne(RelationInterface $relation): void
    {
        // Validate that the target entity exists
        $targetEntity = new ReflectionEntity($relation->getTargetEntity());
        if (!$targetEntity->isEntity()) {
            throw new RuntimeException("MorphOne target entity '{$relation->getTargetEntity()}' is not a valid entity");
        }

        // Validate that referencedBy is specified (required for polymorphic relations)
        $referencedBy = $relation->getInversedBy();
        if (!$referencedBy) {
            throw new RuntimeException("MorphOne relation on '{$relation->getDeclaringClassName()}::{$relation->getPropertyName()}' must specify 'referencedBy' property");
        }

        // Validate the inverse side exists and is properly configured
        $this->validateInverseMorphRelation($relation, $referencedBy, MorphTo::class);
    }

    private function validateMorphMany(RelationInterface $relation): void
    {
        // Validate that the target entity exists
        $targetEntity = new ReflectionEntity($relation->getTargetEntity());
        if (!$targetEntity->isEntity()) {
            throw new RuntimeException("MorphMany target entity '{$relation->getTargetEntity()}' is not a valid entity");
        }

        // Validate that referencedBy is specified (required for polymorphic relations)
        $referencedBy = $relation->getInversedBy();
        if (!$referencedBy) {
            throw new RuntimeException("MorphMany relation on '{$relation->getDeclaringClassName()}::{$relation->getPropertyName()}' must specify 'referencedBy' property");
        }

        // Validate the inverse side exists and is properly configured
        $this->validateInverseMorphRelation($relation, $referencedBy, MorphTo::class);
    }

    private function validateInverseMorphRelation(RelationInterface $relation, string $inversePropertyName, string $expectedAttributeClass): void
    {
        $targetEntity = new ReflectionEntity($relation->getTargetEntity());
        if (!$targetEntity->hasProperty($inversePropertyName)) {
            throw new RuntimeException("Polymorphic relation inverse side misconfigured: property '{$inversePropertyName}' not found in '{$relation->getTargetEntity()}'");
        }

        $targetProperty = $targetEntity->getProperty($inversePropertyName);
        $attributes = $targetProperty->getAttributes($expectedAttributeClass);

        if (empty($attributes)) {
            throw new RuntimeException("Polymorphic relation inverse side misconfigured: expected {$expectedAttributeClass} attribute not found");
        }

        $inverseRelation = new ReflectionRelation($attributes[0]->newInstance(), $targetProperty);

        // For MorphTo, just ensure it's properly configured (no additional validation needed
        // since MorphTo is now open-ended)
    }
}
