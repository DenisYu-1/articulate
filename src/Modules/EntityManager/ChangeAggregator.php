<?php

namespace Articulate\Modules\EntityManager;

/**
 * Aggregates and optimizes changes from multiple UnitOfWorks.
 *
 * This class collects entity changes from different UnitOfWorks and creates
 * an optimized execution plan that minimizes database queries by merging
 * compatible operations.
 */
class ChangeAggregator {
    /** @var array<string, array{insert?: object[], update?: array{entity: object, changes: array}[], delete?: object[]}> */
    private array $aggregatedChanges = [];

    public function __construct(
        private readonly EntityMetadataRegistry $metadataRegistry,
        private UpdateConflictResolutionStrategy $updateConflictResolutionStrategy,
    ) {
    }

    /**
     * Aggregates changes from multiple UnitOfWorks into an optimized execution plan.
     *
     * @param UnitOfWork[] $unitOfWorks
     * @return array{inserts: object[], updates: array{entity: object, changes: array}[], deletes: object[]}
     */
    public function aggregateChanges(array $unitOfWorks): array
    {
        $this->aggregatedChanges = [];

        foreach ($unitOfWorks as $unitOfWork) {
            $this->collectChangesFromUnitOfWork($unitOfWork);
        }

        return [
            'inserts' => $this->optimizeInserts(),
            'updates' => $this->updateConflictResolutionStrategy->resolve(
                $this->optimizeUpdates(),
                $this->metadataRegistry,
            ),
            'deletes' => $this->optimizeDeletes(),
        ];
    }

    public function setUpdateConflictResolutionStrategy(UpdateConflictResolutionStrategy $updateConflictResolutionStrategy): void
    {
        $this->updateConflictResolutionStrategy = $updateConflictResolutionStrategy;
    }

    /**
     * Collects changes from a single UnitOfWork and groups them by entity class.
     */
    private function collectChangesFromUnitOfWork(UnitOfWork $unitOfWork): void
    {
        $changeSets = $unitOfWork->getChangeSets();

        // Group by entity class
        foreach ($changeSets['inserts'] as $entity) {
            $class = $entity::class;
            $this->aggregatedChanges[$class]['insert'][] = $entity;
        }

        foreach ($changeSets['updates'] as $update) {
            $class = $update['entity']::class;
            $this->aggregatedChanges[$class]['update'][] = $update;
        }

        foreach ($changeSets['deletes'] as $entity) {
            $class = $entity::class;
            $this->aggregatedChanges[$class]['delete'][] = $entity;
        }
    }

    /**
     * Optimizes insert operations.
     * Currently just returns them as-is, but could be extended for batching.
     *
     * @return object[]
     */
    private function optimizeInserts(): array
    {
        $inserts = [];
        foreach ($this->aggregatedChanges as $classChanges) {
            if (isset($classChanges['insert'])) {
                $inserts = array_merge($inserts, $classChanges['insert']);
            }
        }

        return $inserts;
    }

    /**
     * Optimizes update operations by merging changes to the same entity.
     *
     * @return array{entity: object, changes: array}[]
     */
    private function optimizeUpdates(): array
    {
        $updates = [];

        foreach ($this->aggregatedChanges as $class => $classChanges) {
            if (!isset($classChanges['update'])) {
                continue;
            }

            // Group updates by entity identity
            $updatesByEntity = [];
            foreach ($classChanges['update'] as $update) {
                $entity = $update['entity'];
                $entityId = $this->getEntityIdentity($entity);

                if (!isset($updatesByEntity[$entityId])) {
                    $updatesByEntity[$entityId] = [
                        'entity' => $entity,
                        'changes' => [],
                    ];
                }

                // Merge changes - later changes override earlier ones
                $updatesByEntity[$entityId]['changes'] = array_merge(
                    $updatesByEntity[$entityId]['changes'],
                    $update['changes']
                );
            }

            $updates = array_merge($updates, array_values($updatesByEntity));
        }

        return $updates;
    }

    /**
     * Optimizes delete operations.
     * Note: If an entity is both updated and deleted, delete takes precedence.
     *
     * @return object[]
     */
    private function optimizeDeletes(): array
    {
        $deletes = [];

        foreach ($this->aggregatedChanges as $class => $classChanges) {
            if (!isset($classChanges['delete'])) {
                continue;
            }

            // Remove entities that are also being deleted from updates
            $deleteEntities = $classChanges['delete'];
            $updateEntities = $classChanges['update'] ?? [];

            // Filter out entities that are being deleted
            $deleteIdentities = array_map([$this, 'getEntityIdentity'], $deleteEntities);

            if (isset($this->aggregatedChanges[$class]['update'])) {
                $this->aggregatedChanges[$class]['update'] = array_filter(
                    $this->aggregatedChanges[$class]['update'],
                    function ($update) use ($deleteIdentities) {
                        $entityId = $this->getEntityIdentity($update['entity']);

                        return !in_array($entityId, $deleteIdentities);
                    }
                );
            }

            $deletes = array_merge($deletes, $deleteEntities);
        }

        return $deletes;
    }

    /**
     * Gets a unique identity for an entity based on its primary key.
     */
    private function getEntityIdentity(object $entity): string
    {
        $metadata = $this->metadataRegistry->getMetadata($entity::class);
        $primaryKeyColumns = $metadata->getPrimaryKeyColumns();

        if (empty($primaryKeyColumns)) {
            $idProperty = $metadata->getProperty('id');
            $id = $idProperty ? $idProperty->getValue($entity) : null;

            return $entity::class . ':' . ($id ?? spl_object_id($entity));
        }

        // Use primary key values
        $identityParts = [$entity::class];
        foreach ($primaryKeyColumns as $column) {
            $propertyName = $metadata->getPropertyNameForColumn($column);
            $property = $propertyName ? $metadata->getProperty($propertyName) : null;
            $value = $property ? $property->getValue($entity) : null;
            $identityParts[] = $value;
        }

        return implode(':', $identityParts);
    }
}
