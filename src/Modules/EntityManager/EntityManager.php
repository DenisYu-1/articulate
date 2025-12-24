<?php

namespace Articulate\Modules\EntityManager;

use Articulate\Connection;
use Articulate\Modules\Generators\GeneratorRegistry;
use Articulate\Modules\QueryBuilder\QueryBuilder;

class EntityManager
{
    private Connection $connection;
    /** @var UnitOfWork[] */
    private array $unitOfWorks = [];
    private ChangeTrackingStrategy $changeTrackingStrategy;
    private HydratorInterface $hydrator;
    private \Articulate\Modules\QueryBuilder\QueryBuilder $queryBuilder;
    private GeneratorRegistry $generatorRegistry;

    public function __construct(
        Connection $connection,
        ?ChangeTrackingStrategy $changeTrackingStrategy = null,
        ?HydratorInterface $hydrator = null,
        ?GeneratorRegistry $generatorRegistry = null
    ) {
        $this->connection = $connection;
        $this->changeTrackingStrategy = $changeTrackingStrategy ?? new DeferredImplicitStrategy();
        $this->generatorRegistry = $generatorRegistry ?? new GeneratorRegistry();

        // Create default UnitOfWork
        $defaultUow = new UnitOfWork($this->changeTrackingStrategy, $this->generatorRegistry);
        $this->unitOfWorks[] = $defaultUow;

        // Initialize hydrator
        $this->hydrator = $hydrator ?? new ObjectHydrator($defaultUow);

        // Initialize QueryBuilder
        $this->queryBuilder = new \Articulate\Modules\QueryBuilder\QueryBuilder($this->connection, $this->hydrator);
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

        // TODO: Query database and get result row
        // For now, simulate with null (no database query yet)
        $rowData = null;

        if ($rowData === null) {
            return null;
        }

        // Hydrate the database row into an entity
        return $this->hydrator->hydrate($class, $rowData);
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
        $unitOfWork = new UnitOfWork($this->changeTrackingStrategy, $this->generatorRegistry);
        $this->unitOfWorks[] = $unitOfWork;
        return $unitOfWork;
    }

    // Query builder
    public function createQueryBuilder(?string $entityClass = null): \Articulate\Modules\QueryBuilder\QueryBuilder
    {
        $qb = new \Articulate\Modules\QueryBuilder\QueryBuilder($this->connection, $this->hydrator);

        if ($entityClass) {
            $qb->setEntityClass($entityClass);
        }

        return $qb;
    }

    // Get the main query builder instance
    public function getQueryBuilder(): \Articulate\Modules\QueryBuilder\QueryBuilder
    {
        return $this->queryBuilder;
    }

    private function getDefaultUnitOfWork(): UnitOfWork
    {
        return $this->unitOfWorks[0];
    }

    // Hydrator access (for advanced customization)
    public function getHydrator(): HydratorInterface
    {
        return $this->hydrator;
    }

    public function setHydrator(HydratorInterface $hydrator): void
    {
        $this->hydrator = $hydrator;
    }

}
