# Entity Manager Implementation Plan - Articulate ORM

## Overview
This document outlines the implementation plan for the Entity Manager component of Articulate ORM. The Entity Manager bridges migrations (schema) and query building (data access), focusing on context-bounded entity management, hydration, memory efficiency, and the Unit of Work pattern.

---

## 1. Core Architecture

### 1.1 EntityManager Class
**Purpose**: Central API for all entity operations

**Responsibilities**:
- Entity persistence (persist, remove, flush)
- Entity retrieval (find, findAll, getReference)
- Transaction management
- Unit of Work coordination
- Identity map management
- Context switching for context-bounded entities
- Holds connection to database

**Key Methods**:
```php
class EntityManager
{
    // Persistence operations
    public function persist(object $entity): void;
    public function remove(object $entity): void;
    public function flush(): void;
    public function clear(): void;
    
    // Retrieval operations
    public function find(string $class, mixed $id): ?object;
    public function findAll(string $class): array;
    public function getReference(string $class, mixed $id): object;
    public function refresh(object $entity): void;
    
    // Transaction management
    public function beginTransaction(): void;
    public function commit(): void;
    public function rollback(): void;
    public function transactional(callable $callback): mixed;
    
    // Unit of Work access
    public function getUnitOfWork(): UnitOfWork;
    
    // Create new unit of work for scoped operations
    public function createUnitOfWork(): UnitOfWork;
}
```

---

## 2. Unit of Work (UoW)

### 2.1 UnitOfWork Class
**Purpose**: Track entity changes and coordinate database operations

**Core Responsibilities**:
- Track entity states (new, managed, removed, detached)
- Detect changes via change tracking strategies
- Order operations (inserts, updates, deletes) to respect constraints
- Combine changes from multiple scopes into optimal queries
- Manage entity lifecycle callbacks

**State Management**:
```php
enum EntityState
{
    case NEW;      // Entity created but not yet persisted
    case MANAGED;  // Entity loaded from or persisted to database
    case REMOVED;  // Entity marked for deletion
}

class UnitOfWork
{
    // State tracking
    private array $identityMap = [];        // [class => [id => entity]]
    private array $entityStates = [];       // [oid => EntityState]
    private array $originalData = [];       // [oid => [field => value]]
    private array $scheduledInserts = [];   // [oid => entity]
    private array $scheduledUpdates = [];   // [oid => entity]
    private array $scheduledDeletes = [];   // [oid => entity]
    
    // Change detection
    public function persist(object $entity): void;
    public function remove(object $entity): void;
    public function computeChangeSets(): void;
    public function getEntityChangeSet(object $entity): array;
    
    // Commit operations
    public function commit(): void;
    
    // Identity map
    public function registerManaged(object $entity, array $data): void;
    public function tryGetById(string $class, mixed $id): ?object;
    
    // State queries
    public function getEntityState(object $entity): EntityState;
    public function isInIdentityMap(object $entity): bool;
}
```

### 2.2 Change Tracking Strategies

**Strategy 1: Deferred Implicit (Default)**
- Track original state on entity load
- Compare current vs original on flush
- Memory overhead: stores original data for all managed entities
- Use case: Standard operations, most common

**Strategy 2: Deferred Explicit**
- Require explicit `EntityManager::persist()` call to track changes
- Only track changes for explicitly persisted entities
- Memory overhead: minimal, only for modified entities
- Use case: Read-heavy operations

**Configuration**: Set on EntityManager level, can be overridden on UnitOfWork level, with package config default.

```php
interface ChangeTrackingStrategy
{
    public function trackEntity(object $entity, array $originalData): void;
    public function computeChangeSet(object $entity): array;
}

class DeferredImplicitStrategy implements ChangeTrackingStrategy { }
class DeferredExplicitStrategy implements ChangeTrackingStrategy { }
```

### 2.3 Scoped UnitOfWork

**Purpose**: Enable memory-efficient operations with isolated change tracking

**Concept**:
- UnitOfWork that tracks entities independently from other UnitOfWork instances
- Can be cleared without affecting other UnitOfWork instances
- Changes are separated, but could be merged into same query when global EM flushing
- Enables processing large datasets in chunks
- All UnitOfWork instances are equal - no parent/child relationships, only horizontal relations

```php
class UnitOfWork
{
    private array $identityMap = [];
    private array $changes = [];
    
    public function persist(object $entity): void;
    public function remove(object $entity): void;
    public function clear(): void;
    
    // Flushes its own changes only
    public function flush(): void;
    
    // Detach entity from current scope, still might be tracked in EM
    public function detach(object $entity): void;
}
```

