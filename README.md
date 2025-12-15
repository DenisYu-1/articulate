# Articulate

A business-oriented PHP ORM library.

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

To set up CI/CD, configure the following secrets in your GitHub repository:

- `DATABASE_USER`: Database username (e.g., `root` for MySQL, `postgres` for PostgreSQL)
- `DATABASE_PASSWORD`: Database password (e.g., `rootpassword`)
- `DATABASE_NAME`: Database name for test databases (e.g., `articulate_test`)

The workflow will automatically:
- Set up PHP 8.4 with required extensions (PDO, MySQL, PostgreSQL, SQLite)
- Start MySQL 8.0 and PostgreSQL 15 services
- Create a `.env` file with the required environment variables
- Run the QA checks
