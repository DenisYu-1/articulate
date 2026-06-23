<?php

namespace Articulate\Modules\EntityManager;

use Articulate\Attributes\Reflection\ReflectionManyToMany;
use Articulate\Attributes\Reflection\ReflectionMorphedByMany;
use Articulate\Attributes\Reflection\ReflectionMorphToMany;
use Articulate\Attributes\Reflection\ReflectionRelation;
use Articulate\Attributes\Reflection\RelationInterface;
use Articulate\Collection\MappingItem;
use Articulate\Schema\EntityMetadata;
use Articulate\Schema\EntityMetadataRegistry;
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
     * Get the entity manager.
     */
    public function getEntityManager(): EntityManager
    {
        return $this->entityManager;
    }

    /**
     * Return the number of related entities without loading them.
     * Supported for OneToMany and ManyToMany; returns 0 for other relation types.
     */
    public function count(object $entity, RelationInterface $relation): int
    {
        if ($relation instanceof ReflectionManyToMany) {
            return $this->countManyToMany($entity, $relation);
        }

        if ($relation instanceof ReflectionRelation && $relation->isOneToMany()) {
            return $this->countOneToMany($entity, $relation);
        }

        return 0;
    }

    /**
     * COUNT(*) for a OneToMany relation.
     */
    private function countOneToMany(object $entity, ReflectionRelation $relation): int
    {
        $meta = $this->metadataRegistry->getMetadata($entity::class);
        $pk = $this->getPrimaryKeyValue($entity, $meta);
        $targetEntity = $relation->getTargetEntity();
        $targetMeta = $this->metadataRegistry->getMetadata($targetEntity);
        $targetTable = $targetMeta->getTableName();

        $fkColumn = null;
        $ownedBy = $relation->getMappedByProperty();
        if ($ownedBy) {
            $ownedByRel = $targetMeta->getRelation($ownedBy);
            if ($ownedByRel instanceof ReflectionRelation && $ownedByRel->isManyToOne()) {
                $fkColumn = $ownedByRel->getColumnName();
            }
        }
        $fkColumn ??= $meta->getTableName() . '_id';

        $result = $this->entityManager->createQueryBuilder()
            ->select('COUNT(*) as cnt')
            ->from($targetTable)
            ->where($fkColumn, $pk)
            ->getResult();

        return (int) ($result[0]['cnt'] ?? 0);
    }

    /**
     * COUNT(*) for a ManyToMany relation via the pivot table.
     */
    private function countManyToMany(object $entity, ReflectionRelation|ReflectionManyToMany $relation): int
    {
        $meta = $this->metadataRegistry->getMetadata($entity::class);
        $pk = $this->getPrimaryKeyValue($entity, $meta);

        $result = $this->entityManager->createQueryBuilder()
            ->select('COUNT(*) as cnt')
            ->from($relation->getPivotTableName())
            ->where($relation->getForeignPivotKey(), $pk)
            ->getResult();

        return (int) ($result[0]['cnt'] ?? 0);
    }

    /**
     * Load a relationship for a given entity.
     *
     * @param object $entity The entity to load the relationship for
     * @param RelationInterface $relation The relationship metadata
     * @param array $data The raw data from the database (for morph relationships)
     * @param bool $forceLoad Whether to force loading even if already loaded
     * @return mixed The loaded relationship data
     */
    public function load(object $entity, RelationInterface $relation, array $data = [], bool $forceLoad = false): mixed
    {
        if ($relation instanceof ReflectionManyToMany) {
            return $this->loadManyToMany($entity, $relation);
        }

        if ($relation instanceof ReflectionMorphToMany) {
            return $this->loadMorphToMany($entity, $relation);
        }

        if ($relation instanceof ReflectionMorphedByMany) {
            return $this->loadMorphedByMany($entity, $relation);
        }

        if (!($relation instanceof ReflectionRelation)) {
            return null;
        }

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
            if ($ownedByRelation instanceof ReflectionRelation && $ownedByRelation->isManyToOne()) {
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
    private function loadManyToMany(object $entity, ReflectionRelation|ReflectionManyToMany $relation): array
    {
        $entityMetadata = $this->metadataRegistry->getMetadata($entity::class);
        $primaryKeyValue = $this->getPrimaryKeyValue($entity, $entityMetadata);

        $pivotTable = $relation->getPivotTableName();
        $foreignKey = $relation->getForeignPivotKey();
        $relatedKey = $relation->getRelatedPivotKey();

        $usePivotData = $relation instanceof ReflectionManyToMany && $relation->isMappingCollectionType();

        $pivotQb = $this->entityManager->createQueryBuilder()->from($pivotTable)->where($foreignKey, $primaryKeyValue);
        if (!$usePivotData) {
            $pivotQb->select($relatedKey);
        }

        $pivotResults = $pivotQb->getResult();
        $relatedIds = array_column($pivotResults, $relatedKey);

        if (empty($relatedIds)) {
            return [];
        }

        $targetEntity = $relation->getTargetEntity();
        $targetMetadata = $this->metadataRegistry->getMetadata($targetEntity);
        $targetPrimaryKey = $targetMetadata->getPrimaryKeyColumns()[0] ?? 'id';

        $entities = $this->entityManager->createQueryBuilder($targetEntity)
            ->where("$targetPrimaryKey IN (" . str_repeat('?,', count($relatedIds) - 1) . '?)', $relatedIds)
            ->getResult($targetEntity);

        if (!$usePivotData) {
            return $entities;
        }

        $entityMap = [];
        foreach ($entities as $e) {
            $entityMap[(string) $this->getPrimaryKeyValue($e, $targetMetadata)] = $e;
        }

        $items = [];
        foreach ($pivotResults as $pivotRow) {
            $id = (string) $pivotRow[$relatedKey];
            if (isset($entityMap[$id])) {
                $items[] = MappingItem::fromDatabase($entityMap[$id], $pivotRow);
            }
        }

        return $items;
    }

    private function loadMorphToMany(object $entity, ReflectionMorphToMany $relation): array
    {
        $entityMetadata = $this->metadataRegistry->getMetadata($entity::class);
        $ownerPK = $this->getPrimaryKeyValue($entity, $entityMetadata);

        $pivotResults = $this->entityManager->createQueryBuilder()
            ->select($relation->getTargetJoinColumn())
            ->from($relation->getTableName())
            ->where($relation->getTypeColumn(), $entity::class)
            ->where($relation->getOwnerJoinColumn(), $ownerPK)
            ->getResult();

        $targetIds = array_column($pivotResults, $relation->getTargetJoinColumn());

        if (empty($targetIds)) {
            return [];
        }

        $targetEntity = $relation->getTargetEntity();
        $targetMetadata = $this->metadataRegistry->getMetadata($targetEntity);
        $targetPK = $targetMetadata->getPrimaryKeyColumns()[0] ?? 'id';

        return $this->entityManager->createQueryBuilder($targetEntity)
            ->whereIn($targetPK, $targetIds)
            ->getResult($targetEntity);
    }

    private function loadMorphedByMany(object $entity, ReflectionMorphedByMany $relation): array
    {
        $entityMetadata = $this->metadataRegistry->getMetadata($entity::class);
        $ownerPK = $this->getPrimaryKeyValue($entity, $entityMetadata);
        $targetEntity = $relation->getTargetEntity();

        $pivotResults = $this->entityManager->createQueryBuilder()
            ->select($relation->getOwnerJoinColumn())
            ->from($relation->getTableName())
            ->where($relation->getTypeColumn(), $targetEntity)
            ->where($relation->getTargetJoinColumn(), $ownerPK)
            ->getResult();

        $morphIds = array_column($pivotResults, $relation->getOwnerJoinColumn());

        if (empty($morphIds)) {
            return [];
        }

        $targetMetadata = $this->metadataRegistry->getMetadata($targetEntity);
        $targetPK = $targetMetadata->getPrimaryKeyColumns()[0] ?? 'id';

        return $this->entityManager->createQueryBuilder($targetEntity)
            ->whereIn($targetPK, $morphIds)
            ->getResult($targetEntity);
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
