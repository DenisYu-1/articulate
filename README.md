# Articulate

A business-oriented PHP ORM library.

## Current gaps / known issues

- Schema reader is MySQL-only: it swallows errors, returns empty columns on PostgreSQL/SQLite, and treats lengthed types (e.g., `int(11)`) as `string`.
- Query builder classes are still missing.
### Documentation

- One-to-one relations: `src/Attributes/Relations/README.md`
