# Articulate

<p align="center">
  <img src="logo.svg" alt="Articulate Logo" width="80" height="80">
</p>

A context-driven PHP ORM library that enables domain-aware entity management and memory-efficient operations through bounded contexts.
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
    #[Property]
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
    #[Property]
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

## What's Implemented

Articulate provides a solid foundation for PHP ORM development with the following core features:

### ✅ Core Architecture
- **Entity System**: Full attribute-based entity definition with comprehensive relation support
- **Migration System**: Complete schema management with commands, generators, and execution strategies for MySQL, PostgreSQL, and SQLite
- **Type Mapping System**: Flexible type conversion with custom converters and priority-based resolution
- **EntityManager**: Robust implementation with Unit of Work pattern, change tracking, and identity maps
- **Multi-Database Support**: Comprehensive testing across MySQL, PostgreSQL, and SQLite databases

### ✅ Relations & Associations
- **Standard Relations**: OneToOne, OneToMany, ManyToOne, ManyToMany with full lifecycle support
- **Polymorphic Relations**: MorphTo, MorphOne, MorphMany with open-ended design (no hardcoded entity lists)
- **Advanced Many-to-Many**: Custom junction tables with additional mapping properties
- **Foreign Key Management**: Automatic FK creation with constraint validation

### ✅ Entity Management
- **Unit of Work Pattern**: Memory-efficient change tracking with multiple strategies
- **Hydration System**: Multiple hydrators (Object, Array, Scalar, Partial) for different use cases
- **Proxy System**: Lazy loading infrastructure for performance optimization
- **Lifecycle Callbacks**: Pre/Post persist, update, remove, and load hooks
- **Context-Bounded Entities**: Multiple entity classes sharing the same table with different fields

### ✅ Development & Testing
- **Comprehensive Test Suite**: 2000+ mutations with 83%+ kill rate
- **Multi-Database Testing**: Automated testing across all supported databases
- **Code Quality**: PHP-CS-Fixer, PHPStan static analysis, Deptrac architecture validation
- **CI/CD Pipeline**: GitHub Actions with automated QA checks

## Current Gaps & Known Issues

### 🚧 High Priority
- **Schema Reader**: Currently MySQL-only; returns empty results for PostgreSQL/SQLite and incorrectly handles typed columns (e.g., `int(11)` → `string`)
- **QueryBuilder**: Basic SQL generation exists but missing advanced features (subqueries, aggregations, complex WHERE conditions)
- **Change Tracking**: Uses basic reflection instead of metadata-driven property extraction

### 📋 Medium Priority
- **Repository Pattern**: No abstraction layer for entity-specific queries and operations
- **Caching**: Missing query result cache and second-level entity cache
- **Advanced Query Features**: Criteria API, native SQL queries, bulk operations
- **Lock Modes**: No pessimistic or optimistic locking support

### 🔄 Low Priority
- **Event System**: Limited to lifecycle callbacks; could benefit from broader event architecture
- **ID Generation**: Basic auto-increment; missing UUID, sequences, custom generators

### 📚 Documentation
- **Relations Guide**: `src/Attributes/Relations/README.md` (comprehensive coverage of all relation types)

## Local Development & Testing

### Prerequisites

- Docker & Docker Compose (for database testing)

### Database Setup

For running tests locally, start the required databases:

```bash
docker compose up -d
```

### Environment Configuration

1. **Creating a `.env` file**:
   ```env
   # For local development (outside Docker)
   DATABASE_HOST=127.0.0.1
   DATABASE_HOST_PGSQL=127.0.0.1
   DATABASE_USER=articulate_user
   DATABASE_PASSWORD=articulate_pass
   DATABASE_NAME=articulate_test
   ```

2. **Modifying `docker-compose.override.yml`** for custom local configuration

### Running Tests

#### Local Development (without Docker)
```bash
# Install dependencies
composer install

# Run all QA checks (code style + tests + mutation testing)
composer qa

# Run only unit tests
composer test

# Run tests with coverage
composer test:coverage

# Run mutation tests
composer test:mutation

# Fix code style issues
composer cs:fix
```

## Future Improvements

This section outlines planned enhancements and features for future versions of Articulate.

### ID Generation Strategies

**Current State**: Basic auto-increment ID generation for entities without existing IDs.

**Planned Improvements**:
- **Database-Specific Strategies**:
  - MySQL/SQLite auto-increment (`AUTO_INCREMENT`)
  - PostgreSQL sequences (`SERIAL`, `NEXTVAL`)
  - SQL Server identity columns
- **UUID/GUID Generation**: Built-in UUID v4/v7 generation with attribute configuration
- **Custom ID Generators**: Pluggable strategies for application-specific ID generation
- **Natural Key Support**: Using business-meaningful fields as primary keys (non-auto-generated)
- **Sequence Support**: Named sequences for enterprise databases

**Note**: Composite primary keys (multi-column) are intentionally out of scope for Articulate's initial versions, as they add significant complexity and are not commonly needed in modern application development. Surrogate keys (single-column auto-generated IDs) are recommended for most use cases.

**Example Usage**:
```php
#[Entity]
#[Id(strategy: 'uuid')]
class Product {
    #[Id]
    public string $id; // Auto-generated UUID

    public string $name;
}

#[Entity]
#[Id(strategy: 'sequence', sequence: 'user_seq')]
class User {
    #[Id]
    public int $id; // From PostgreSQL sequence

    public string $email;
}

#[Entity]
#[Id(strategy: 'auto')] // Default auto-increment
class Order {
    #[Id]
    public int $id; // MySQL AUTO_INCREMENT or PostgreSQL SERIAL

    public string $orderNumber; // Natural key, not auto-generated
}
```

## CI/CD

This project uses GitHub Actions for continuous integration. The QA workflow runs on every push and pull request to main/master/develop branches.

### QA Checks

The `composer qa` command runs the following checks:
- **Code style**: `php-cs-fixer fix --dry-run --diff --allow-risky=yes`
- **Unit tests**: `phpunit` (requires MySQL and PostgreSQL)
- **Mutation testing**: `infection --threads=max`
