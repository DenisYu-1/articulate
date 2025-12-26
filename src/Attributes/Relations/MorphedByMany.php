<?php

namespace Articulate\Attributes\Relations;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class MorphedByMany implements RelationAttributeInterface
{
    /**
     * @param class-string $targetEntity The entity class this relation morphs from
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
        return $this->typeColumn ?? $this->name . '_type';
    }

    /**
     * Get the resolved ID column name for this morph relation.
     */
    public function getIdColumn(): string
    {
        return $this->idColumn ?? $this->name . '_id';
    }

    /**
     * Get the resolved target ID column name for this morph relation.
     */
    public function getTargetIdColumn(): string
    {
        return $this->targetIdColumn ?? '__UNRESOLVED_TARGET_ID__';
    }

    /**
     * Resolve column names based on property name and target entity
     * Called by reflection system.
     */
    public function resolveColumnNames(string $propertyName, string $targetTableName): void
    {
        // Note: For MorphedByMany, the column names are typically resolved from the owning side
        // This method is here for consistency with the interface
    }

    /**
     * Get the default mapping table name for this relationship.
     */
    public function getDefaultMappingTableName(): string
    {
        return $this->name . 's';
    }
}