**Usage Example**:
```php
// Process 100,000 users in batches
$uow = $em->createUow();
$batchSize = 1000;

for ($i = 0; $i < 100; $i++) {
    // $qb is either connected to uow, or we're in "detached" mode where you have to attach entities to uow/em manually
    $users = $qb->select('u')->from(User::class)->setFirstResult($i * $batchSize)
        ->setMaxResults($batchSize)->getResult();
    
    // users are attached to active UoW
    foreach ($users as $user) {
        $user->status = 'active';
        $scope->persist($user);
    }
    
    $scope->flush();  // Persists to parent UoW
    $scope->clear();  // Clears local memory, global state retains
}

$em->flush(); // Commits all changes in optimized batch
```

---

## 3. Entity Hydration

### 3.1 Hydrator Interface
**Purpose**: Convert database rows to entity objects and vice versa

```php
interface HydratorInterface
{
    // Database -> Entity
    public function hydrate(string $class, array $data, object $entity = null): object;
    
    // Entity -> Database
    public function extract(object $entity): array;
    
    // Partial hydration for lazy-loaded properties
    public function hydratePartial(object $entity, array $data): void;
}
```

### 3.2 Hydration Strategies

**ObjectHydrator (Default)**
- Creates entity instances via reflection
- Sets properties via reflection (bypasses constructor)
- Handles relations, nested objects, and type conversion
- Use case: Full entity hydration

**ArrayHydrator**
- Returns associative arrays instead of objects
- Useful for read-only queries, reporting
- Lower memory footprint
- Use case: Simple queries, DTOs

**ScalarHydrator**
- Returns single scalar values
- Use case: COUNT, SUM, aggregate queries

**PartialHydrator**
- Hydrates only specified fields
- Use case: Projections, performance optimization

### 3.3 Hydration Process

```php
class ObjectHydrator implements HydratorInterface
{
    public function hydrate(string $class, array $data, object $entity = null): object
    {
        // 1. Create entity instance (or use provided)
        $entity ??= $this->createEntity($class);
        
        // 2. Convert database types to PHP types
        $convertedData = $this->convertTypes($class, $data);
        
        // 3. Set scalar properties
        $this->setProperties($entity, $convertedData);
        
        // 4. Handle relations (lazy proxies or eager loading)
        $this->hydrateRelations($entity, $convertedData);
        
        // 5. Register in identity map
        $this->uow->registerManaged($entity, $data);
        
        return $entity;
    }
    
    private function convertTypes(string $class, array $data): array
    {
        $metadata = $this->metadataFactory->getMetadataFor($class);
        $converted = [];
        
        foreach ($metadata->getProperties() as $property) {
            $dbValue = $data[$property->columnName] ?? null;
            $converted[$property->name] = $this->typeRegistry
                ->convertToPHP($property->type, $dbValue);
        }
        
        return $converted;
    }
}
```

---

## 4. Identity Map

### 4.1 Purpose
- Ensure single instance per entity (per identity)
- Avoid duplicate queries for same entity
- Maintain object references and prevent inconsistencies

### 4.2 Implementation

```php
class IdentityMap
{
    private array $entities = []; // [className => [id => entity]]
    
    public function add(object $entity, mixed $id): void;
    public function get(string $class, mixed $id): ?object;
    public function has(string $class, mixed $id): bool;
    public function remove(object $entity): void;
    public function clear(?string $class = null): void;
    
    // Composite keys support
    public function generateKey(mixed $id): string;
}
```

### 4.3 Identity Map Behavior

```php
// First fetch - loads from database
$user1 = $em->find(User::class, 1);

// Second fetch - returns same instance from identity map
$user2 = $em->find(User::class, 1);

assert($user1 === $user2); // Same object instance
```

---

## 5. Context-Bounded Entity Support

### 5.1 Concept
Different entity classes map to the same table but expose different fields and relationships based on context.

### 5.2 Context Manager

```php
class ContextManager
{
    // Determine which entity class to use for a table
    public function resolveEntityClass(string $tableName): string;

    // Get all entity classes for a table
    public function getEntityClassesForTable(string $tableName): array;

    // Check if entity is part of current context
    public function isInContext(object $entity): bool;
}
```

**Note**: Contexts are not switched globally. Different UnitOfWork instances are used for different contexts, avoiding global state changes.

### 5.3 Context Resolution

```php
// Example: Authentication context uses LoginUser
$uow = $em->createUnitOfWork();
$loginUser = $uow->find(LoginUser::class, 1); // Has login, password fields

// Profile context uses User entity
$uowProfile = $em->createUnitOfWork();
$user = $uowProfile->find(User::class, 1); // Has name, phones, groups, cart relations

// Same table, different views
assert($loginUser->id === $user->id); // Same database record
assertFalse($loginUser === $user); // Different instances

// Data consistency note: When flushing changes from different contexts for the same record,
// latest change overwrites previous ones. This is an edge case that may require configuration
// to throw errors if detected. Document this behavior prominently.
```

