<?php

namespace Articulate\Modules\EntityManager;

use Articulate\Connection;

class EntityManager
{
    private Connection $connection;
    /** @var UnitOfWork[] */
    private array $unitOfWorks = [];
    private ChangeTrackingStrategy $changeTrackingStrategy;

    public function __construct(
        Connection $connection,
        ?ChangeTrackingStrategy $changeTrackingStrategy = null
    ) {
        $this->connection = $connection;
        $this->changeTrackingStrategy = $changeTrackingStrategy ?? new DeferredImplicitStrategy();
        $this->unitOfWorks[] = new UnitOfWork($this->changeTrackingStrategy);
    }

    // Persistence operations
    public function persist(object $entity): void
    {
        $this->getDefaultUnitOfWork()->persist($entity);
    }

    public function remove(object $entity): void
    {
        $this->getDefaultUnitOfWork()->remove($entity);
    }

    public function flush(): void
    {
        foreach ($this->unitOfWorks as $unitOfWork) {
            $unitOfWork->commit();
        }
    }

    public function clear(): void
    {
        foreach ($this->unitOfWorks as $unitOfWork) {
            $unitOfWork->clear();
        }
    }

    // Retrieval operations
    public function find(string $class, mixed $id): ?object
    {
        // Check identity maps of all unit of works first
        foreach ($this->unitOfWorks as $unitOfWork) {
            $entity = $unitOfWork->tryGetById($class, $id);
            if ($entity) {
                return $entity;
            }
        }

        // TODO: Query database and hydrate entity
        // For now, return null
        return null;
    }

    public function findAll(string $class): array
    {
        // TODO: Query database and hydrate all entities
        return [];
    }

    public function getReference(string $class, mixed $id): object
    {
        // Check identity maps of all unit of works first
        foreach ($this->unitOfWorks as $unitOfWork) {
            $entity = $unitOfWork->tryGetById($class, $id);
            if ($entity) {
                return $entity;
            }
        }

        // TODO: Create proxy and register in identity map
        // For now, throw an exception
        throw new \RuntimeException('getReference not yet implemented');
    }

    public function refresh(object $entity): void
    {
        // TODO: Reload entity data from database
        throw new \RuntimeException('refresh not yet implemented');
    }

    // Transaction management
    public function beginTransaction(): void
    {
        $this->connection->beginTransaction();
    }

    public function commit(): void
    {
        $this->flush();
        $this->connection->commit();
    }

    public function rollback(): void
    {
        $this->connection->rollback();
    }

    public function transactional(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    // Unit of Work access
    public function getUnitOfWork(): UnitOfWork
    {
        return $this->getDefaultUnitOfWork();
    }

    // Create new unit of work, mostly for scopes
    public function createUnitOfWork(): UnitOfWork
    {
        $unitOfWork = new UnitOfWork($this->changeTrackingStrategy);
        $this->unitOfWorks[] = $unitOfWork;
        return $unitOfWork;
    }

    private function getDefaultUnitOfWork(): UnitOfWork
    {
        return $this->unitOfWorks[0];
    }

}
