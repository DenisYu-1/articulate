<?php

namespace Articulate\Attributes\Relations;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class MorphTo implements RelationAttributeInterface
{
    private ?string $resolvedTypeColumn = null;

    private ?string $resolvedIdColumn = null;

    /**
     * @param string|null $typeColumn Custom column name for the morph type (default: {property}_type)
     * @param string|null $idColumn Custom column name for the morph ID (default: {property}_id)
     */
    public function __construct(
        public readonly ?string $typeColumn = null,
        public readonly ?string $idColumn = null,
    ) {
    }

    public function getTargetEntity(): ?string
    {
        // MorphTo can target any entity at runtime - no single target entity
        return null;
    }

    public function getColumn(): ?string
    {
        return $this->idColumn;
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
     * Get recommended index for this polymorphic relation
     * Returns a composite index on (type_column, id_column).
     */
    public function getRecommendedIndexName(): string
    {
        $typeColumn = $this->getTypeColumn();
        $idColumn = $this->getIdColumn();

        return "idx_{$typeColumn}_{$idColumn}";
    }

    /**
     * Get recommended index columns for this polymorphic relation.
     */
    public function getRecommendedIndexColumns(): array
    {
        return [$this->getTypeColumn(), $this->getIdColumn()];
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
