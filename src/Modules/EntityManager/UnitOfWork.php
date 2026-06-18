<?php

namespace Articulate\Modules\EntityManager;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionProperty as ArticulateReflectionProperty;

use Articulate\Exceptions\ReadOnlyEntityException;
use Articulate\Exceptions\ScheduleConflictException;
use Articulate\Modules\EntityManager\Proxy\ProxyInterface;
use Articulate\Schema\EntityMetadataRegistry;
use Articulate\Schema\EntityRegistrarInterface;

class UnitOfWork implements EntityRegistrarInterface {
    private IdentityMap $identityMap;

    private ChangeTrackingStrategy $changeTrackingStrategy;

    private EntityMetadataRegistry $metadataRegistry;

    /** @var array<int, EntityState> */
    private array $entityStates = [];

    /** @var array<int, object> */
    private array $scheduledInserts = [];

    /** @var array<int, object> */
    private array $scheduledUpdates = [];

    /** @var array<int, object> */
    private array $scheduledDeletes = [];

    /** @var array<int, object> Entities removed via soft-delete (UPDATE instead of DELETE) */
    private array $scheduledSoftDeletes = [];

    /** @var array<int, object> */
    private array $entitiesByOid = [];

    /** @var array<int, true> OIDs registered via persist() with an explicit ID (not loaded from DB) */
    private array $explicitPersistOids = [];

    private LifecycleCallbackManager $callbackManager;

    public function __construct(
        ?ChangeTrackingStrategy $changeTrackingStrategy = null,
        ?LifecycleCallbackManager $callbackManager = null,
        ?EntityMetadataRegistry $metadataRegistry = null
    ) {
        $this->identityMap = new IdentityMap();
        $this->metadataRegistry = $metadataRegistry ?? new EntityMetadataRegistry();
        $this->changeTrackingStrategy = $changeTrackingStrategy ?? new DeferredImplicitStrategy($this->metadataRegistry);
        $this->callbackManager = $callbackManager ?? new LifecycleCallbackManager();
    }

    public function persist(object $entity): void
    {
        $reflectionEntity = new ReflectionEntity($entity::class);
        if ($reflectionEntity->isEntity() && $reflectionEntity->isReadOnly()) {
            throw new ReadOnlyEntityException(
                sprintf("Entity '%s' is marked as read-only and cannot be written.", $entity::class)
            );
        }

        $oid = spl_object_id($entity);
        $state = $this->getEntityState($entity);

        if ($state === EntityState::NEW) {
            // Call prePersist callbacks for new entities
            $this->callbackManager->invokeCallbacks($entity, 'prePersist');

            $id = $this->extractEntityId($entity);

            if ($id !== null && $id !== '') {
                $this->assertNoConflictingDelete($entity, $id);
                $this->explicitPersistOids[$oid] = true;
            }

            $this->scheduledInserts[$oid] = $entity;
            $this->entityStates[$oid] = EntityState::MANAGED;
            $this->entitiesByOid[$oid] = $entity;
            $this->changeTrackingStrategy->trackEntity($entity, []);
        } elseif ($state === EntityState::MANAGED) {
            // Call preUpdate callbacks for managed entities being updated
            $this->callbackManager->invokeCallbacks($entity, 'preUpdate');

            // Entity is already managed, ensure it's tracked for changes
            if (!isset($this->scheduledUpdates[$oid])) {
                $this->scheduledUpdates[$oid] = $entity;
            }
        }
    }

