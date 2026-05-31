# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

All commands run inside the PHP container (`docker compose exec php bash`):

```bash
# Setup
docker compose up -d
docker compose exec php bash
composer install

# Testing
composer test                                          # All tests
composer test:coverage                                 # HTML coverage → build/coverage/
composer test:mutation                                 # Infection mutation testing
composer test -- --filter=TestClassName                # Single test class
composer test -- --filter=TestClassName::testMethod    # Single test method
composer test -- --testsuite="Database Tests"          # Multi-DB tests only
composer test -- --group=mysql                         # MySQL tests only
composer test -- --group=pgsql                         # PostgreSQL tests only

# Code quality
composer cs:check          # Dry-run php-cs-fixer
composer cs:fix            # Apply CS fixes
composer static:check      # PHPStan level 6
composer architecture:check  # Deptrac layer validation
composer complexity:check   # PHPMD cyclomatic/NPath analysis

# Full QA pipeline
composer qa                # CS → architecture → tests → mutation tests
```

## Project Overview

Articulate is an ORM for PHP domain-driven applications where multiple entity classes map to the same database table (context-bounded entities). Primary users are backend developers building bounded-context architectures. The core optimization is keeping entity state tracking per-context rather than process-wide — each `EntityManager` instance owns its own `IdentityMap`. When in doubt: prefer explicitness over magic, bounded scope over global state.

## Tech Stack

- **Language**: PHP 8.4+ (use readonly properties, enums, fibers where appropriate)
- **Databases**: MySQL 8.0, PostgreSQL 15 — both must work
- **Mapping**: PHP attributes only — no XML, no YAML, no annotations
- **Testing**: PHPUnit with real DB connections — no mocks
- **QA**: php-cs-fixer, PHPStan level 6, Deptrac, Infection mutation testing
- **Do NOT use**: Doctrine annotations, global singletons, static state, process-wide registries

## Architecture

Articulate is a context-bounded ORM for domain-driven PHP applications. PHP 8.4+, attribute-based configuration, MySQL 8.0 + PostgreSQL 15.

### Core Concepts

**Context-bounded entities**: Multiple entity classes can map to the same database table (e.g., `LoginUser` and `ProfileUser` both map to `users`). This is the primary differentiator from standard ORMs.

**Unit of Work**: `EntityManager` tracks entity state (NEW → MANAGED → REMOVED) via `UnitOfWork`. Changes collected by `ChangeAggregator`, flushed as a batch. Each context (EntityManager instance) has its own `IdentityMap` — no process-wide singletons.

**Attribute-driven metadata**: No XML/YAML. All mapping via PHP attributes: `#[Entity]`, `#[Property]`, `#[PrimaryKey]`, `#[Index]`, `#[OneToMany]`, `#[ManyToMany]`, etc.

### Module Map

| Module | Path | Purpose |
|--------|------|---------|
| EntityManager | `src/Modules/EntityManager/` | UnitOfWork, IdentityMap, hydrators, lifecycle callbacks, lazy-loading proxies |
| QueryBuilder | `src/Modules/QueryBuilder/` | Fluent SQL builder, WHERE clauses, keyset pagination, soft-delete filter |
| Database | `src/Modules/Database/` | Schema reader, type mappers per DB, schema comparator (diff engine) |
| Repository | `src/Modules/Repository/` | AbstractRepository, EntityRepository, criteria pattern |
| Migrations | `src/Modules/Migrations/` | Schema diff → migration SQL (MySQL & PostgreSQL generators) |
| Generators | `src/Modules/Generators/` | ID strategies: UUID v4/v7, ULID, AutoIncrement, Serial, Prefixed |
| Attributes | `src/Attributes/` | All PHP attributes + reflection wrappers (ReflectionEntity, ReflectionProperty, ReflectionRelation) |
| Schema | `src/Schema/` | EntityMetadata, EntityMetadataRegistry, naming conventions |
| Utils | `src/Utils/` | TypeRegistry, type converters (bool↔TINYINT, DateTime↔DATETIME, etc.) |
| Commands | `src/Commands/` | Symfony Console: DiffCommand, InitCommand, MigrateCommand |

### Architectural Boundaries

Deptrac enforces 12 layers. The dependency direction is:
`Commands → Migrations → Database → QueryBuilder → Repository → EntityManager → Schema → Attributes → Utils → Collection → Generators → Exceptions`

Run `composer architecture:check` after adding cross-module dependencies.

### Where New Things Go

| Adding... | Goes in... |
|-----------|-----------|
| New entity mapping attribute | `src/Attributes/` |
| New DB type converter | `src/Utils/` |
| New hydration strategy | `src/Modules/EntityManager/Hydrators/` |
| New query feature | `src/Modules/QueryBuilder/` |
| New schema diff concern | `src/Modules/Database/SchemaComparator/` |
| New ID generation strategy | `src/Modules/Generators/` |
| New CLI command | `src/Commands/` |
| New test for EntityManager | `tests/Modules/EntityManager/` |

Always run `composer architecture:check` after adding cross-module dependencies.

### Schema Comparator (Diff Engine)

`DatabaseSchemaComparator` in `src/Modules/Database/SchemaComparator/` compares live DB schema against entity attributes. Uses per-concern comparators:
- `ColumnComparator` — column type/nullability/default diffs
- `EntityTableComparator` — table-level diffs
- `ForeignKeyComparator`, `IndexComparator`, `MappingTableComparator`

