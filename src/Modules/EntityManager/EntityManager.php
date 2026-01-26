<?php

namespace Articulate\Modules\EntityManager;

use Articulate\Connection;
use Articulate\Exceptions\EntityNotFoundException;
use Articulate\Modules\Generators\GeneratorRegistry;
use Articulate\Modules\QueryBuilder\QueryBuilder;
use Articulate\Modules\Repository\RepositoryFactory;
use Articulate\Modules\Repository\RepositoryInterface;
use Articulate\Utils\TypeRegistry;

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

    private ChangeAggregator $changeAggregator;

    private QueryExecutor $queryExecutor;

    private ?Proxy\ProxyManager $proxyManager = null;

    private RepositoryFactory $repositoryFactory;

    private UpdateConflictResolutionStrategy $updateConflictResolutionStrategy;

    public function __construct(
        Connection $connection,
        ?ChangeTrackingStrategy $changeTrackingStrategy = null,
        ?HydratorInterface $hydrator = null,
        ?GeneratorRegistry $generatorRegistry = null,
        ?EntityMetadataRegistry $metadataRegistry = null,
        ?QueryExecutor $queryExecutor = null,
        ?UpdateConflictResolutionStrategy $updateConflictResolutionStrategy = null
    ) {
        $this->connection = $connection;
        $this->generatorRegistry = $generatorRegistry ?? new GeneratorRegistry();
        $this->metadataRegistry = $metadataRegistry ?? new EntityMetadataRegistry();

        // Create default change tracking strategy with metadata registry
        $this->changeTrackingStrategy = $changeTrackingStrategy ?? new DeferredImplicitStrategy($this->metadataRegistry);

        // Initialize callback manager
        $this->callbackManager = new LifecycleCallbackManager();

        // Initialize change aggregator and query executor
        $this->updateConflictResolutionStrategy = $updateConflictResolutionStrategy ?? new MergeUpdateConflictResolutionStrategy();
        $this->changeAggregator = new ChangeAggregator($this->metadataRegistry, $this->updateConflictResolutionStrategy);
        $this->queryExecutor = $queryExecutor ?? new QueryExecutor($this->connection, $this->generatorRegistry);

        // Create default UnitOfWork
        $defaultUow = new UnitOfWork($this->connection, $this->changeTrackingStrategy, $this->generatorRegistry, $this->callbackManager, $this->metadataRegistry);
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

        // Initialize type registry for type conversion
        $typeRegistry = new TypeRegistry();

        // Initialize hydrator
        $this->hydrator = $hydrator ?? new ObjectHydrator($defaultUow, $relationshipLoader, $this->callbackManager, $typeRegistry);

        // Initialize QueryBuilder
        $this->queryBuilder = new QueryBuilder($this->connection, $this->hydrator, $this->metadataRegistry);

        // Initialize RepositoryFactory
        $this->repositoryFactory = new RepositoryFactory($this);
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
        // Aggregate changes from all UnitOfWorks
        $aggregatedChanges = $this->changeAggregator->aggregateChanges($this->unitOfWorks);

        // Execute the changes in proper order (respecting foreign key constraints)
        $this->executeChanges($aggregatedChanges);

        // Execute post-operation callbacks
        foreach ($this->unitOfWorks as $unitOfWork) {
            $unitOfWork->executePostCallbacks($aggregatedChanges);
        }

        // Clear changes from all UnitOfWorks
        foreach ($this->unitOfWorks as $unitOfWork) {
            $unitOfWork->clearChanges();
        }
    }

    public function clear(): void
    {
        foreach ($this->unitOfWorks as $unitOfWork) {
            $unitOfWork->clear();
        }
    }

    public function flushAndClear(): void
    {
        $this->flush();
        $this->clear();
    }

    /**
     * Execute aggregated changes in proper order respecting foreign key constraints.
     *
     * @param array{inserts: object[], updates: array{entity: object, changes: array}[], deletes: object[]} $changes
     */
    private function executeChanges(array $changes): void
    {
        // Execute inserts in dependency order (parents before children)
        $orderedInserts = $this->orderEntitiesByDependencies($changes['inserts'], 'insert');
        foreach ($orderedInserts as $entity) {
            $this->queryExecutor->executeInsert($entity);
        }

        // Execute updates (order doesn't matter for foreign key constraints)
        foreach ($changes['updates'] as $update) {
            if (isset($update['table'])) {
                $this->queryExecutor->executeUpdateByTable(
                    tableName: $update['table'],
                    columnChanges: $update['set'],
                    whereClause: $update['where'],
                    whereValues: $update['whereValues'],
                );

                continue;
            }

            $this->queryExecutor->executeUpdate($update['entity'], $update['changes']);
        }

        // Execute deletes in reverse dependency order (children before parents)
        $orderedDeletes = $this->orderEntitiesByDependencies($changes['deletes'], 'delete');
        foreach ($orderedDeletes as $entity) {
            $this->queryExecutor->executeDelete($entity);
        }
    }

    /**
     * Order entities by their foreign key dependencies.
     *
     * For inserts: parents before children
     * For deletes: children before parents
     *
     * @param object[] $entities
     * @param string $operation 'insert' or 'delete'
     * @return object[]
     */
    private function orderEntitiesByDependencies(array $entities, string $operation): array
    {
        if (empty($entities)) {
            return $entities;
        }

        // Build dependency graph
        $graph = $this->buildDependencyGraph($entities, $operation);

        // Perform topological sort
        return $this->topologicalSort($entities, $graph);
    }

    /**
     * Build a dependency graph for the given entities.
     *
     * @param object[] $entities
     * @param string $operation 'insert' or 'delete'
     * @return array<string, string[]> Entity class => array of entities it depends on
     */
    private function buildDependencyGraph(array $entities, string $operation): array
    {
        $graph = [];

        // Group entities by class for easier lookup
        $entitiesByClass = [];
        foreach ($entities as $entity) {
            $entitiesByClass[$entity::class][] = $entity;
        }

        // Initialize graph for all entity classes
        foreach (array_keys($entitiesByClass) as $entityClass) {
            $graph[$entityClass] = [];
        }

        foreach ($entities as $entity) {
            $entityClass = $entity::class;

            // Get entity metadata
            $metadata = $this->metadataRegistry->getMetadata($entityClass);

            // Check relationships
            foreach ($metadata->getRelations() as $relation) {
                $targetClass = $relation->getTargetEntity();

                // Only consider relationships to entities that are also being operated on
                if ($targetClass === null || !isset($entitiesByClass[$targetClass])) {
                    continue;
                }

                if ($operation === 'insert') {
                    // For inserts: entities with relationships that require foreign keys depend on their targets
                    // (children must be inserted after parents)
                    if ($relation->isForeignKeyRequired()) {
                        $graph[$entityClass][] = $targetClass;
                    }
                } elseif ($operation === 'delete') {
                    // For deletes: target entities depend on entities that reference them
                    // (parents must be deleted after children)
                    if ($relation->isForeignKeyRequired()) {
                        $graph[$targetClass][] = $entityClass;
                    }
                }
            }
        }

        // Remove duplicates
        foreach ($graph as $class => $dependencies) {
            $graph[$class] = array_unique($dependencies);
        }

        return $graph;
    }

    /**
     * Perform topological sort on entities based on dependency graph.
     *
     * @param object[] $entities
     * @param array<string, string[]> $graph
     * @return object[]
     */
    private function topologicalSort(array $entities, array $graph): array
    {
        $result = [];
        $visited = [];
        $visiting = [];

        // Group entities by class
        $entitiesByClass = [];
        foreach ($entities as $entity) {
            $entitiesByClass[$entity::class][] = $entity;
        }

        // Helper function for DFS topological sort
        $visit = function ($class) use (&$visit, &$result, &$visited, &$visiting, $graph, $entitiesByClass) {
            if (isset($visited[$class])) {
                return;
            }

            if (isset($visiting[$class])) {
                // Cycle detected - for now, just continue (could be improved)
                return;
            }

            $visiting[$class] = true;

            // Visit dependencies first
            if (isset($graph[$class])) {
                foreach ($graph[$class] as $dependency) {
                    $visit($dependency);
                }
            }

            $visiting[$class] = false;
            $visited[$class] = true;

            // Add all entities of this class to result
            if (isset($entitiesByClass[$class])) {
                $result = array_merge($result, $entitiesByClass[$class]);
            }
        };

        // Visit all classes
        foreach (array_keys($entitiesByClass) as $class) {
            $visit($class);
        }

        return $result;
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

        // Query database for the entity
        $metadata = $this->metadataRegistry->getMetadata($class);
        $tableName = $metadata->getTableName();
        $primaryKeyColumns = $metadata->getPrimaryKeyColumns();

        // For now, assume single primary key
        if (empty($primaryKeyColumns)) {
            return null;
        }

        $primaryKeyColumn = $primaryKeyColumns[0];

        // Build and execute query directly to get raw data
        $columnNames = $metadata->getColumnNames();

        // Add morph columns from relations
        foreach ($metadata->getRelations() as $relation) {
            if ($relation->isMorphTo()) {
                $columnNames[] = $relation->getMorphTypeColumnName();
                $columnNames[] = $relation->getMorphIdColumnName();
            }
        }

        $qb = $this->createQueryBuilder()
            ->select(...$columnNames)
            ->from($tableName)
            ->where("$primaryKeyColumn = ?", $id)
            ->limit(1);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();
        $statement = $this->connection->executeQuery($sql, $params);
        $rawResults = $statement->fetchAll();

        if (empty($rawResults)) {
            return null;
        }

        $rawData = $rawResults[0];

        // Hydrate the entity
        $entity = $this->hydrator->hydrate($class, $rawData);

        // Register the entity as managed in the unit of work
        $this->getUnitOfWork()->registerManaged($entity, $rawData);

        return $entity;
    }

    public function findAll(string $class): array
    {
        // Get entity metadata
        $metadata = $this->metadataRegistry->getMetadata($class);
        $tableName = $metadata->getTableName();

        // Build and execute query to get all records
        $qb = $this->createQueryBuilder()
            ->select(...$metadata->getColumnNames())
            ->from($tableName);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();
        $statement = $this->connection->executeQuery($sql, $params);
        $rawResults = $statement->fetchAll();

        if (empty($rawResults)) {
            return [];
        }

        $entities = [];

        // Hydrate each entity and register in unit of work
        foreach ($rawResults as $rawData) {
            $entity = $this->hydrator->hydrate($class, $rawData);
            $this->getUnitOfWork()->registerManaged($entity, $rawData);
            $entities[] = $entity;
        }

        return $entities;
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

        // Create a proxy for lazy loading
        if ($this->proxyManager === null) {
            throw new \RuntimeException('Proxy manager is not available');
        }

        $proxy = $this->proxyManager->createProxy($class, $id);

        // Register the proxy as managed in the unit of work
        // Note: We pass empty array for original data since we haven't loaded it yet
        $this->getUnitOfWork()->registerManaged($proxy, []);

        return $proxy;
    }

    public function refresh(object $entity): void
    {
        // Get entity class name (handle proxies)
        $entityClass = $entity instanceof Proxy\ProxyInterface
            ? $entity->getProxyEntityClass()
            : $entity::class;

        // Get entity metadata
        $metadata = $this->metadataRegistry->getMetadata($entityClass);
        $primaryKeyColumns = $metadata->getPrimaryKeyColumns();

        if (empty($primaryKeyColumns)) {
            throw new \RuntimeException("Entity {$entityClass} has no primary key");
        }

        // For now, assume single primary key
        $primaryKeyColumn = $primaryKeyColumns[0];
        $propertyName = $metadata->getPropertyNameForColumn($primaryKeyColumn);

        if (!$propertyName) {
            throw new \RuntimeException("Cannot determine primary key property for entity {$entityClass}");
        }

        // Get the entity's ID
        $reflectionProperty = new \ReflectionProperty($entity, $propertyName);
        $reflectionProperty->setAccessible(true);
        $id = $reflectionProperty->getValue($entity);

        if ($id === null) {
            throw new \RuntimeException("Entity {$entityClass} has null primary key value");
        }

        // Query database for fresh data
        $tableName = $metadata->getTableName();
        $qb = $this->createQueryBuilder()
            ->select(...$metadata->getColumnNames())
            ->from($tableName)
            ->where("$primaryKeyColumn = ?", $id)
            ->limit(1);

        $sql = $qb->getSQL();
        $params = $qb->getParameters();
        $statement = $this->connection->executeQuery($sql, $params);
        $rawResults = $statement->fetchAll();

        if (empty($rawResults)) {
            throw new EntityNotFoundException("Entity {$entityClass} with ID {$id} not found in database");
        }

        $freshData = $rawResults[0];

        // If it's a proxy, initialize it with fresh data
        if ($entity instanceof Proxy\ProxyInterface && !$entity->isProxyInitialized()) {
            $this->hydrator->hydrate($entityClass, $freshData, $entity);
            $entity->markProxyInitialized();
        } else {
            // For regular entities, update properties with fresh data
            $this->updateEntityProperties($entity, $freshData, $metadata);
        }

        // Update the unit of work's original data to reflect the fresh state
        $this->getUnitOfWork()->registerManaged($entity, $freshData);
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
        $unitOfWork = new UnitOfWork($this->connection, $this->changeTrackingStrategy, $this->generatorRegistry, $this->callbackManager, $this->metadataRegistry);
        $this->unitOfWorks[] = $unitOfWork;

        return $unitOfWork;
    }

    public function setUpdateConflictResolutionStrategy(UpdateConflictResolutionStrategy $updateConflictResolutionStrategy): void
    {
        $this->updateConflictResolutionStrategy = $updateConflictResolutionStrategy;
        $this->changeAggregator->setUpdateConflictResolutionStrategy($updateConflictResolutionStrategy);
    }

    public function getUpdateConflictResolutionStrategy(): UpdateConflictResolutionStrategy
    {
        return $this->updateConflictResolutionStrategy;
    }

    // Query builder
    public function createQueryBuilder(?string $entityClass = null): QueryBuilder
    {
        $qb = new QueryBuilder($this->connection, $this->hydrator, $this->metadataRegistry);

        // Set the default UnitOfWork for entity management
        $qb->setUnitOfWork($this->getDefaultUnitOfWork());

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

    /**
     * Get a repository for the given entity class.
     *
     * If the entity specifies a custom repository class via the Entity attribute,
     * that class will be used. Otherwise, a generic EntityRepository will be returned.
     */
    public function getRepository(string $entityClass): RepositoryInterface
    {
        return $this->repositoryFactory->getRepository($entityClass);
    }

    /**
     * Update entity properties with fresh data from database.
     */
    private function updateEntityProperties(object $entity, array $data, EntityMetadata $metadata): void
    {
        $typeRegistry = new TypeRegistry();

        foreach ($metadata->getProperties() as $propertyName => $property) {
            $columnName = $property->getColumnName();
            if (array_key_exists($columnName, $data)) {
                $value = $data[$columnName];
                // Convert database value back to PHP type
                $converter = $typeRegistry->getConverter($property->getType());
                if ($converter) {
                    $phpValue = $converter->convertToPHP($value);
                } else {
                    $phpValue = $value; // Basic conversion - most types don't need special handling
                }

                $reflectionProperty = new \ReflectionProperty($entity, $propertyName);
                $reflectionProperty->setAccessible(true);
                $reflectionProperty->setValue($entity, $phpValue);
            }
        }
    }
}
