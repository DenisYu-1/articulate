# Articulate

Context-bounded ORM for modular PHP applications that share database tables across modules.

## Why Articulate?

Most ORMs make the table/entity boundary the modeling boundary: one table, one primary entity class. In modular systems, that turns shared tables into shared domain objects.

A `users` table may be touched by authentication, administration, billing, public APIs, reporting, and background workers. Those contexts do not need the same fields, relations, invariants, or lifecycle behavior. A single shared `User` entity gradually becomes a coupling point between modules.

Articulate makes the bounded context the modeling boundary. Several small entity classes can map to the same physical table: `LoginUser` for authentication, `AdminUser` for administration, `BillingCustomer` for billing, and read-only projection entities for public APIs.

<p align="center">
  <img src="logo.svg" alt="Articulate Logo" width="80" height="80">
</p>

Articulate still provides the expected ORM foundations: attributes, repositories, relations, migrations, type mapping, identity map, unit of work, lazy loading, and caching. The difference is that these pieces are designed around context-bounded entities from the start.

## Badges

[![CI](https://github.com/DenisYu-1/articulate/workflows/QA/badge.svg)](https://github.com/DenisYu-1/articulate/actions)
[![Mutation testing](https://img.shields.io/badge/Mutation%20Score-83%25+-brightgreen)](https://github.com/DenisYu-1/articulate/actions)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.4-8892BF.svg)](https://php.net/)

## What Makes It Different?

- Multiple entity classes can map to one physical table.
- Partial entities can be marked read-only when they intentionally omit required columns.
- Each `EntityManager` owns its identity map and units of work.
- Shared-table sibling entities are handled deliberately during writes and cache eviction.
- Schema metadata, migrations, relations, lazy loading, repositories, and type conversion all understand context-bounded entities.
- MySQL and PostgreSQL are first-class targets.

## Quick Start

```php
use Articulate\Connection;
use Articulate\Modules\EntityManager\EntityManager;

#[Entity]
class User
{
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public string $name;

    #[Property]
    public string $email;
}

$connection = new Connection('mysql:host=127.0.0.1;dbname=myapp', 'user', 'password');
$em = new EntityManager($connection);

$user = new User();
$user->name = 'Jane';
$user->email = 'jane@example.com';
$em->persist($user);
$em->flush();

$user = $em->getRepository(User::class)->find($user->id);
```

## Before / After

**Before** — one shared entity shape for every context:

```php
#[Entity]
class User
{
    public int $id;
    public string $login;
    public string $password;
    public string $name;
    public array $phones;  // Auth doesn't need this
    public array $groups;  // Auth doesn't need this
    public Cart $cart;     // Auth doesn't need this
}

// Auth: loads full user + all relations
$user = $userRepo->find($id);
return $auth->validate($user->login, $user->password);
```

**After** — separate entities per context, same table:

```php
#[Entity(tableName: 'user')]
class LoginUser
{
    #[PrimaryKey]
    public int $id;

    #[Property]
    public string $login;

    #[Property]
    public string $password;
}

#[Entity]
class User
{
    #[PrimaryKey]
    public int $id;

    #[Property]
    public string $name;

    #[OneToMany(ownedBy: 'user', targetEntity: Phone::class)]
    public array $phones;

    #[OneToOne(targetEntity: Cart::class, referencedBy: 'user')]
    public Cart $cart;
}

// Auth: loads only id, login, password
$loginUser = $em->getRepository(LoginUser::class)->find($id);
return $auth->validate($loginUser->login, $loginUser->password);
```

## When It Fits

Articulate is a good fit when different bounded contexts need different views of the same data, when adding a relation for one workflow should not affect every other workflow, or when long-running processes need tighter control over tracked entities.

If your application has one stable entity model per table and your current ORM handles that well, Articulate would still fit, just probably will not solve a meaningful problem for you.

## Core Concepts

### Context-Bounded Entities

Multiple entity classes can point to the same database table, each exposing only the fields and relationships needed for that context. Articulate merges compatible column definitions and validates for conflicts.

### Read-Only Entities

Mark a context-bounded entity as read-only when it intentionally omits required columns — for example, a `LoginUser` that exposes only `login` and `password` from a `users` table that has many more non-nullable columns.

```php
#[Entity(tableName: 'user', readOnly: true)]
class LoginUser
{
    #[PrimaryKey]
    public int $id;

    #[Property]
    public string $login;

    #[Property]
    public string $password;
}

// find() and QueryBuilder work normally:
$loginUser = $em->getRepository(LoginUser::class)->find($id);
$auth->validate($loginUser->login, $loginUser->password);

// persist() and remove() throw ReadOnlyEntityException:
$em->persist($loginUser); // throws
```

`ReadOnlyEntityException` is thrown at `persist()` and `remove()` — before any SQL is built.

### Memory-Efficient Unit of Work

- Clear entities from memory that are no longer needed within specific operations
- Different units of work can track their own entities independently
- Entity manager combines all unit-of-work changes into minimal database queries during flush

Useful for processing large datasets, complex business operations spanning multiple contexts, and long-running processes with varying entity lifecycles.

## Type Mapping System

Built-in mappings: `bool` ↔ `TINYINT(1)`, `int` ↔ `INT`, `float` ↔ `FLOAT`, `string` ↔ `VARCHAR(255)`, `DateTimeInterface` ↔ `DATETIME`.

Custom class mappings and `TypeConverterInterface` for complex types. Priority-based resolution when a class implements multiple interfaces with registered mappings.

## Repository Pattern

```php
$userRepo = $em->getRepository(User::class);
$user = $userRepo->find(1);
$users = $userRepo->findBy(['status' => 'active']);
$user = $userRepo->findOneBy(['email' => 'user@example.com']);
```

Custom repositories via `#[Entity(repositoryClass: UserRepository::class)]` extending `AbstractRepository`.

## Caching

Articulate has three independent cache layers, all using PSR-6 (`CacheItemPoolInterface`). Pass the same pool instance to share backend, or separate instances for isolation.

### Second-Level Cache

Cross-request entity cache. Survives beyond a single `EntityManager` instance.

```
Request A: identity map miss → DB hit → entity stored in L2 cache
Request B: identity map miss → L2 cache hit → DB skipped entirely
Request C: identity map miss → L2 cache hit → DB skipped entirely
```

Pass any PSR-6 pool to `EntityManager`. If no dedicated pool is given, it falls back to the result cache pool automatically.

```php
$em = new EntityManager(
    $connection,
    resultCache: $cachePool,           // also backs L2 cache unless overridden
    secondLevelCacheTtl: 3600,
);

// Or with a dedicated L2 pool:
$em = new EntityManager(
    $connection,
    resultCache: $queryPool,
    secondLevelCache: $entityPool,     // separate backend for entity cache
    secondLevelCacheTtl: 3600,
);
```

`find()` checks the identity map first, then the L2 cache, then the database. On `flush()`, modified and deleted entity entries are evicted automatically — stale data is never served after a write.

### Query Result Cache

Cache raw result sets from `QueryBuilder` queries. Useful for read-heavy queries that don't change often.

```php
$users = $em->createQueryBuilder(User::class)
    ->from('users')
    ->where('status', 'active')
    ->enableResultCache(lifetime: 300, resultCacheId: 'active_users')
    ->getResult();
```

- Custom cache key via `resultCacheId`, or auto-generated from query shape + parameters
- Locked queries (`FOR UPDATE`) are never cached
- Call `disableResultCache()` to opt out per query

### Statement Cache

Caches compiled SQL strings (query structure, not results). Eliminates repeated SQL compilation for queries with the same shape but different parameter values.

```php
$em = new EntityManager($connection, statementCache: $cachePool);
```

Transparent — no per-query opt-in needed. Failures are silently ignored so a broken cache backend never breaks queries.

## Connection Pooling

Enable PDO persistent connections to reuse open database connections across requests:

```php
$connection = new Connection(
    dsn: 'mysql:host=127.0.0.1;dbname=myapp',
    user: 'root',
    password: 'secret',
    persistent: true,
);
```

Skips TCP handshake and authentication overhead on each request. Pair with a pool-aware cache backend for full cross-request performance.

## MySQL Table Options (ENGINE, CHARSET, COLLATE)

Articulate does not append table options like `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=...` to generated `CREATE TABLE` statements. This is intentional.

Storage engine and character set are deployment concerns, not schema concerns. The right values depend on the MySQL version, the hosting environment, and the application's locale requirements — there is no single correct default. Hardcoding them would mean either inheriting outdated assumptions or overriding a deliberate server configuration.

Instead, Articulate delegates to the server's configured defaults:

- **ENGINE** — InnoDB is the MySQL default since 5.7 and is the only engine that supports foreign keys; Articulate's FK generation already implies it.
- **CHARSET / COLLATE** — configure once at the server or database level (`CREATE DATABASE ... CHARACTER SET utf8mb4`). All tables created in that database inherit the correct charset without per-table repetition.

If per-table overrides are ever needed, the right path is an explicit option on `#[Entity]`, not a framework-wide hardcoded string.

## Index Attribute Design

`#[Index]` takes `fields` — PHP property names, not column names:

```php
#[Index(fields: ['userId', 'createdAt'])]
#[Entity]
class Order { ... }
```

This keeps index definitions coupled to the entity model. When a property is renamed alongside its column, PHP tooling catches the broken reference in `fields`. Raw column strings would silently diverge.

**Expression and prefix indexes** (e.g. `LOWER(email)`, `title(100)`) have no PHP property to reference. If that need arises, a dedicated `ExpressionIndex` attribute will be introduced as an explicit escape hatch rather than mixing column-string support into `Index`.

## License

Licensed under the Apache License 2.0. See [LICENSE](./LICENSE).