Output feeds `MigrateCommand` and `DiffCommand`.

### Hydration

Four hydrators in `src/Modules/EntityManager/Hydrators/`:
- `ObjectHydrator` — full entity objects (default)
- `ArrayHydrator` — raw arrays
- `ScalarHydrator` — single scalar value
- `PartialHydrator` — subset of properties
- `LazyLoadingHydrator` — wraps ObjectHydrator with proxy injection for deferred relation loading

### Proxy System

`ProxyGenerator` generates PHP proxy classes at runtime for lazy loading. Proxies intercept property access and trigger `LazyLoadingHydrator`. Generated proxies cached in configured proxy directory.

## Coding Conventions

- **Naming**: Classes = PascalCase, methods/vars = camelCase, DB columns = snake_case via naming convention resolver
- **Types**: Full type hints everywhere — no `mixed` unless unavoidable, no `@param` when signature suffices
- **No comments** unless the WHY is non-obvious (hidden constraint, workaround, subtle invariant)
- **Interfaces before implementations**: depend on abstractions, not concrete classes
- **Exceptions**: use types from `src/Exceptions/` — never throw `\Exception` directly
- **File size**: keep classes focused; if a class exceeds ~300 lines, consider splitting by concern
- **No static state**: zero static properties/methods that accumulate state across requests
- **Dual-DB**: every SQL-touching feature must work on both MySQL and PostgreSQL — use `match($databaseName)` in tests

## Patterns

### Read Replicas

No built-in read/write routing — intentional. Let infrastructure handle it (PgBouncer, ProxySQL, RDS Proxy) or use the per-context design:

```php
// Write context → primary
$primary = new EntityManager($primaryConnection, ...);
$primary->persist($entity);
$primary->flush();

// Read context → replica
$replica = new EntityManager($replicaConnection, ...);
$users = $replica->findAll(User::class);
```

Each `EntityManager` owns its `IdentityMap` and `UnitOfWork` — calling `flush()` on a replica-backed instance is a caller error, not something the ORM prevents. Document this contract in consuming code.

### Second-Level Cache

`SecondLevelCache` (`src/Modules/EntityManager/`) caches raw entity rows keyed by `class + id` behind a PSR-6 pool. Pass `secondLevelCache:` (and optional `secondLevelCacheTtl:`) to `EntityManager`. `find()` reads through it; `flush()` evicts changed/deleted IDs. Cache faults never break query execution — every operation is wrapped and fails open.

Contract — read before relying on it:
- **Cross-context staleness is by design.** Eviction only happens in the `EntityManager` that performed the write. A write in context A leaves a stale row readable in context B until its TTL expires. Keep `secondLevelCacheTtl` short for entities shared across contexts, or evict explicitly. Same footgun class as the replica `flush()` rule above.
- **Caches the root row only, not relations.** A cache hit returns a shallow hydrate; relations still lazy-load on access (consistent with a cold `find()`).
- **IDs must be scalar, `Stringable`, or a composite array.** Keys are type-tagged so `1` (int) and `"1"` (string) never collide; non-`Stringable` objects throw (caught upstream → caching silently disabled for that ID).

### Enum Properties

Backed enums persist their backing value, pure enums their case name. `TypeRegistry` maps int-backed enums → `INT`, everything else → `VARCHAR(255)`, and lazily builds an `EnumTypeConverter` per enum class (nullable `?Enum` handled too). No registration needed — just type the property with the enum.

### Transaction Helpers

`Connection::transactional(callable, maxRetries, baseDelayMs)` runs work in a transaction and retries on deadlock / serialization failure (SQLSTATE 40001/40P01, MySQL 1213/1205) with exponential backoff; nested calls run in the caller's transaction without committing. Savepoints: `createSavepoint`/`releaseSavepoint`/`rollbackToSavepoint`. `flush()` does **not** auto-retry — wire `transactional()` at the call site if you need it.

## Safe-Change Rules

CLAUDE.md and README.md should be kept up-to-date with the codebase.

Do NOT casually modify:
- `UnitOfWork` state machine transitions (NEW → MANAGED → REMOVED) — breaks flush correctness
- `IdentityMap` keying logic — breaks entity identity guarantees
- Public API of `EntityManager` (method signatures, return types) — downstream breaking change
- Deptrac layer config (`deptrac.yaml`) — only change with architectural intent
- Attribute class names/constructors — breaks all existing user code using those attributes
- Schema comparator output format — feeds migration SQL generators

Flag these to the user before implementing.

## Testing

Tests use **real database connections, no mocks**. Each test runs in a transaction that auto-rolls back.

### Multi-database tests

Extend `DatabaseTestCase`, use `@dataProvider databaseProvider` for tests that run on both MySQL and PostgreSQL:

```php
/** @dataProvider databaseProvider */
public function testFeature(Connection $connection, string $databaseName): void
{
    $this->setCurrentDatabase($connection, $databaseName);
    $tableName = $this->getTableName('my_table', $databaseName);
    // database-specific SQL via match($databaseName) { 'mysql' => ..., 'pgsql' => ... }
}
```

### Database environment variables

```env
DATABASE_HOST=127.0.0.1        # or "mysql" inside Docker
DATABASE_HOST_PGSQL=127.0.0.1  # or "pgsql" inside Docker
DATABASE_USER=root
DATABASE_PASSWORD=rootpassword
DATABASE_NAME=articulate_test
```

See `tests/ExampleMultiDatabaseTest.php` for reference patterns.
