<?php

namespace Articulate\Attributes\Relations;

/**
 * Registry for mapping entity class names to shorter morph type aliases
 * Improves performance and database storage efficiency.
 */
class MorphTypeRegistry
{
    private static array $mappings = [];

    private static array $reverseMappings = [];

    /**
     * Register a morph type alias for an entity class.
     */
    public static function register(string $entityClass, string $alias): void
    {
        if (!class_exists($entityClass)) {
            throw new \InvalidArgumentException("Entity class '{$entityClass}' does not exist");
        }

        self::$mappings[$entityClass] = $alias;
        self::$reverseMappings[$alias] = $entityClass;
    }

    /**
     * Get the morph type alias for an entity class
     * Returns the full class name if no alias is registered.
     */
    public static function getAlias(string $entityClass): string
    {
        return self::$mappings[$entityClass] ?? $entityClass;
    }

    /**
     * Get the entity class from a morph type alias
     * Returns the alias if no mapping is found (for backward compatibility).
     */
    public static function getEntityClass(string $alias): string
    {
        return self::$reverseMappings[$alias] ?? $alias;
    }

    /**
     * Check if an alias is registered.
     */
    public static function hasAlias(string $alias): bool
    {
        return isset(self::$reverseMappings[$alias]);
    }

    /**
     * Get all registered mappings.
     */
    public static function getMappings(): array
    {
        return self::$mappings;
    }

    /**
     * Clear all mappings.
     */
    public static function clear(): void
    {
        self::$mappings = [];
        self::$reverseMappings = [];
    }
}

