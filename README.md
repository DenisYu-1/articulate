# Articulate

A business-oriented PHP ORM library.

## Current gaps / known issues

- Rollback picks the last migration file found, not the latest executed from the `migrations` table; rollback target is filesystem-order dependent.
- Schema reader is MySQL-only: it swallows errors, returns empty columns on PostgreSQL/SQLite, and treats lengthed types (e.g., `int(11)`) as `string`.
- Column diffs ignore default and length changes, so many real schema drifts are missed.
- Primary keys are derived from property names instead of column names, so custom PK column names break PK/FK generation.
- Migration ALTER ordering drops columns before foreign keys and leaves identifiers unquoted, which can fail with existing FKs or reserved names.
- Query builder classes are still missing.
### Documentation

- One-to-one relations: `src/Attributes/Relations/README.md`
