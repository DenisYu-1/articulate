# Articulate

A business-oriented PHP ORM library that provides context-aware entity management and memory-efficient operations.

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

## Current gaps / known issues

- Schema reader is MySQL-only: it swallows errors, returns empty columns on PostgreSQL/SQLite, and treats lengthed types (e.g., `int(11)`) as `string`.
- Query builder classes are still missing.
### Documentation

- One-to-one relations: `src/Attributes/Relations/README.md`

## CI/CD

This project uses GitHub Actions for continuous integration. The QA workflow runs on every push and pull request to main/master/develop branches.

### QA Checks

The `composer qa` command runs the following checks:
- **Code style**: `php-cs-fixer fix --dry-run --diff --allow-risky=yes`
- **Unit tests**: `phpunit` (requires MySQL and PostgreSQL)
- **Mutation testing**: `infection --threads=max`

### GitHub Actions Setup

To set up CI/CD, configure the following in your GitHub repository settings under **Secrets and variables**:

- `DATABASE_USER`: Database username (e.g., `root` for MySQL, `postgres` for PostgreSQL)
- `DATABASE_PASSWORD`: Database password (e.g., `rootpassword`)
- `DATABASE_NAME`: Database name for test databases (e.g., `articulate_test`)

The workflow will automatically:
- Validate that all required secrets are configured
- Set up PHP 8.4 with required extensions (PDO, MySQL, PostgreSQL, SQLite)
- Start MySQL 8.0 and PostgreSQL 15 services with proper authentication
- Create a `.env` file with the required environment variables
- Run the QA checks
