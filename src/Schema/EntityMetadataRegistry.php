<?php

namespace Articulate\Schema;

/**
 * Registry for caching and accessing entity metadata.
 */
class EntityMetadataRegistry {
    /** @var array<string, EntityMetadata> */
    private array $metadataCache = [];

    /** @var array<string, list<string>> table name → entity class list */
    private array $tableIndex = [];

    /**
     * Get metadata for an entity class.
     */
    public function getMetadata(string $entityClass): EntityMetadata
    {
        if (!isset($this->metadataCache[$entityClass])) {
            $this->metadataCache[$entityClass] = new EntityMetadata($entityClass);
            $table = $this->metadataCache[$entityClass]->getTableName();
            if (!in_array($entityClass, $this->tableIndex[$table] ?? [], true)) {
                $this->tableIndex[$table][] = $entityClass;
            }
        }

        return $this->metadataCache[$entityClass];
    }

    /**
     * Return all entity classes known to map to the given table.
     * Only classes whose metadata has been loaded at least once are returned.
     *
     * @return list<string>
     */
    public function getClassesByTable(string $tableName): array
    {
        return $this->tableIndex[$tableName] ?? [];
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
        if (isset($this->metadataCache[$entityClass])) {
            $table = $this->metadataCache[$entityClass]->getTableName();
            $this->tableIndex[$table] = array_values(
                array_filter($this->tableIndex[$table] ?? [], fn ($c) => $c !== $entityClass)
            );
        }
        unset($this->metadataCache[$entityClass]);
    }

    /**
     * Clear all cached metadata.
     */
    public function clearAll(): void
    {
        $this->metadataCache = [];
        $this->tableIndex = [];
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