### 5.4 Identity Map with Context

**Challenge**: How to handle identity map when multiple entity classes point to same table?

**Solution**:

**For simplicity: separate Identity Maps per Entity Class**
- Each entity class has its own identity map entry
- `LoginUser` instance and `User` instance can coexist
- Allows different hydration levels for same record
- Clear separation of contexts

```php
$loginUser = $em->find(LoginUser::class, 1);
$user = $em->find(User::class, 1);

// Both exist independently in identity map
// identityMap['LoginUser'][1] = $loginUser
// identityMap['User'][1] = $user
```

---

## 6. Lazy Loading & Proxies

### 6.1 Purpose
Defer loading of related entities until accessed, improving performance for queries that don't need relations.

### 6.2 Proxy Pattern

```php
interface Proxy
{
    public function __load(): void;
    public function __isInitialized(): bool;
}

class LazyLoadingProxy implements Proxy
{
    private bool $initialized = false;
    private ?object $realEntity = null;
    
    public function __load(): void
    {
        if (!$this->initialized) {
            $this->realEntity = $this->entityManager->find($this->class, $this->id);
            $this->initialized = true;
        }
    }
    
    public function __get(string $name): mixed
    {
        $this->__load();
        return $this->realEntity->$name;
    }
}
```

### 6.3 Proxy Generation

**Runtime Proxy Generation**:
- Use inheritance or composition
- Override `__get`, `__set`, `__call` to trigger loading
- Simpler but with overhead

**Code Generation (Preferred for Production)**:
- Generate proxy classes ahead of time
- No runtime overhead
- Store in cache directory

```php
class ProxyFactory
{
    public function getProxy(string $class, mixed $id): Proxy;
    public function generateProxyClass(string $class): string;
}
```

### 6.4 Collection Proxies

```php
class PersistentCollection implements ArrayAccess, Iterator
{
    private bool $initialized = false;
    private ?array $collection = null;
    
    public function initialize(): void
    {
        if (!$this->initialized) {
            $this->collection = $this->loadCollection();
            $this->initialized = true;
        }
    }
    
    // ArrayAccess methods trigger initialization
}
```

---

## 7. Relationship Handling

### 7.1 Relation Loading Strategies

**Lazy Loading (Default)**
- Relations loaded on first access
- Reduces initial query overhead
- Risk of N+1 queries

**Eager Loading**
- Relations loaded with main entity via JOINs
- Prevents N+1 queries
- Heavier initial query

**Extra Lazy (For Collections)**
- Collection metadata loaded but not items
- Individual operations (count, contains) use targeted queries
- Use case: Large collections

### 7.2 Relation Hydration

```php
class RelationHydrator
{
    public function hydrateOneToOne(object $entity, PropertyMetadata $property, array $data): void
    {
        // Create proxy for lazy loading or eager load
        $relatedEntity = $this->shouldEagerLoad($property)
            ? $this->loadRelatedEntity($property, $data)
            : $this->createProxy($property, $data);
            
        $this->setProperty($entity, $property->name, $relatedEntity);
    }
    
    public function hydrateOneToMany(object $entity, PropertyMetadata $property): void
    {
        // Always use PersistentCollection for lazy loading
        $collection = new PersistentCollection($this->em, $property, $entity);
        $this->setProperty($entity, $property->name, $collection);
    }
    
    public function hydrateManyToMany(object $entity, PropertyMetadata $property): void
    {
        // Similar to OneToMany but queries through junction table
        $collection = new PersistentCollection($this->em, $property, $entity);
        $this->setProperty($entity, $property->name, $collection);
    }
}
```

### 7.3 Inverse Side Management

**Concept**: Keep both sides of bidirectional relations in sync.

```php
class User {
    #[OneToMany(targetEntity: Phone::class, ownedBy: 'user')]
    public array $phones;
}

class Phone {
    #[ManyToOne(targetEntity: User::class)]
    public User $user;
}

// When adding a phone, sync both sides
$user->phones[] = $phone;
$phone->user = $user; // Should be done automatically by UoW
```

**Implementation**: Use lifecycle callbacks or UoW hooks to maintain consistency.

---

## 8. Lifecycle Callbacks

### 8.1 Callback Events

