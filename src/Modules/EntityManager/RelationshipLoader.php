<?php

namespace Articulate\Modules\EntityManager;

use Articulate\Attributes\Reflection\ReflectionRelation;
use ReflectionProperty;

/**
 * Handles loading of entity relationships.
 */
class RelationshipLoader {
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
     * @param array $data The raw data from the database (for morph relationships)
     * @param bool $forceLoad Whether to force loading even if already loaded
     * @return mixed The loaded relationship data
     */
    public function load(object $entity, ReflectionRelation $relation, array $data = [], bool $forceLoad = false): mixed
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
        if ($relation->isMorphTo()) {
            return $this->loadMorphTo($entity, $relation, $data);
        }

        if ($relation->isMorphOne()) {
            return $this->loadMorphOne($entity, $relation, $data);
        }

        if ($relation->isMorphMany()) {
            return $this->loadMorphMany($entity, $relation, $data);
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
        $foreignKeyColumn = null;

        // Try to infer from ownedBy property
        $ownedBy = $relation->getMappedByProperty();
        if ($ownedBy) {
            $ownedByRelation = $targetMetadata->getRelation($ownedBy);
            if ($ownedByRelation && $ownedByRelation->isManyToOne()) {
                $foreignKeyColumn = $ownedByRelation->getColumnName();
            }
        }

        if (!$foreignKeyColumn) {
            // Fallback: assume foreign key is {entity_table}_id
            $entityTable = $entityMetadata->getTableName();
            $foreignKeyColumn = $entityTable . '_id';
        }

        // Query for related entities
        $qb = $this->entityManager->createQueryBuilder($targetEntity)
            ->where($foreignKeyColumn, $primaryKeyValue);

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
            ->where($foreignKey, $primaryKeyValue);

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
     * Load a MorphTo relationship.
     *
     * MorphTo is the inverse side of a polymorphic relationship. The owning entity
     * contains morph_type and morph_id columns that point to any target entity.
     */
    private function loadMorphTo(object $entity, ReflectionRelation $relation, array $data = []): ?object
    {
        // Get the morph type and ID values from the raw data
        $morphTypeColumn = $relation->getMorphTypeColumnName();
        $morphIdColumn = $relation->getMorphIdColumnName();

        $morphTypeValue = $data[$morphTypeColumn] ?? null;
        $morphIdValue = $data[$morphIdColumn] ?? null;

        // If either value is null, there's no relationship
        if ($morphTypeValue === null || $morphIdValue === null) {
            return null;
        }

        // Find the entity using the resolved type and ID
        return $this->entityManager->find($morphTypeValue, $morphIdValue);
    }

    /**
     * Load a MorphOne relationship.
     *
     * MorphOne is the owning side of a polymorphic relationship. The target entities
     * contain morph_type and morph_id columns that reference back to this entity.
     */
    private function loadMorphOne(object $entity, ReflectionRelation $relation, array $data = []): ?object
    {
        $entityMetadata = $this->metadataRegistry->getMetadata($entity::class);
        $primaryKeyValue = $this->getPrimaryKeyValue($entity, $entityMetadata);
        $targetEntity = $relation->getTargetEntity();
        $morphType = $relation->getMorphType();

        // Query the target table where morph_type matches our entity class and morph_id matches our ID
        $qb = $this->entityManager->createQueryBuilder($targetEntity)
            ->where($relation->getMorphTypeColumnName(), $morphType)
            ->where($relation->getMorphIdColumnName(), $primaryKeyValue)
            ->limit(1);

        $results = $qb->getResult($targetEntity);

        return $results[0] ?? null;
    }

    /**
     * Load a MorphMany relationship.
     *
     * MorphMany is the owning side of a polymorphic relationship. Similar to MorphOne
     * but returns a collection of related entities instead of a single entity.
     */
    private function loadMorphMany(object $entity, ReflectionRelation $relation, array $data = []): array
    {
        $entityMetadata = $this->metadataRegistry->getMetadata($entity::class);
        $primaryKeyValue = $this->getPrimaryKeyValue($entity, $entityMetadata);
        $targetEntity = $relation->getTargetEntity();
        $morphType = $relation->getMorphType();

        // Query the target table where morph_type matches our entity class and morph_id matches our ID
        $qb = $this->entityManager->createQueryBuilder($targetEntity)
            ->where($relation->getMorphTypeColumnName(), $morphType)
            ->where($relation->getMorphIdColumnName(), $primaryKeyValue);

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
        $reflectionProperty = new ReflectionProperty($entity, $propertyName);
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

        $reflectionProperty = new ReflectionProperty($entity, $propertyName);
        $reflectionProperty->setAccessible(true);

        return $reflectionProperty->getValue($entity);
    }
}
