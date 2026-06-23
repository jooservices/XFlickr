---
name: database-migration-safety
description: MySQL migration safety — test stack only for agents; rollback awareness.
---

# Skill: database-migration-safety

## Purpose

Prevent accidental schema changes on dev data and ensure safe migrations.

## Rules

- **Agents:** run migrations only on `docker-compose.test.yml` test stack.
- **Never** `migrate:fresh` or `db:wipe` on local dev stack.
- New migrations: reversible `down()` when practical.
- App-owned tables only in `database/migrations/` — do not migrate crawler package tables manually.
- Test migrations with `composer test:docker` after adding migrations.

## Test stack migration

```bash
docker compose -f docker-compose.test.yml run --rm test php artisan migrate --force
```

## Before adding a migration

1. Confirm table ownership (app vs crawler package).
2. Check foreign keys and cascade behavior.
3. Add feature test covering the schema change if behavior-critical.

## Related skills

- `xflickr-docker-testing`
- `docker-dev-stack-safety`
