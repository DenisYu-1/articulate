# Articulate

## The Problem

Traditional ORMs force one entity class per table. Auth needs only `login` and `password`, but it loads `phones`, `groups`, `cart`, and every other relation. The admin panel needs different fields than the API. Adding one relation to a `User` entity affects every consumer of that class.

The entity manager accumulates objects in memory for the entire request or process. Long-running jobs, batch imports, or complex flows have no way to release entities that are no longer needed without detaching everything.

<p align="center">
  <img src="logo.svg" alt="Articulate Logo" width="80" height="80">
</p>

Articulate addresses these pains with context-bounded entities and scoped unit-of-work management.

## Badges

[![CI](https://github.com/DenisYu-1/articulate/workflows/QA/badge.svg)](https://github.com/DenisYu-1/articulate/actions)
[![Mutation testing](https://img.shields.io/badge/Mutation%20Score-83%25+-brightgreen)](https://github.com/DenisYu-1/articulate/actions)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.4-8892BF.svg)](https://php.net/)

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

**Before** — one fat entity, every context gets everything:

```php
#[Entity]
class User
{
    public int $id;
    public string $login;
    public string $password;
    public string $name;
    public array $phones;   // Auth doesn't need this
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

## How Articulate Compares?

| | Doctrine | Cycle ORM | Articulate |
|---|---|---|---|
| Multiple entity classes per table | No built-in support | No, one entity per table | Yes, first-class context-bounded entities |
| Memory control | Identity map held for process lifetime; clear-all or nothing | Similar model | Scoped unit-of-work; release entities mid-request |
| Config style | XML/YAML common, attributes optional | Annotations/attributes | Attributes only (PHP 8.4+) |

Articulate is aimed at projects where different bounded contexts need different views of the same data and where memory pressure matters in long-running or batch processes.

## Core Concepts

### Context-Bounded Entities

Multiple entity classes can point to the same database table, each exposing only the fields and relationships needed for that context. Articulate merges compatible column definitions and validates for conflicts.

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

## License

Licensed under the Apache License 2.0. See `LICENSE`.
