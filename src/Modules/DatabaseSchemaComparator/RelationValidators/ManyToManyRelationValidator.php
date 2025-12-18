<?php

namespace Articulate\Modules\DatabaseSchemaComparator\RelationValidators;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionManyToMany;
use Articulate\Attributes\Reflection\RelationInterface;
use Articulate\Attributes\Relations\ManyToMany;
use RuntimeException;

class ManyToManyRelationValidator implements RelationValidatorInterface
{
    public function validate(RelationInterface $relation): void
    {
        if (!$relation instanceof ReflectionManyToMany) {
            return;
        }

        if ($relation->getMappedBy() && $relation->getInversedBy()) {
            throw new RuntimeException('Many-to-many misconfigured: ownedBy and referencedBy cannot be both defined');
        }

        if (!$relation->isOwningSide() && count($relation->getExtraProperties()) > 0) {
            throw new RuntimeException('Many-to-many misconfigured: inverse side cannot define extra mapping properties');
        }

        $targetEntity = new ReflectionEntity($relation->getTargetEntity());
        $mappedBy = $relation->getMappedBy();
        $inversedBy = $relation->getInversedBy();

        if ($relation->isOwningSide()) {
            if (!$inversedBy) {
                throw new RuntimeException('Many-to-many owning side must specify referencedBy to define the inverse property');
            }
            if (!$targetEntity->hasProperty($inversedBy)) {
                throw new RuntimeException('Many-to-many inverse side misconfigured: property not found');
            }
            $targetProperty = $targetEntity->getProperty($inversedBy);
            $attributes = $targetProperty->getAttributes(ManyToMany::class);
            if (empty($attributes)) {
                throw new RuntimeException('Many-to-many inverse side misconfigured: attribute missing');
            }
            /** @var ManyToMany $targetAttr */
            $targetAttr = $attributes[0]->newInstance();
            $targetOwnedBy = $targetAttr->ownedBy;
            if ($targetOwnedBy !== $relation->getPropertyName()) {
                throw new RuntimeException('Many-to-many inverse side misconfigured: ownedBy does not reference owning property');
            }
            if (
                $targetAttr->mappingTable
                && $targetAttr->mappingTable->name
                && $relation->getAttribute()->mappingTable?->name
                && $targetAttr->mappingTable->name !== $relation->getTableName()
            ) {
                throw new RuntimeException('Many-to-many inverse side misconfigured: mapping table name mismatch');
            }

            return;
        }

        if (!$mappedBy) {
            throw new RuntimeException('Many-to-many inverse side misconfigured: ownedBy is required');
        }
        if (!$targetEntity->hasProperty($mappedBy)) {
            throw new RuntimeException('Many-to-many inverse side misconfigured: owning property not found');
        }
        $targetProperty = $targetEntity->getProperty($mappedBy);
        $attributes = $targetProperty->getAttributes(ManyToMany::class);
        if (empty($attributes)) {
            throw new RuntimeException('Many-to-many inverse side misconfigured: owning property attribute missing');
        }
        /** @var ManyToMany $targetAttr */
        $targetAttr = $attributes[0]->newInstance();
        if ($targetAttr->ownedBy !== null) {
            throw new RuntimeException('Many-to-many inverse side misconfigured: owning property cannot declare ownedBy');
        }
        if ($targetAttr->referencedBy && $targetAttr->referencedBy !== $relation->getPropertyName()) {
            throw new RuntimeException('Many-to-many inverse side misconfigured: referencedBy does not reference inverse property');
        }
        if (
            $targetAttr->mappingTable
            && $targetAttr->mappingTable->name
            && $relation->getAttribute()->mappingTable?->name
            && $targetAttr->mappingTable->name !== $relation->getTableName()
        ) {
            throw new RuntimeException('Many-to-many inverse side misconfigured: mapping table name mismatch');
        }
    }

    public function supports(RelationInterface $relation): bool
    {
        return $relation instanceof ReflectionManyToMany;
    }
}
