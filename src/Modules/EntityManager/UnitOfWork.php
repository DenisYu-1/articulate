<?php

namespace Articulate\Modules\EntityManager;

use Articulate\Modules\Generators\GeneratorRegistry;

class UnitOfWork
{
    private IdentityMap $identityMap;

    private ChangeTrackingStrategy $changeTrackingStrategy;

    private GeneratorRegistry $generatorRegistry;

    /** @var array<int, EntityState> */
    private array $entityStates = [];

    /** @var array<int, object> */
    private array $scheduledInserts = [];

    /** @var array<int, object> */
    private array $scheduledUpdates = [];

    /** @var array<int, object> */
    private array $scheduledDeletes = [];

    public function __construct(
        ?ChangeTrackingStrategy $changeTrackingStrategy = null,
        ?GeneratorRegistry $generatorRegistry = null
    ) {
        $this->identityMap = new IdentityMap();
        $this->changeTrackingStrategy = $changeTrackingStrategy ?? new DeferredImplicitStrategy();
        $this->generatorRegistry = $generatorRegistry ?? new GeneratorRegistry();
    }

    public function persist(object $entity): void
    {
        $oid = spl_object_id($entity);
        $state = $this->getEntityState($entity);

        if ($state === EntityState::NEW) {
            $id = $this->extractEntityId($entity);

            // If entity has an ID, it's likely already persisted
            if ($id !== null && $id !== '') {
                // Register as managed entity
                $this->registerManaged($entity, []);
                $this->entityStates[$oid] = EntityState::MANAGED;
            } else {
                // New entity without ID - schedule for insert
                $this->scheduledInserts[$oid] = $entity;
                $this->entityStates[$oid] = EntityState::MANAGED;
                $this->changeTrackingStrategy->trackEntity($entity, []);
            }
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

        // TODO: Execute operations in proper order respecting foreign key constraints
        // For now: inserts, then updates, then deletes

        $this->executeInserts();
        $this->executeUpdates();
        $this->executeDeletes();

        // Clear schedules
        $this->scheduledInserts = [];
        $this->scheduledUpdates = [];
        $this->scheduledDeletes = [];
    }

    private function executeInserts(): void
    {
        foreach ($this->scheduledInserts as $entity) {
            $this->executeInsert($entity);
        }
    }

    private function executeUpdates(): void
    {
        foreach ($this->scheduledUpdates as $entity) {
            $this->executeUpdate($entity);
        }
    }

    private function executeDeletes(): void
    {
        foreach ($this->scheduledDeletes as $entity) {
            $this->executeDelete($entity);
        }
    }

    private function executeInsert(object $entity): void
    {
        // TODO: Generate INSERT SQL and execute
        // For now, simulate ID generation for entities without IDs

        $id = $this->extractEntityId($entity);
        if ($id === null || $id === '') {
            // Simulate auto-generated ID
            $generatedId = $this->generateNextId($entity::class);

            // Set the generated ID on the entity
            $this->setEntityId($entity, $generatedId);

            // Update identity map with new ID
            $this->identityMap->remove($entity); // Remove old entry if any
            $this->identityMap->add($entity, $generatedId);
        }
    }

    private function executeUpdate(object $entity): void
    {
        // TODO: Generate UPDATE SQL and execute
        // For now, just mark as executed
    }

    private function executeDelete(object $entity): void
    {
        // TODO: Generate DELETE SQL and execute
        // For now, just mark as executed
    }

    private function generateNextId(string $entityClass): mixed
    {
        // Use reflection to find the primary key generator type
        $reflectionEntity = new \Articulate\Attributes\Reflection\ReflectionEntity($entityClass);

        foreach ($reflectionEntity->getEntityProperties() as $property) {
            if ($property->isPrimaryKey()) {
                $generatorType = $property->getGeneratorType();

                if ($generatorType !== null) {
                    // Use specified generator
                    $generator = $this->generatorRegistry->getGenerator($generatorType);

                    return $generator->generate($entityClass);
                }

                // Fall back to auto-increment if AutoIncrement attribute is present
                if ($property->isAutoIncrement()) {
                    $generator = $this->generatorRegistry->getGenerator('auto_increment');

                    return $generator->generate($entityClass);
                }

                break;
            }
        }

        // Default to auto-increment for backward compatibility
        $generator = $this->generatorRegistry->getDefaultGenerator();

        return $generator->generate($entityClass);
    }

    private function setEntityId(object $entity, mixed $id): void
    {
        // TODO: Use entity metadata to find ID property
        // For now, assume 'id' property exists
        $reflection = new \ReflectionClass($entity);
        if ($reflection->hasProperty('id')) {
            $property = $reflection->getProperty('id');
            $property->setAccessible(true);
            $property->setValue($entity, $id);
        }
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
        $reflection = new \ReflectionClass($entity);
        if ($reflection->hasProperty('id')) {
            $property = $reflection->getProperty('id');
            $property->setAccessible(true);

            return $property->getValue($entity);
        }

        return null;
    }
}
