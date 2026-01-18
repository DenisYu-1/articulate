<?php

namespace Articulate\Modules\Database\SchemaComparator\RelationValidators;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionManyToMany;
use Articulate\Attributes\Reflection\RelationInterface;
use Articulate\Attributes\Relations\ManyToMany;
use RuntimeException;

class ManyToManyRelationValidator implements RelationValidatorInterface {
    public function validate(RelationInterface $relation): void
    {
        if (!$relation instanceof ReflectionManyToMany) {
            return;
        }

        $this->validateBasicConfiguration($relation);

        if ($relation->isOwningSide()) {
            $this->validateOwningSide($relation);
        } else {
            $this->validateInverseSide($relation);
        }
    }

    /**
     * Validates basic configuration constraints for many-to-many relations.
     */
    private function validateBasicConfiguration(ReflectionManyToMany $relation): void
    {
        if ($relation->getMappedBy() && $relation->getInversedBy()) {
            throw new RuntimeException('Many-to-many misconfigured: ownedBy and referencedBy cannot be both defined');
        }

        if (!$relation->isOwningSide() && count($relation->getExtraProperties()) > 0) {
            throw new RuntimeException('Many-to-many misconfigured: inverse side cannot define extra mapping properties');
        }
    }

    /**
     * Validates the owning side of a many-to-many relation.
     */
    private function validateOwningSide(ReflectionManyToMany $relation): void
    {
        $targetEntity = new ReflectionEntity($relation->getTargetEntity());
        $inversedBy = $relation->getInversedBy();

        if (!$inversedBy) {
            throw new RuntimeException('Many-to-many owning side must specify referencedBy to define the inverse property');
        }

        $this->validateInverseProperty($targetEntity, $inversedBy, $relation);
        $this->validateMappingTableName($relation, $targetEntity, $inversedBy);
    }

    /**
     * Validates the inverse side of a many-to-many relation.
     */
    private function validateInverseSide(ReflectionManyToMany $relation): void
    {
        $targetEntity = new ReflectionEntity($relation->getTargetEntity());
        $mappedBy = $relation->getMappedBy();

        if (!$mappedBy) {
            throw new RuntimeException('Many-to-many inverse side misconfigured: ownedBy is required');
        }

        $this->validateOwningProperty($targetEntity, $mappedBy, $relation);
        $this->validateMappingTableName($relation, $targetEntity, $mappedBy);
    }

    /**
     * Validates that the inverse property exists and is correctly configured.
     */
    private function validateInverseProperty(ReflectionEntity $targetEntity, string $inversedBy, ReflectionManyToMany $relation): void
    {
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
    }

    /**
     * Validates that the owning property exists and is correctly configured.
     */
    private function validateOwningProperty(ReflectionEntity $targetEntity, string $mappedBy, ReflectionManyToMany $relation): void
    {
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
    }

    /**
     * Validates that mapping table names match between related properties.
     */
    private function validateMappingTableName(ReflectionManyToMany $relation, ReflectionEntity $targetEntity, string $targetPropertyName): void
    {
        $targetProperty = $targetEntity->getProperty($targetPropertyName);
        $attributes = $targetProperty->getAttributes(ManyToMany::class);
        /** @var ManyToMany $targetAttr */
        $targetAttr = $attributes[0]->newInstance();

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
