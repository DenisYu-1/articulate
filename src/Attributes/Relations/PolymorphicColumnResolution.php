<?php

namespace Articulate\Attributes\Relations;

/**
 * Trait for polymorphic relations that need to resolve column names.
 *
 * Provides common functionality for resolving type and ID column names
 * in polymorphic relationships, eliminating code duplication.
 */
trait PolymorphicColumnResolution {
    private ?string $resolvedTypeColumn = null;

    private ?string $resolvedIdColumn = null;

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
     * Resolve column names based on property name.
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

    /**
     * Convert a camelCase string to snake_case.
     */
    private function convertToSnakeCase(string $string): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $string));
    }
}
