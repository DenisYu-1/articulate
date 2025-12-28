<?php

namespace Articulate\Modules\EntityManager;

use Articulate\Attributes\Reflection\ReflectionRelation;

/**
 * Handles loading of entity relationships.
 */
class RelationshipLoader
{
    public function __construct(
        private EntityManager $entityManager,
        private EntityMetadataRegistry $metadataRegistry
    ) {
    }

    /**
     * Get the metadata registry.
     */
    public function getMetadataRegistry(): EntityMetadataRegistry
    {
        return $this->metadataRegistry;
    }

    /**
     * Load a relationship for a given entity.
     *
     * @param object $entity The entity to load the relationship for
     * @param ReflectionRelation $relation The relationship metadata
     * @param bool $forceLoad Whether to force loading even if already loaded
     * @return mixed The loaded relationship data
     */
    public function load(object $entity, ReflectionRelation $relation, bool $forceLoad = false): mixed
    {
        if ($relation->isOneToOne()) {
            return $this->loadOneToOne($entity, $relation);
        }

        if ($relation->isOneToMany()) {
            return $this->loadOneToMany($entity, $relation);
        }

        if ($relation->isManyToOne()) {
            return $this->loadManyToOne($entity, $relation);
        }

        if ($relation->isManyToMany()) {
            return $this->loadManyToMany($entity, $relation);
        }

        // Handle polymorphic relationships if needed
        if ($relation->isMorphOne() || $relation->isMorphMany() || $relation->isMorphTo()) {
            // TODO: Implement polymorphic relationship loading
            return null;
        }

        return null;
    }

    /**
     * Load a OneToOne relationship.
     */
    private function loadOneToOne(object $entity, ReflectionRelation $relation): ?object
    {
        $foreignKeyValue = $this->getForeignKeyValue($entity, $relation);
        if ($foreignKeyValue === null) {
            return null;
        }

        return $this->entityManager->find($relation->getTargetEntity(), $foreignKeyValue);
    }

    /**
     * Load a OneToMany relationship.
     */
    private function loadOneToMany(object $entity, ReflectionRelation $relation): array
    {
        $entityMetadata = $this->metadataRegistry->getMetadata($entity::class);
        $primaryKeyValue = $this->getPrimaryKeyValue($entity, $entityMetadata);

        $targetEntity = $relation->getTargetEntity();
        $targetMetadata = $this->metadataRegistry->getMetadata($targetEntity);

        // Find the foreign key column that references this entity
        $foreignKeyColumn = $relation->getColumnName();
        if (!$foreignKeyColumn) {
            // Try to infer from ownedBy property or relationship name
            $ownedBy = $relation->getEntityProperty()->ownedBy ?? null;
            if ($ownedBy) {
                $ownedByRelation = $targetMetadata->getRelation($ownedBy);
                if ($ownedByRelation && $ownedByRelation->isManyToOne()) {
                    $foreignKeyColumn = $ownedByRelation->getColumnName();
                }
            }
        }

        if (!$foreignKeyColumn) {
            // Fallback: assume foreign key is {entity_table}_id
            $entityTable = $entityMetadata->getTableName();
            $foreignKeyColumn = $entityTable . '_id';
        }

        // Query for related entities
        $qb = $this->entityManager->createQueryBuilder($targetEntity)
            ->where("$foreignKeyColumn = ?", $primaryKeyValue);

        return $qb->getResult($targetEntity);
    }

    /**
     * Load a ManyToOne relationship.
     */
    private function loadManyToOne(object $entity, ReflectionRelation $relation): ?object
    {
        $foreignKeyValue = $this->getForeignKeyValue($entity, $relation);
        if ($foreignKeyValue === null) {
            return null;
        }

        return $this->entityManager->find($relation->getTargetEntity(), $foreignKeyValue);
    }

    /**
     * Load a ManyToMany relationship.
     */
    private function loadManyToMany(object $entity, ReflectionRelation $relation): array
    {
        // For ManyToMany, we need to query through a pivot table
        $entityMetadata = $this->metadataRegistry->getMetadata($entity::class);
        $primaryKeyValue = $this->getPrimaryKeyValue($entity, $entityMetadata);

        $pivotTable = $relation->getPivotTableName();
        $foreignKey = $relation->getForeignPivotKey();
        $relatedKey = $relation->getRelatedPivotKey();

        // Query the pivot table to get related IDs
        $pivotQb = $this->entityManager->createQueryBuilder()
            ->select($relatedKey)
            ->from($pivotTable)
            ->where("$foreignKey = ?", $primaryKeyValue);

        $pivotResults = $pivotQb->getResult();
        $relatedIds = array_column($pivotResults, $relatedKey);

        if (empty($relatedIds)) {
            return [];
        }

        // Query for the actual related entities
        $targetEntity = $relation->getTargetEntity();
        $targetMetadata = $this->metadataRegistry->getMetadata($targetEntity);
        $targetPrimaryKey = $targetMetadata->getPrimaryKeyColumns()[0] ?? 'id';

        $qb = $this->entityManager->createQueryBuilder($targetEntity)
            ->where("$targetPrimaryKey IN (" . str_repeat('?,', count($relatedIds) - 1) . '?)', $relatedIds);

        return $qb->getResult($targetEntity);
    }

    /**
     * Get the foreign key value from the entity for the given relationship.
     */
    private function getForeignKeyValue(object $entity, ReflectionRelation $relation): mixed
    {
        $columnName = $relation->getColumnName();
        if (!$columnName) {
            return null;
        }

        // Get the property name for this column
        $entityMetadata = $this->metadataRegistry->getMetadata($entity::class);
        $propertyName = $entityMetadata->getPropertyNameForColumn($columnName);

        if (!$propertyName) {
            return null;
        }

        // Get the value from the entity
        $reflectionProperty = new \ReflectionProperty($entity, $propertyName);
        $reflectionProperty->setAccessible(true);

        return $reflectionProperty->getValue($entity);
    }

    /**
     * Get the primary key value from an entity.
     */
    private function getPrimaryKeyValue(object $entity, EntityMetadata $metadata): mixed
    {
        $primaryKeyColumns = $metadata->getPrimaryKeyColumns();
        if (empty($primaryKeyColumns)) {
            return null;
        }

        // For now, assume single primary key
        $primaryKeyColumn = $primaryKeyColumns[0];
        $propertyName = $metadata->getPropertyNameForColumn($primaryKeyColumn);

        if (!$propertyName) {
            return null;
        }

        $reflectionProperty = new \ReflectionProperty($entity, $propertyName);
        $reflectionProperty->setAccessible(true);

        return $reflectionProperty->getValue($entity);
    }
}