```php
enum LifecycleEvent
{
    case PRE_PERSIST;   // Before INSERT
    case POST_PERSIST;  // After INSERT
    case PRE_UPDATE;    // Before UPDATE
    case POST_UPDATE;   // After UPDATE
    case PRE_REMOVE;    // Before DELETE
    case POST_REMOVE;   // After DELETE
    case POST_LOAD;     // After entity hydration
}
```

### 8.2 Callback Attributes

```php
use Articulate\Attributes\Lifecycle\PrePersist;
use Articulate\Attributes\Lifecycle\PostLoad;

#[Entity]
class User
{
    #[PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new DateTime();
    }
    
    #[PostLoad]
    public function onPostLoad(): void
    {
        // Initialize computed properties
    }
}
```

### 8.3 Callback Execution

```php
class LifecycleEventManager
{
    public function triggerEvent(LifecycleEvent $event, object $entity): void
    {
        $metadata = $this->metadataFactory->getMetadataFor($entity::class);
        
        foreach ($metadata->getCallbacksFor($event) as $method) {
            $entity->$method();
        }
    }
}
```

---

## 9. Transaction Management

### 9.1 Transaction API

```php
// Manual transaction control
$em->beginTransaction();
try {
    $em->persist($user);
    $em->flush();
    $em->commit();
} catch (\Exception $e) {
    $em->rollback();
    throw $e;
}

// Transactional helper
$em->transactional(function (EntityManager $em) {
    $em->persist($user);
    $em->flush();
    // Automatic commit on success, rollback on exception
});
```

### 9.2 Nested Transactions (Savepoints)

```php
$em->beginTransaction();
$em->persist($user);

$em->beginTransaction(); // Creates savepoint
$em->persist($group);

try {
    $em->flush();
    $em->commit(); // Commits savepoint
} catch (\Exception $e) {
    $em->rollback(); // Rollback to savepoint
}

$em->commit(); // Commits outer transaction
```

---

## 10. Memory Management

### 10.1 Entity Manager Clear

```php
// Clear all entities from memory globally (affects all UnitOfWork instances)
$em->clear();

// Clear specific entity class globally
$em->clear(User::class);
```

### 10.2 UnitOfWork Clear

```php
// Clear entities only from this UnitOfWork scope
$uow->clear();

// Clear specific entity class from this UnitOfWork scope
$uow->clear(User::class);
```

### 10.2 Entity Detachment

```php
// Detach entity from UoW
$em->detach($user);

// Changes no longer tracked
$user->name = 'New Name';
$em->flush(); // No UPDATE query
```

### 10.3 Memory Optimization Strategies

**Strategy 1: Batch Processing with Scopes**
```php
$scope = $em->createUnitOfWork();
foreach ($largeDataset as $item) {
    $entity = $scope->find(Entity::class, $item['id']);
    // Modify entity
    $scope->flush();
}
$scope->clear(); // Free memory
$em->flush(); // Final commit
```

**Strategy 2: Read-Only Queries**
```php
// Use array hydration for read-only operations
$users = $qb->select('u')->from(User::class)
    ->getResult(ArrayHydrator::class);
// No identity map pollution
```
```php
// You can also set read-only flag on query builder
$users = $qb->select('u')->from(User::class)
    ->setReadOnly();
// Entity will be in memory, but will not be participated in calculation for changes
```

**Strategy 3: Iteration**
```php
// Stream results one by one
$query = $qb->select('u')->from(User::class)->getQuery();
foreach ($query->iterate() as $user) {
    // Process user
    $em->detach($user); // Free after processing
}
```

Advanced cursor methods on database-level should be implemented further in QueryBuilder section (part 3)

---

## 11. Reference Loading (Proxies vs Database)

### 11.1 getReference()

**Purpose**: Create a proxy without database query, useful when you only need to set a foreign key.

```php
// Instead of querying the database
$user = $em->find(User::class, 1); // SELECT query
$phone->user = $user;

// Use reference
$user = $em->getReference(User::class, 1); // No query, creates proxy
$phone->user = $user;
$em->persist($phone);
$em->flush(); // Only INSERT for phone, no SELECT for user
```

### 11.2 Implementation

```php
public function getReference(string $class, mixed $id): object
{
    // Check identity map first
    if ($entity = $this->uow->tryGetById($class, $id)) {
        return $entity;
    }
    
    // Create uninitialized proxy
    $proxy = $this->proxyFactory->getProxy($class, $id);
    $this->uow->registerManaged($proxy, []);
    
    return $proxy;
}
```

---

## 12. Key Design Decisions

### 12.1 Context-Bounded Entities: Separate vs Shared Identity Maps
**Decision**: Use separate identity maps per entity class
**Rationale**: Simpler implementation, clearer semantics, allows different hydration levels