    public function remove(object $entity): void
    {
        $reflectionEntity = new ReflectionEntity($entity::class);
        if ($reflectionEntity->isEntity() && $reflectionEntity->isReadOnly()) {
            throw new ReadOnlyEntityException(
                sprintf("Entity '%s' is marked as read-only and cannot be written.", $entity::class)
            );
        }

        $oid = spl_object_id($entity);
        $state = $this->getEntityState($entity);

        $this->callbackManager->invokeCallbacks($entity, 'preRemove');

        if ($state === EntityState::MANAGED) {
            $softDeleteColumn = $this->getSoftDeleteColumn($entity);

            if ($softDeleteColumn !== null) {
                // Soft delete: set the delete field to now and schedule an UPDATE
                $this->setSoftDeleteField($entity, $softDeleteColumn);
                $this->scheduledSoftDeletes[$oid] = $entity;
            } else {
                $this->scheduledDeletes[$oid] = $entity;
            }

            $this->entityStates[$oid] = EntityState::REMOVED;
            unset($this->scheduledInserts[$oid], $this->scheduledUpdates[$oid]);
            unset($this->entitiesByOid[$oid]);
            $this->identityMap->remove($entity);

            $this->removeSiblingEntities($entity);
        } elseif ($state === EntityState::NEW) {
            unset($this->scheduledInserts[$oid]);
            unset($this->entityStates[$oid]);
            unset($this->entitiesByOid[$oid]);
        }
    }

    public function computeChangeSets(): void
    {
        // Compute changes for all managed entities that are not scheduled for insert
        // (entities scheduled for insert don't have meaningful change tracking yet)
        foreach ($this->entityStates as $oid => $state) {
            if ($state === EntityState::MANAGED && !isset($this->scheduledInserts[$oid])) {
                $entity = $this->getEntityByOid($oid);
                if ($entity && $this->hasChanges($entity)) {
                    if (!isset($this->scheduledUpdates[$oid])) {
                        // Detected dirty without an explicit persist() — invoke preUpdate now
                        // so callbacks like `updatedAt = now` are included in the change set.
                        $this->callbackManager->invokeCallbacks($entity, 'preUpdate');
                    }
                    $this->scheduledUpdates[$oid] = $entity;
                }
            }
        }
    }

    public function getEntityChangeSet(object $entity): array
    {
        return $this->changeTrackingStrategy->computeChangeSet($entity);
    }

    /**
     * Gets all pending changes without executing them.
     *
     * @return array{inserts: object[], updates: array{entity: object, changes: array}[], deletes: object[], softDeletes: object[]}
     */
    public function getChangeSets(): array
    {
        $this->computeChangeSets();

        return [
            'inserts' => array_values($this->scheduledInserts),
            'updates' => array_map(
                fn ($entity) => ['entity' => $entity, 'changes' => $this->changeTrackingStrategy->computeChangeSet($entity)],
                array_values($this->scheduledUpdates)
            ),
            'deletes' => array_values($this->scheduledDeletes),
            'softDeletes' => array_values($this->scheduledSoftDeletes),
        ];
    }

    /**
     * Clears all pending changes after they have been processed.
     */
    public function clearChanges(): void
    {
        foreach ($this->scheduledInserts as $entity) {
            $this->changeTrackingStrategy->refreshSnapshot($entity);
        }
        $this->scheduledInserts = [];
        $this->scheduledUpdates = [];
        $this->scheduledDeletes = [];
        $this->scheduledSoftDeletes = [];
        $this->explicitPersistOids = [];
    }

    /**
     * Executes post-operation callbacks for the given changes.
     * This should be called after the changes have been successfully executed.
     *
     * @param array{inserts: object[], updates: array{entity: object, changes: array}[], deletes: object[], softDeletes: object[]} $changes
     */
    public function executePostCallbacks(array $changes): void
    {
        // Call postPersist for inserted entities
        foreach ($changes['inserts'] as $entity) {
            $this->callbackManager->invokeCallbacks($entity, 'postPersist');
        }

        // Call postUpdate for updated entities
        foreach ($changes['updates'] as $update) {
            $this->callbackManager->invokeCallbacks($update['entity'], 'postUpdate');
        }

        // Call postRemove for hard-deleted entities
        foreach ($changes['deletes'] as $entity) {
            $this->callbackManager->invokeCallbacks($entity, 'postRemove');
        }

        // Call postRemove for soft-deleted entities
        foreach ($changes['softDeletes'] as $entity) {
            $this->callbackManager->invokeCallbacks($entity, 'postRemove');
        }
    }

