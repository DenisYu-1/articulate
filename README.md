# Articulate

<p align="center">
  <img src="logo.svg" alt="Articulate Logo" width="80" height="80">
</p>

A context-driven PHP ORM library that enables domain-aware entity management and memory-efficient operations through bounded contexts.
Work in progress.

## Badges

[![CI](https://github.com/DenisYu-1/articulate/workflows/QA/badge.svg)](https://github.com/DenisYu-1/articulate/actions)
[![Mutation testing](https://img.shields.io/badge/Mutation%20Score-82.01%25-brightgreen)](https://github.com/DenisYu-1/articulate/actions)
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

## Current gaps / known issues

- Schema reader is MySQL-only: it swallows errors, returns empty columns on PostgreSQL/SQLite, and treats lengthed types (e.g., `int(11)`) as `string`.
- Query builder classes are still missing.
- Polymorphic relations: `src/Attributes/Relations/README.md`
### Documentation

- One-to-one relations: `src/Attributes/Relations/README.md`

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

## CI/CD

This project uses GitHub Actions for continuous integration. The QA workflow runs on every push and pull request to main/master/develop branches.

### QA Checks

The `composer qa` command runs the following checks:
- **Code style**: `php-cs-fixer fix --dry-run --diff --allow-risky=yes`
- **Unit tests**: `phpunit` (requires MySQL and PostgreSQL)
- **Mutation testing**: `infection --threads=max`
