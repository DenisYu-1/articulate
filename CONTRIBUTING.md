# Contributing

## Prerequisites

- Docker & Docker Compose
- All commands run inside the PHP container

## Setup

```bash
docker compose up -d
docker compose exec php bash

composer install
```

## Development Workflow

```bash
composer qa
composer test
composer test:coverage
composer test:mutation
composer cs:fix
composer architecture:check
```

## Database Testing

```bash
docker compose exec php composer test -- --testsuite="Database Tests"
docker compose exec php composer test -- --group=mysql
docker compose exec php composer test -- --group=pgsql
```

## Services

- `php`: PHP 8.4
- `mysql`: MySQL 8.0
- `pgsql`: PostgreSQL 15
