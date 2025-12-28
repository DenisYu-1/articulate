<?php

namespace Articulate\Modules\EntityManager;

use Articulate\Connection;
use Articulate\Modules\Generators\GeneratorRegistry;
use Articulate\Modules\QueryBuilder\QueryBuilder;

class EntityManager {
    private Connection $connection;

    /** @var UnitOfWork[] */
    private array $unitOfWorks = [];

    private ChangeTrackingStrategy $changeTrackingStrategy;

    private HydratorInterface $hydrator;

    private QueryBuilder $queryBuilder;

    private GeneratorRegistry $generatorRegistry;

    private EntityMetadataRegistry $metadataRegistry;

    private LifecycleCallbackManager $callbackManager;

    private ?Proxy\ProxyManager $proxyManager = null;

    public function __construct(
        Connection $connection,
        ?ChangeTrackingStrategy $changeTrackingStrategy = null,
        ?HydratorInterface $hydrator = null,
        ?GeneratorRegistry $generatorRegistry = null,
        ?EntityMetadataRegistry $metadataRegistry = null
    ) {
        $this->connection = $connection;
        $this->changeTrackingStrategy = $changeTrackingStrategy ?? new DeferredImplicitStrategy();
        $this->generatorRegistry = $generatorRegistry ?? new GeneratorRegistry();
        $this->metadataRegistry = $metadataRegistry ?? new EntityMetadataRegistry();

        // Initialize callback manager
        $this->callbackManager = new LifecycleCallbackManager();

        // Create default UnitOfWork
        $defaultUow = new UnitOfWork($this->changeTrackingStrategy, $this->generatorRegistry, $this->callbackManager);
        $this->unitOfWorks[] = $defaultUow;

        // Create relationship loader
        $relationshipLoader = new RelationshipLoader($this, $this->metadataRegistry);

        // Initialize proxy system
        $proxyGenerator = new Proxy\ProxyGenerator($this->metadataRegistry);
        $this->proxyManager = new Proxy\ProxyManager(
            $this,
            $this->metadataRegistry,
            $proxyGenerator
        );

        // Initialize hydrator
        $this->hydrator = $hydrator ?? new ObjectHydrator($defaultUow, $relationshipLoader, $this->callbackManager);

        // Initialize QueryBuilder
        $this->queryBuilder = new QueryBuilder($this->connection, $this->hydrator, $this->metadataRegistry);
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
        $this->connection->rollbackTransaction();
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
        $unitOfWork = new UnitOfWork($this->changeTrackingStrategy, $this->generatorRegistry, $this->callbackManager);
        $this->unitOfWorks[] = $unitOfWork;

        return $unitOfWork;
    }

    // Query builder
    public function createQueryBuilder(?string $entityClass = null): QueryBuilder
    {
        $qb = new QueryBuilder($this->connection, $this->hydrator, $this->metadataRegistry);

        if ($entityClass) {
            $qb->setEntityClass($entityClass);
        }

        return $qb;
    }

    // Get the main query builder instance
    public function getQueryBuilder(): QueryBuilder
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

    /**
     * Load a relationship for an entity.
     */
    public function loadRelation(object $entity, string $relationName): mixed
    {
        $metadata = $this->metadataRegistry->getMetadata($entity::class);
        $relation = $metadata->getRelation($relationName);

        if (!$relation) {
            throw new \InvalidArgumentException("Relation '$relationName' not found on entity " . $entity::class);
        }

        $relationshipLoader = new RelationshipLoader($this, $this->metadataRegistry);

        return $relationshipLoader->load($entity, $relation, true);
    }

    /**
     * Create a new collection for relationship management.
     */
    public function createCollection(array $items = []): Collection
    {
        return new Collection($items);
    }

    /**
     * Get the metadata registry.
     */
    public function getMetadataRegistry(): EntityMetadataRegistry
    {
        return $this->metadataRegistry;
    }

    /**
     * Create a lazy-loading proxy for an entity.
     */
    public function createProxy(string $entityClass, mixed $identifier): Proxy\ProxyInterface
    {
        return $this->proxyManager->createProxy($entityClass, $identifier);
    }
}
