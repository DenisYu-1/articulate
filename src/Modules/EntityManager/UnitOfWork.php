<?php

namespace Articulate\Modules\EntityManager;

class UnitOfWork
{
    private IdentityMap $identityMap;
    private ChangeTrackingStrategy $changeTrackingStrategy;

    /** @var array<int, EntityState> */
    private array $entityStates = [];

    /** @var array<int, object> */
    private array $scheduledInserts = [];

    /** @var array<int, object> */
    private array $scheduledUpdates = [];

    /** @var array<int, object> */
    private array $scheduledDeletes = [];

    public function __construct(
        ?ChangeTrackingStrategy $changeTrackingStrategy = null
    ) {
        $this->identityMap = new IdentityMap();
        $this->changeTrackingStrategy = $changeTrackingStrategy ?? new DeferredImplicitStrategy();
    }

    public function persist(object $entity): void
    {
        $oid = spl_object_id($entity);
        $state = $this->getEntityState($entity);

        if ($state === EntityState::NEW) {
            $this->scheduledInserts[$oid] = $entity;
            $this->entityStates[$oid] = EntityState::MANAGED;
            $this->changeTrackingStrategy->trackEntity($entity, []);
        } elseif ($state === EntityState::MANAGED) {
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

        if ($state === EntityState::MANAGED) {
            $this->scheduledDeletes[$oid] = $entity;
            $this->entityStates[$oid] = EntityState::REMOVED;

            // Remove from other schedules
            unset($this->scheduledInserts[$oid], $this->scheduledUpdates[$oid]);
        } elseif ($state === EntityState::NEW) {
            // Entity was never persisted, just remove from schedules
            unset($this->scheduledInserts[$oid]);
            unset($this->entityStates[$oid]);
        }
    }

    public function computeChangeSets(): void
    {
        // Compute changes for all managed entities
        foreach ($this->entityStates as $oid => $state) {
            if ($state === EntityState::MANAGED) {
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

    public function commit(): void
    {
        $this->computeChangeSets();

        // TODO: Execute inserts, updates, deletes in proper order
        // respecting foreign key constraints

        // For now, just clear schedules
        $this->scheduledInserts = [];
        $this->scheduledUpdates = [];
        $this->scheduledDeletes = [];
    }

    public function registerManaged(object $entity, array $data): void
    {
        // TODO: Extract ID from entity based on metadata
        $id = $this->extractEntityId($entity);

        $this->identityMap->add($entity, $id);
        $this->entityStates[spl_object_id($entity)] = EntityState::MANAGED;
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

    public function clear(): void
    {
        $this->identityMap->clear();
        $this->entityStates = [];
        $this->scheduledInserts = [];
        $this->scheduledUpdates = [];
        $this->scheduledDeletes = [];
        $this->changeTrackingStrategy = new DeferredImplicitStrategy();
    }

    private function hasChanges(object $entity): bool
    {
        $changes = $this->changeTrackingStrategy->computeChangeSet($entity);
        return !empty($changes);
    }

    private function getEntityByOid(int $oid): ?object
    {
        // This is a simplified approach - in practice, we'd maintain a reverse lookup
        // For now, search through all tracked entities
        foreach ($this->entityStates as $entityOid => $state) {
            if ($entityOid === $oid) {
                // We'd need a way to get the entity from oid
                // This is a placeholder - proper implementation would maintain entity references
                return null;
            }
        }

        return null;
    }

    private function extractEntityId(object $entity): mixed
    {
        // TODO: Extract ID based on entity metadata (primary key)
        // For now, assume there's an 'id' property
        return $entity->id ?? null;
    }
}