    public function registerManaged(object $entity, array $data): void
    {
        $id = $this->extractEntityId($entity);
        $oid = spl_object_id($entity);

        $classForMap = $entity instanceof ProxyInterface
            ? $entity->getProxyEntityClass()
            : null;
        $this->identityMap->add($entity, $id, $classForMap);
        $this->entityStates[$oid] = EntityState::MANAGED;
        $this->entitiesByOid[$oid] = $entity;
        $this->changeTrackingStrategy->trackEntity($entity, $data);
    }

    public function tryGetById(string $class, mixed $id): ?object
    {
        return $this->identityMap->get($class, $id);
    }

    public function getEntityState(object $entity): EntityState
    {
        $oid = spl_object_id($entity);

        return $this->entityStates[$oid] ?? EntityState::NEW;
    }

    public function isInIdentityMap(object $entity): bool
    {
        $id = $this->extractEntityId($entity);
        if ($id === null) {
            return false;
        }

        $class = $entity instanceof ProxyInterface
            ? $entity->getProxyEntityClass()
            : $entity::class;

        return $this->identityMap->has($class, $id);
    }

    /**
     * Get scheduled updates (for testing purposes).
     * @return array<int, object>
     */
    public function getScheduledUpdates(): array
    {
        return $this->scheduledUpdates;
    }

    public function detach(object $entity): void
    {
        $oid = spl_object_id($entity);

        $this->identityMap->remove($entity);
        $this->changeTrackingStrategy->untrackEntity($entity);
        unset($this->entityStates[$oid], $this->entitiesByOid[$oid], $this->explicitPersistOids[$oid]);
        unset($this->scheduledInserts[$oid], $this->scheduledUpdates[$oid]);
        unset($this->scheduledDeletes[$oid], $this->scheduledSoftDeletes[$oid]);
    }

    public function clear(): void
    {
        $trackedEntities = $this->entitiesByOid
            + $this->scheduledInserts
            + $this->scheduledUpdates
            + $this->scheduledDeletes
            + $this->scheduledSoftDeletes;

        foreach ($trackedEntities as $entity) {
            $this->changeTrackingStrategy->untrackEntity($entity);
        }

        $this->identityMap->clear();
        $this->entityStates = [];
        $this->entitiesByOid = [];
        $this->explicitPersistOids = [];
        $this->scheduledInserts = [];
        $this->scheduledUpdates = [];
        $this->scheduledDeletes = [];
        $this->scheduledSoftDeletes = [];
    }

    private function assertNoConflictingDelete(object $entity, mixed $id): void
    {
        try {
            $tableName = $this->metadataRegistry->getMetadata($entity::class)->getTableName();
        } catch (\InvalidArgumentException) {
            return;
        }

        $key = $this->identityMap->generateKey($id);

        foreach ($this->scheduledDeletes as $deleted) {
            try {
                $deletedTable = $this->metadataRegistry->getMetadata($deleted::class)->getTableName();
            } catch (\InvalidArgumentException) {
                continue;
            }

            if ($deletedTable !== $tableName) {
                continue;
            }

            if ($this->identityMap->generateKey($this->extractEntityId($deleted)) === $key) {
                throw new ScheduleConflictException(sprintf(
                    "Cannot persist '%s' with id '%s': a delete is already scheduled for the same row (table '%s'). Flush before persisting.",
                    $entity::class,
                    $key,
                    $tableName,
                ));
            }
        }
    }

    private function hasChanges(object $entity): bool
    {
        $changes = $this->changeTrackingStrategy->computeChangeSet($entity);

        return !empty($changes);
    }

    private function getEntityByOid(int $oid): ?object
    {
        return $this->entitiesByOid[$oid] ?? null;
    }

