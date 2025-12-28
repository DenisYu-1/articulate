<?php

namespace Articulate\Modules\EntityManager;

use Articulate\Attributes\Reflection\ReflectionEntity;

/**
 * Aggregates and optimizes changes from multiple UnitOfWorks.
 *
 * This class collects entity changes from different UnitOfWorks and creates
 * an optimized execution plan that minimizes database queries by merging
 * compatible operations.
 */
class ChangeAggregator {
    /** @var array<string, array{insert: object[], update: array{entity: object, changes: array}[], delete: object[]}> */
    private array $aggregatedChanges = [];

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
            'updates' => $this->optimizeUpdates(),
            'deletes' => $this->optimizeDeletes(),
        ];
    }

    /**
     * Collects changes from a single UnitOfWork and groups them by entity class.
     */
    private function collectChangesFromUnitOfWork(UnitOfWork $unitOfWork): void
    {
        // Get scheduled operations from the UnitOfWork
        $scheduledInserts = $this->getScheduledInserts($unitOfWork);
        $scheduledUpdates = $this->getScheduledUpdates($unitOfWork);
        $scheduledDeletes = $this->getScheduledDeletes($unitOfWork);

        // Group by entity class
        foreach ($scheduledInserts as $entity) {
            $class = $entity::class;
            $this->aggregatedChanges[$class]['insert'][] = $entity;
        }

        foreach ($scheduledUpdates as $entity) {
            $class = $entity::class;
            $changes = $unitOfWork->getEntityChangeSet($entity);
            $this->aggregatedChanges[$class]['update'][] = [
                'entity' => $entity,
                'changes' => $changes,
            ];
        }

        foreach ($scheduledDeletes as $entity) {
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
        $reflectionEntity = new ReflectionEntity($entity::class);
        $primaryKeyColumns = $reflectionEntity->getPrimaryKeyColumns();

        if (empty($primaryKeyColumns)) {
            // Fallback to 'id' property
            $idProperty = $reflectionEntity->getProperty('id');
            $idProperty->setAccessible(true);
            $id = $idProperty->getValue($entity);

            return $entity::class . ':' . ($id ?? 'null');
        }

        // Use primary key values
        $identityParts = [$entity::class];
        foreach ($primaryKeyColumns as $column) {
            // Find the property for this column
            foreach (iterator_to_array($reflectionEntity->getEntityProperties()) as $property) {
                if ($property->getColumnName() === $column) {
                    $fieldName = $property->getFieldName();
                    $reflectionProperty = new \ReflectionProperty($entity, $fieldName);
                    $reflectionProperty->setAccessible(true);
                    $value = $reflectionProperty->getValue($entity);
                    $identityParts[] = $value;

                    break;
                }
            }
        }

        return implode(':', $identityParts);
    }

    // Methods to access UnitOfWork private properties via reflection
    // These would ideally be replaced with proper getters in UnitOfWork

    private function getScheduledInserts(UnitOfWork $unitOfWork): array
    {
        $reflection = new \ReflectionClass($unitOfWork);
        $property = $reflection->getProperty('scheduledInserts');
        $property->setAccessible(true);

        return $property->getValue($unitOfWork);
    }

    private function getScheduledUpdates(UnitOfWork $unitOfWork): array
    {
        $reflection = new \ReflectionClass($unitOfWork);
        $property = $reflection->getProperty('scheduledUpdates');
        $property->setAccessible(true);

        return $property->getValue($unitOfWork);
    }

    private function getScheduledDeletes(UnitOfWork $unitOfWork): array
    {
        $reflection = new \ReflectionClass($unitOfWork);
        $property = $reflection->getProperty('scheduledDeletes');
        $property->setAccessible(true);

        return $property->getValue($unitOfWork);
    }
}
