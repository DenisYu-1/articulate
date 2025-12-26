<?php

namespace Articulate\Attributes\Relations;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class MorphToMany implements RelationAttributeInterface
{
    private ?string $resolvedTypeColumn = null;

    private ?string $resolvedIdColumn = null;

    private ?string $resolvedTargetIdColumn = null;

    /**
     * @param class-string $targetEntity The entity class this relation morphs to
     * @param string $name The name of the polymorphic relationship (e.g., 'taggable')
     * @param string|null $typeColumn Custom column name for the morph type (default: {name}_type)
     * @param string|null $idColumn Custom column name for the morph ID (default: {name}_id)
     * @param string|null $targetIdColumn Custom column name for the target entity ID (default: {target_table}_id)
     * @param MappingTable|null $mappingTable Custom mapping table configuration
     * @param bool $foreignKey Whether to create foreign key constraints
     */
    public function __construct(
        public readonly string $targetEntity,
        public readonly string $name,
        public readonly ?string $typeColumn = null,
        public readonly ?string $idColumn = null,
        public readonly ?string $targetIdColumn = null,
        public readonly ?MappingTable $mappingTable = null,
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
     * Get the morph name identifier.
     */
    public function getMorphName(): string
    {
        return $this->name;
    }

    /**
     * Get the resolved type column name for this morph relation.
     */
    public function getTypeColumn(): string
    {
        return $this->resolvedTypeColumn ?? $this->typeColumn ?? $this->name . '_type';
    }

    /**
     * Get the resolved ID column name for this morph relation.
     */
    public function getIdColumn(): string
    {
        return $this->resolvedIdColumn ?? $this->idColumn ?? $this->name . '_id';
    }

    /**
     * Get the resolved target ID column name for this morph relation.
     */
    public function getTargetIdColumn(): string
    {
        return $this->resolvedTargetIdColumn ?? $this->targetIdColumn ?? '__UNRESOLVED_TARGET_ID__';
    }

    /**
     * Resolve column names based on property name and target entity
     * Called by reflection system.
     */
    public function resolveColumnNames(string $propertyName, string $targetTableName): void
    {
        // Resolve type column
        if ($this->typeColumn === null) {
            $this->resolvedTypeColumn = $this->name . '_type';
        } else {
            $this->resolvedTypeColumn = $this->typeColumn;
        }

        // Resolve ID column
        if ($this->idColumn === null) {
            $this->resolvedIdColumn = $this->name . '_id';
        } else {
            $this->resolvedIdColumn = $this->idColumn;
        }

        // Resolve target ID column
        if ($this->targetIdColumn === null) {
            $this->resolvedTargetIdColumn = $targetTableName . '_id';
        } else {
            $this->resolvedTargetIdColumn = $this->targetIdColumn;
        }
    }

    /**
     * Get the default mapping table name for this relationship.
     */
    public function getDefaultMappingTableName(): string
    {
        return $this->name . 's';
    }
}