    private function extractEntityId(object $entity): mixed
    {
        // For proxies, get the identifier from the proxy interface
        if ($entity instanceof ProxyInterface) {
            return $entity->getProxyIdentifier();
        }

        $primaryKeyProperty = $this->findPrimaryKeyProperty($entity);
        if ($primaryKeyProperty !== null) {
            // Use metadata-driven property access instead of direct reflection
            return $primaryKeyProperty->getValue($entity);
        }

        return null;
    }

    private function extractEntitySnapshot(object $entity): array
    {
        $metadata = $this->metadataRegistry->getMetadata($entity::class);
        $snapshot = [];

        foreach ($metadata->getProperties() as $propertyName => $property) {
            $snapshot[$property->getColumnName()] = $property->getValue($entity);
        }

        return $snapshot;
    }

    /**
     * Mark all managed sibling entities (same table, same id) as REMOVED.
     * Does not add them to scheduledDeletes — the DB row is already covered by the primary entity's DELETE.
     */
    private function removeSiblingEntities(object $removed): void
    {
        try {
            $tableName = $this->metadataRegistry->getMetadata($removed::class)->getTableName();
        } catch (\InvalidArgumentException) {
            return;
        }

        $removedKey = $this->identityMap->generateKey($this->extractEntityId($removed));

        foreach (array_keys($this->entitiesByOid) as $siblingOid) {
            if (!array_key_exists($siblingOid, $this->entitiesByOid)) {
                continue;
            }

            $sibling = $this->entitiesByOid[$siblingOid];

            if (($this->entityStates[$siblingOid] ?? EntityState::NEW) !== EntityState::MANAGED) {
                continue;
            }

            try {
                $siblingTable = $this->metadataRegistry->getMetadata($sibling::class)->getTableName();
            } catch (\InvalidArgumentException) {
                continue;
            }

            if ($siblingTable !== $tableName) {
                continue;
            }

            if ($this->identityMap->generateKey($this->extractEntityId($sibling)) !== $removedKey) {
                continue;
            }

            if (array_key_exists($siblingOid, $this->explicitPersistOids)) {
                throw new ScheduleConflictException(sprintf(
                    "Cannot remove '%s': a persist is already scheduled for '%s' sharing the same row (table '%s', id '%s'). Flush before removing.",
                    $removed::class,
                    $sibling::class,
                    $tableName,
                    $removedKey,
                ));
            }

            $this->entityStates[$siblingOid] = EntityState::REMOVED;
            unset($this->scheduledInserts[$siblingOid], $this->scheduledUpdates[$siblingOid]);
            unset($this->entitiesByOid[$siblingOid]);
            $this->identityMap->remove($sibling);
        }
    }

    private function getSoftDeleteColumn(object $entity): ?string
    {
        try {
            $metadata = $this->metadataRegistry->getMetadata($entity::class);

            return $metadata->isSoftDeleteable() ? $metadata->getSoftDeleteColumn() : null;
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private function setSoftDeleteField(object $entity, string $columnName): void
    {
        try {
            $metadata = $this->metadataRegistry->getMetadata($entity::class);
            $fieldName = $metadata->getSoftDeleteField();

            if ($fieldName === null) {
                return;
            }

            foreach ($metadata->getProperties() as $property) {
                if ($property->getColumnName() === $columnName) {
                    $property->setValue($entity, new \DateTimeImmutable());

                    return;
                }
            }
        } catch (\InvalidArgumentException) {
        }
    }

    private function findPrimaryKeyProperty(object $entity): ?ArticulateReflectionProperty
    {
        $reflectionEntity = new ReflectionEntity($entity::class);

        // First try to find primary key from entity metadata
        foreach (iterator_to_array($reflectionEntity->getEntityProperties()) as $property) {
            // Only check ReflectionProperty objects for primary key, not ReflectionRelation
            if ($property instanceof ArticulateReflectionProperty && $property->isPrimaryKey()) {
                return $property; // Return the metadata object directly
            }
        }

        return null;
    }
}
