<?php

namespace Articulate\Attributes\Relations;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class MorphOne implements RelationAttributeInterface {
    private ?string $resolvedTypeColumn = null;

    private ?string $resolvedIdColumn = null;

    /**
     * @param class-string $targetEntity The entity class this relation morphs to
     * @param string|null $morphType The morph type identifier (defaults to the target entity class name)
     * @param string|null $typeColumn Custom column name for the morph type (default: {property}_type)
     * @param string|null $idColumn Custom column name for the morph ID (default: {property}_id)
     * @param string|null $referencedBy The property on the target entity that references back
     * @param bool $foreignKey Whether to create a foreign key constraint
     */
    public function __construct(
        public readonly string $targetEntity,
        public readonly ?string $morphType = null,
        public readonly ?string $typeColumn = null,
        public readonly ?string $idColumn = null,
        public readonly ?string $referencedBy = null,
        public readonly bool $foreignKey = true,
    ) {
    }

    public function getTargetEntity(): ?string
    {
        return $this->targetEntity;
    }

    public function getColumn(): ?string
    {
        return $this->idColumn;
    }

    /**
     * Get the morph type identifier.
     */
    public function getMorphType(): string
    {
        return $this->morphType ?? $this->targetEntity;
    }

    /**
     * Get the resolved type column name for this morph relation.
     */
    public function getTypeColumn(): string
    {
        return $this->resolvedTypeColumn ?? $this->typeColumn ?? '__UNRESOLVED_TYPE__';
    }

    /**
     * Get the resolved ID column name for this morph relation.
     */
    public function getIdColumn(): string
    {
        return $this->resolvedIdColumn ?? $this->idColumn ?? '__UNRESOLVED_ID__';
    }

    /**
     * Resolve column names based on property name
     * Called by reflection system.
     */
    public function resolveColumnNames(string $propertyName): void
    {
        if ($this->typeColumn === null) {
            $this->resolvedTypeColumn = $this->convertToSnakeCase($propertyName) . '_type';
        } else {
            $this->resolvedTypeColumn = $this->typeColumn;
        }

        if ($this->idColumn === null) {
            $this->resolvedIdColumn = $this->convertToSnakeCase($propertyName) . '_id';
        } else {
            $this->resolvedIdColumn = $this->idColumn;
        }
    }

    private function convertToSnakeCase(string $string): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $string));
    }
}
