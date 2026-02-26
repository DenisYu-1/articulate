<?php

namespace Articulate\Modules\EntityManager;

/**
 * Registry for caching and accessing entity metadata.
 */
class EntityMetadataRegistry {
    /** @var array<string, EntityMetadata> */
    private array $metadataCache = [];

    /**
     * Get metadata for an entity class.
     */
    public function getMetadata(string $entityClass): EntityMetadata
    {
        if (!isset($this->metadataCache[$entityClass])) {
            $this->metadataCache[$entityClass] = new EntityMetadata($entityClass);
        }

        return $this->metadataCache[$entityClass];
    }

    /**
     * Check if metadata exists for an entity class.
     */
    public function hasMetadata(string $entityClass): bool
    {
        return isset($this->metadataCache[$entityClass]);
    }

    /**
     * Clear cached metadata for a specific entity class.
     */
    public function clearMetadata(string $entityClass): void
    {
        unset($this->metadataCache[$entityClass]);
    }

    /**
     * Clear all cached metadata.
     */
    public function clearAll(): void
    {
        $this->metadataCache = [];
    }

    /**
     * Get table name for an entity class.
     */
    public function getTableName(string $entityClass): string
    {
        return $this->getMetadata($entityClass)->getTableName();
    }

    /**
     * Check if a class is an entity.
     */
    public function isEntity(string $entityClass): bool
    {
        try {
            $this->getMetadata($entityClass);

            return true;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }
}