### 12.2 Change Tracking Strategy
**Decision**: Default to deferred implicit, support explicit strategy
**Rationale**: Balances ease of use with memory efficiency options

### 12.3 Proxy Implementation
**Decision**: Runtime proxies initially, code generation for production
**Rationale**: Faster development, optimized for production

### 12.4 Scoped Unit of Work
**Decision**: Multiple independent UnitOfWork instances with merge-on-flush
**Rationale**: Enables memory-efficient batch processing while maintaining global optimization. No parent/child relationships - all UnitOfWork instances are equal with horizontal relations.

### 12.5 Modular architecture
**Decision**: Strong emphasis on separation of concerns and loose coupling, modules separation in a folder structure
**Rationale**: Readability, extensibility, testability

---

## 13. Dependencies & Integration

### 13.1 Required Components from Part 1 (Migrations)
- Entity metadata (attributes, relations, properties)
- Type registry and converters
- Database connection abstraction
- Schema reader/writer

### 13.2 Integration Points for Part 3 (Query Builder)
- Query builder creates result sets that need hydration
- Query builder needs access to metadata for query construction
- Query builder should support different hydration modes

---

## 14. Testing Strategy

### 14.1 Unit Tests
- UnitOfWork state transitions
- Identity map operations
- Hydration correctness
- Change detection accuracy
- Context resolution

### 14.2 Integration Tests
- Full CRUD cycles
- Relation loading (lazy, eager)
- Transaction rollback scenarios
- Memory management with large datasets
- Context-bounded entity scenarios

### 14.3 Performance Tests (last step)
- Identity map lookup speed
- Hydration performance
- Memory usage with varying dataset sizes
- Scoped UoW overhead
- Proxy initialization costs

---

## 15. Open Questions & Future Enhancements

### 15.1 Open Questions & Notes
1. Locking will be done in part 3.
2. Proxy initialization and identity map interaction - to be determined during implementation.
3. Transaction nesting implementation details - discuss when we get there.
4. How to test lazy loading without triggering N+1 queries in automated tests.
5. Memory leak testing for long-running processes with context-bounded entities.
6. Concurrent access scenarios with multiple UnitOfWork instances.

### 15.2 Future Enhancements
- Event system (more granular than lifecycle callbacks)?
- Query result cache – part 3
- Second-level cache for entities – part 3
- Partial object updates (UPDATE only changed fields) – should be impelemnted in part 2
- Bulk operations optimization
- Connection pooling strategy
- Statement caching
- Repository pattern abstraction – part 3
- Custom hydrators for specific use cases

---

## 16. Example Usage Scenarios

### Scenario 1: Basic CRUD
```php
// Create
$user = new User();
$user->name = 'John Doe';
$em->persist($user);
$em->flush();

// Read
$user = $em->find(User::class, $user->id);

// Update
$user->name = 'Jane Doe';
$em->flush(); // Auto-detected

// Delete
$em->remove($user);
$em->flush();
```

### Scenario 2: Context-Bounded Entities
```php
// Authentication context
$uowAuth = $em->createUnitOfWork();
$loginUser = $uowAuth->find(LoginUser::class, 1);
if (password_verify($password, $loginUser->password)) {
    // Profile context (separate UnitOfWork)
    $uowProfile = $em->createUnitOfWork();
    $user = $uowProfile->find(User::class, 1);
    // Access full profile data
}
```

### Scenario 3: Memory-Efficient Batch Processing
```php
$uow = $em->createUnitOfWork();

for ($page = 0; $page < 100; $page++) {
    $users = $qb->select('u')->from(User::class)
        ->setFirstResult($page * 1000)
        ->setMaxResults(1000)
        ->getResult();

    foreach ($users as $user) {
        $user->status = 'processed';
        $uow->persist($user);
    }

    $uow->flush();
    $uow->clear(); // Free memory
}

$em->flush(); // Batch commit
```

### Scenario 4: Lazy Loading Relations
```php
$user = $em->find(User::class, 1);
// No query for phones yet

foreach ($user->phones as $phone) {
    // Query triggered on first access
    echo $phone->number;
}
```

---

## Conclusion

This plan provides a comprehensive roadmap for implementing the Entity Manager component of Articulate ORM. The design balances:

- **Ease of use**: Simple API for common operations
- **Performance**: Identity map, lazy loading, batch operations
- **Memory efficiency**: Scoped UoW, detachment, streaming
- **Flexibility**: Context-bounded entities, multiple hydration strategies
- **Correctness**: UoW pattern, change tracking, transaction safety

The phased approach ensures incremental progress with testable milestones, building from core functionality to advanced features.