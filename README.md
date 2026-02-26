# Articulate

<p align="center">
  <img src="logo.svg" alt="Articulate Logo" width="80" height="80">
</p>

A modern, context-driven PHP ORM library that enables domain-aware entity management and memory-efficient operations through bounded contexts. 
Work in progress.

## Badges

[![CI](https://github.com/DenisYu-1/articulate/workflows/QA/badge.svg)](https://github.com/DenisYu-1/articulate/actions)
[![Mutation testing](https://img.shields.io/badge/Mutation%20Score-83%25+-brightgreen)](https://github.com/DenisYu-1/articulate/actions)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.4-8892BF.svg)](https://php.net/)

## Main Concepts

### Context-Bounded Entities

Context-bounded entities allow multiple entity classes to point to the same database table while exposing different fields and relationships based on the specific use case. This enables better separation of concerns and more focused domain modeling.

**Example:** Different user representations for different contexts:
- `LoginUser` entity with `login` and `password` fields for authentication
- `User` entity with full profile information, relationships to phones, groups, and cart

```php
#[Entity(tableName: 'user')]
class LoginUser
{
    #[PrimaryKey]
    public int $id;

    #[Property(maxLength: 120)]
    public string $login;

    #[Property(maxLength: 120)]
    public string $password;
}

#[Entity] // Defaults to table name 'user'
class User
{
    #[PrimaryKey]
    public int $id;

    #[Property(maxLength: 120)]
    public string $name;

    #[OneToMany(ownedBy: 'user', targetEntity: Phone::class)]
    public array $phones;

    #[ManyToMany(targetEntity: Group::class, referencedBy: 'users')]
    public array $groups;

    #[OneToOne(targetEntity: Cart::class, referencedBy: 'user')]
    public Cart $cart;
}
```

When entities share the same table, Articulate intelligently merges compatible column definitions and validates for conflicts.

### Memory-Efficient Unit of Work Management

Articulate provides flexible unit-of-work management that allows tracking entity changes in scoped contexts while maintaining global change tracking for optimal database operations.

**Key Benefits:**
- **Memory Efficiency**: Clear entities from memory that are no longer needed within specific operations
- **Scoped Tracking**: Different units of work can track their own entities independently
- **Global Optimization**: Entity manager combines all unit-of-work changes into minimal database queries during flush operations

This approach is particularly valuable when:
- Processing large datasets where memory usage needs to be controlled
- Performing complex business operations that span multiple contexts
- Managing long-running processes with varying entity lifecycles

## Type Mapping System

Articulate provides a flexible type mapping system that automatically converts between PHP types and database types.

### Built-in Type Mappings

- `bool` ↔ `TINYINT(1)` (with automatic conversion)
- `int` ↔ `INT`
- `float` ↔ `FLOAT`
- `string` ↔ `VARCHAR(255)` (configurable length)
- `DateTimeInterface` implementations ↔ `DATETIME`

### Class and Interface Mappings

You can register mappings for PHP classes and interfaces:

```php
use Articulate\Utils\TypeRegistry;

// Register DateTimeInterface (done automatically with high priority)
$registry->registerClassMapping(\DateTimeInterface::class, 'DATETIME');

// All classes implementing DateTimeInterface will use DATETIME
#[Entity]
class Event {
    #[Property]
    public \DateTime $startTime;      // DATETIME column

    #[Property]
    public \DateTimeImmutable $endTime; // DATETIME column
}

// Custom class mappings with priority (lower number = higher priority)
$registry->registerClassMapping(MyInterface::class, 'JSON', null, 5);
$registry->registerClassMapping(MyClass::class, 'TEXT', null, 10); // Lower priority
```

**Priority System:** When a class implements multiple interfaces or extends multiple classes with registered mappings, the mapping with the lowest priority number is used.

### Custom Type Converters

Implement `TypeConverterInterface` for complex type conversions:

```php
class MoneyConverter implements TypeConverterInterface {
    public function convertToDatabase(mixed $value): mixed {
        return $value?->amount . ' ' . $value?->currency;
    }

    public function convertToPHP(mixed $value): mixed {
        // Parse and return Money object
    }
}
```

## Repository Pattern

Articulate provides a flexible repository pattern that allows you to organize entity-specific queries and operations in dedicated classes.

### Basic Usage

Get a repository for any entity using the EntityManager:

```php
$userRepository = $em->getRepository(User::class);

// Basic CRUD operations
$user = $userRepository->find(1);
$allUsers = $userRepository->findAll();
$activeUsers = $userRepository->findBy(['status' => 'active']);
$user = $userRepository->findOneBy(['email' => 'user@example.com']);
$userCount = $userRepository->count(['status' => 'active']);
$exists = $userRepository->exists(1);
```

### Custom Repository Classes

Create custom repository classes for entity-specific operations:

```php
#[Entity(repositoryClass: UserRepository::class)]
class User
{
    #[PrimaryKey]
    public int $id;

    #[Property]
    public string $email;

    #[Property]
    public string $status;
}

class UserRepository extends AbstractRepository
{
    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function findActiveUsers(): array
    {
        return $this->createQueryBuilder()
            ->where('status = ?', 'active')
            ->orderBy('created_at', 'DESC')
            ->getResult();
    }

    public function findUsersByStatusPaginated(string $status, int $page = 1, int $limit = 20): array
    {
        return $this->findBy(
            ['status' => $status],
            ['created_at' => 'DESC'],
            $limit,
            ($page - 1) * $limit
        );
    }
}

// Usage
$userRepo = $em->getRepository(User::class); // Returns UserRepository
$user = $userRepo->findByEmail('user@example.com');
$activeUsers = $userRepo->findActiveUsers();
```

### Repository Interface

All repositories implement `RepositoryInterface`:

```php
interface RepositoryInterface
{
    public function find(mixed $id): ?object;
    public function findAll(): array;
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;
    public function findOneBy(array $criteria, ?array $orderBy = null): ?object;
    public function count(array $criteria = []): int;
    public function exists(mixed $id): bool;
}
```

### Default Repository

Entities without a custom repository class automatically get an `EntityRepository` instance that provides all standard operations.

```php
#[Entity] // No repositoryClass specified
class Product { ... }

// Returns EntityRepository<Product>
$productRepo = $em->getRepository(Product::class);
```

## What's Implemented

Articulate provides a comprehensive ORM foundation with production-ready core features:

### ✅ Core Architecture
- **Entity System**: Full attribute-based entity definition with comprehensive relation support
- **Migration System**: Complete schema management with commands, generators, and execution strategies for MySQL and PostgreSQL
- **Type Mapping System**: Flexible type conversion with custom converters and priority-based resolution
- **EntityManager**: Robust implementation with Unit of Work pattern, change tracking, and identity maps
- **Schema Reader**: Full support for reading existing database schemas from MySQL and PostgreSQL

### ✅ Supported Databases

Articulate currently supports:

- **PostgreSQL** - Full support with all features
- **MySQL / MariaDB** - Full support with all features

**SQLite is not supported** at the moment due to its limited ALTER TABLE capabilities. Support may be added in the future as a separate package.

### ✅ Relations & Associations
- **Standard Relations**: OneToOne, OneToMany, ManyToOne, ManyToMany with full lifecycle support
- **Polymorphic Relations**: MorphTo, MorphOne, MorphMany with open-ended design (no hardcoded entity lists)
- **Advanced Many-to-Many**: Custom junction tables with additional mapping properties
- **Foreign Key Management**: Automatic FK creation with constraint validation

### ✅ Entity Management
- **Unit of Work Pattern**: Memory-efficient change tracking with multiple strategies (Deferred Implicit/Explicit)
- **Hydration System**: Multiple hydrators (Object, Array, Scalar, Partial) for different use cases
- **Proxy System**: Lazy loading infrastructure with runtime proxy generation
- **Lifecycle Callbacks**: Pre/Post persist, update, remove, and load hooks
- **Context-Bounded Entities**: Multiple entity classes sharing the same table with different fields (full schema merging & conflict detection implemented)
- **Identity Map**: Efficient entity caching and reference consistency
- **Change Tracking**: Metadata-driven property extraction and comparison

### ✅ Query Builder
- **SQL Generation**: SELECT, FROM, WHERE, JOIN (INNER, LEFT, RIGHT, CROSS), ORDER BY, GROUP BY, HAVING, LIMIT/OFFSET
- **Advanced Features**: Subqueries (`whereExists`, `selectSub`), aggregations (COUNT, SUM, AVG, MIN, MAX), criteria API, cursor pagination
- **DML**: INSERT, UPDATE, DELETE with `returning()` for PostgreSQL
- **Entity Integration**: Automatic table name resolution and result hydration
- **Query Result Cache**: PSR-6 integration via `enableResultCache()`
- **Locking**: Pessimistic lock via `lock()` (requires transaction)

### ✅ Repository Pattern
- **RepositoryInterface**: Standardized contract for entity-specific operations
- **AbstractRepository**: Base implementation with common CRUD operations (find, findAll, findBy, findOneBy, count, exists)
- **EntityRepository**: Generic repository for entities without custom logic
- **Custom Repositories**: Extend AbstractRepository for entity-specific query methods
- **Entity Attribute Integration**: Specify custom repository class via `#[Entity(repositoryClass: CustomRepo::class)]`
- **QueryBuilder Integration**: Repositories use QueryBuilder for complex queries

### ✅ Development & Testing
- **Comprehensive Test Suite**: 2000+ mutations with 83%+ kill rate
- **Multi-Database Testing**: Automated testing across all supported databases
- **Code Quality**: PHP-CS-Fixer, PHPStan static analysis, Deptrac architecture validation
- **CI/CD Pipeline**: GitHub Actions with automated QA checks
- **Mutation Testing**: Infection framework with high mutation kill rate

## Testing Strategy

Articulate employs a rigorous testing approach focused on quality and reliability:

### Multi-Database Testing
- **Parallel Test Execution**: Tests run simultaneously across MySQL and PostgreSQL
- **Database-Specific Logic**: Conditional SQL handling for database differences
- **Isolation**: Each test uses unique table names and transaction rollbacks
- **Real Database Connections**: Functional tests over mocks for accuracy

### Mutation Testing
- **Infection Framework**: 2000+ mutations with 83%+ kill rate
- **Code Quality Assurance**: Ensures test coverage catches actual bugs
- **Continuous Improvement**: High mutation scores indicate robust test suites

### Test Categories
- **Unit Tests**: Core logic and algorithms
- **Integration Tests**: Full workflow testing with real databases
- **Functional Tests**: End-to-end entity operations
- **Migration Tests**: Schema changes and database compatibility

### Quality Assurance Pipeline
```bash
composer qa  # Runs: CS check → Architecture check → Tests → Mutation testing
```

## Current Gaps & Known Issues

### Implemented (previously listed as gaps)
- **Repository Pattern**: Full implementation with `RepositoryInterface`, `AbstractRepository`, custom repositories via `#[Entity(repositoryClass: ...)]`
- **Criteria API**: Object-oriented query building with `CriteriaInterface`, `AndCriteria`, `OrCriteria`, `InCriteria`, `LikeCriteria`, etc.
- **Query Result Cache**: `QueryResultCache` with PSR-6 integration, `enableResultCache()` on QueryBuilder
- **Lock Modes**: Pessimistic locking via `lock()` on QueryBuilder (requires active transaction)
- **ID Generation Strategies**: Auto-increment, UUID, UUIDv7, ULID, serial (PostgreSQL), prefixed IDs, custom generators

### Remaining Gaps
- **Context-Bounded Entities Runtime**: Schema merging and conflict detection work; runtime behavior could use more integration tests
- **Second-Level Entity Cache**: Only query result cache exists; no entity-level cache
- **Event System**: Lifecycle callbacks (PrePersist, PostLoad, etc.) exist; broader event architecture would be beneficial
- **Performance Optimizations**: Statement caching, connection pooling

## Development Environment

### Prerequisites

- Docker & Docker Compose (required for all development tasks)
- PHP scripts must be executed inside Docker containers

### Docker Environment Setup

All development work must be done inside Docker containers:

```bash
# Start all services (MySQL, PostgreSQL, PHP)
docker compose up -d

# Access PHP container for development
docker compose exec php bash
```

### Environment Configuration

1. **Local `.env` file** (optional, for custom database connections):
   ```env
   DATABASE_HOST=127.0.0.1
   DATABASE_HOST_PGSQL=127.0.0.1
   DATABASE_USER=articulate_user
   DATABASE_PASSWORD=articulate_pass
   DATABASE_NAME=articulate_test
   ```

2. **Docker Compose Override** (for custom configurations):
   Modify `docker-compose.override.yml` for local environment adjustments

### Development Workflow

All commands should be run inside the PHP container:

```bash
# Access PHP container
docker compose exec php bash

# Install/update dependencies
composer install

# Run complete QA pipeline
composer qa

# Run tests only
composer test

# Run tests with coverage report
composer test:coverage

# Run mutation tests (time-intensive)
composer test:mutation

# Fix code style issues
composer cs:fix

# Check architecture dependencies
composer architecture:check

# Run complexity analysis
composer complexity:check
```

### Database Testing

The project includes automated multi-database testing:

```bash
# Run all database tests
docker compose exec php composer test -- --testsuite="Database Tests"

# Run MySQL-specific tests
docker compose exec php composer test -- --group=mysql

# Run PostgreSQL-specific tests
docker compose exec php composer test -- --group=pgsql
```

### Available Services

- `php`: PHP 8.4 application container
- `mysql`: MySQL 8.0 database
- `pgsql`: PostgreSQL 15 database

## Roadmap

### Production Readiness
- **Documentation**: Keep README and docs aligned with implementation
- **Filter System**: Pluggable query filters (see `docs/filter-system.md`) to replace hard-coded soft-delete logic

### Future Enhancements
- **Second-Level Entity Cache**: Entity-level caching alongside existing query result cache
- **Event System**: Broader event architecture beyond lifecycle callbacks
- **Performance Optimizations**: Statement caching, connection pooling

## License

Licensed under the Apache License 2.0. See `LICENSE`.

