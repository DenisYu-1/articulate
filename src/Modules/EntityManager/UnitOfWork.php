<?php

namespace Articulate\Modules\EntityManager;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionProperty as ArticulateReflectionProperty;
use Articulate\Connection;
use Articulate\Modules\EntityManager\Proxy\ProxyInterface;
use Articulate\Modules\Generators\GeneratorRegistry;
use ReflectionProperty;

class UnitOfWork {
    private IdentityMap $identityMap;

    private ChangeTrackingStrategy $changeTrackingStrategy;

    private GeneratorRegistry $generatorRegistry;

    private EntityMetadataRegistry $metadataRegistry;

    /** @var array<int, EntityState> */
    private array $entityStates = [];

    /** @var array<int, object> */
    private array $scheduledInserts = [];

    /** @var array<int, object> */
    private array $scheduledUpdates = [];

    /** @var array<int, object> */
    private array $scheduledDeletes = [];

    /** @var array<int, object> */
    private array $entitiesByOid = [];

    private LifecycleCallbackManager $callbackManager;

    private Connection $connection;

    public function __construct(
        Connection $connection,
        ?ChangeTrackingStrategy $changeTrackingStrategy = null,
        ?GeneratorRegistry $generatorRegistry = null,
        ?LifecycleCallbackManager $callbackManager = null,
        ?EntityMetadataRegistry $metadataRegistry = null
    ) {
        $this->connection = $connection;
        $this->identityMap = new IdentityMap();
        $this->metadataRegistry = $metadataRegistry ?? new EntityMetadataRegistry();
        $this->changeTrackingStrategy = $changeTrackingStrategy ?? new DeferredImplicitStrategy($this->metadataRegistry);
        $this->generatorRegistry = $generatorRegistry ?? new GeneratorRegistry();
        $this->callbackManager = $callbackManager ?? new LifecycleCallbackManager();
    }

    public function persist(object $entity): void
    {
        $oid = spl_object_id($entity);
        $state = $this->getEntityState($entity);

        if ($state === EntityState::NEW) {
            // Call prePersist callbacks for new entities
            $this->callbackManager->invokeCallbacks($entity, 'prePersist');

            $id = $this->extractEntityId($entity);

            // If entity has an ID, it's likely already persisted
            if ($id !== null && $id !== '') {
                // Register as managed entity
                $this->registerManaged($entity, []);
                $this->entityStates[$oid] = EntityState::MANAGED;
                $this->entitiesByOid[$oid] = $entity;
            } else {
                // New entity without ID - schedule for insert
                $this->scheduledInserts[$oid] = $entity;
                $this->entityStates[$oid] = EntityState::MANAGED;
                $this->entitiesByOid[$oid] = $entity;
                $this->changeTrackingStrategy->trackEntity($entity, []);
            }
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
        $oid = spl_object_id($entity);
        $state = $this->getEntityState($entity);

        // Call preRemove callbacks
        $this->callbackManager->invokeCallbacks($entity, 'preRemove');

        if ($state === EntityState::MANAGED) {
            $this->scheduledDeletes[$oid] = $entity;
            $this->entityStates[$oid] = EntityState::REMOVED;

            // Remove from other schedules
            unset($this->scheduledInserts[$oid], $this->scheduledUpdates[$oid]);
            unset($this->entitiesByOid[$oid]);
        } elseif ($state === EntityState::NEW) {
            // Entity was never persisted, just remove from schedules
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
     * @return array{inserts: object[], updates: array{entity: object, changes: array}[], deletes: object[]}
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
        ];
    }

    /**
     * Clears all pending changes after they have been processed.
     */
    public function clearChanges(): void
    {
        $this->scheduledInserts = [];
        $this->scheduledUpdates = [];
        $this->scheduledDeletes = [];
    }

    /**
     * Executes post-operation callbacks for the given changes.
     * This should be called after the changes have been successfully executed.
     *
     * @param array{inserts: object[], updates: array{entity: object, changes: array}[], deletes: object[]} $changes
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

        // Call postRemove for deleted entities
        foreach ($changes['deletes'] as $entity) {
            $this->callbackManager->invokeCallbacks($entity, 'postRemove');
        }
    }

    public function registerManaged(object $entity, array $data): void
    {
        // TODO: Extract ID from entity based on metadata
        $id = $this->extractEntityId($entity);
        $oid = spl_object_id($entity);

        $this->identityMap->add($entity, $id);
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
        // TODO: Check if entity is in identity map
        // This requires extracting the ID from the entity
        return false;
    }

    /**
     * Get scheduled updates (for testing purposes).
     * @return array<int, object>
     */
    public function getScheduledUpdates(): array
    {
        return $this->scheduledUpdates;
    }

    public function clear(): void
    {
        $this->identityMap->clear();
        $this->entityStates = [];
        $this->entitiesByOid = [];
        $this->scheduledInserts = [];
        $this->scheduledUpdates = [];
        $this->scheduledDeletes = [];
        $this->changeTrackingStrategy = new DeferredImplicitStrategy($this->metadataRegistry);
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
