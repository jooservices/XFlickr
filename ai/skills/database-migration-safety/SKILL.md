---
name: database-migration-safety
description: MySQL migration safety — test stack only for agents; rollback awareness.
---

# Skill: database-migration-safety

## Purpose

Prevent accidental schema changes on dev data and ensure safe migrations.

## Rules

- **Agents:** never run migrations on dev stack. Validate via `bash scripts/test.sh gate:test` after adding migrations.
- **Never** `migrate:fresh` or `db:wipe` on local dev stack.
- New migrations: reversible `down()` when practical.
- App-owned tables only in `database/migrations/` — do not migrate crawler package tables manually.

## Test stack

Migrations are exercised automatically by PHPUnit in the test container (`sqlite :memory:`).

```bash
bash scripts/test.sh gate:test
```

## Before adding a migration

1. Confirm table ownership (app vs crawler package).
2. Check foreign keys and cascade behavior.
3. Add feature test covering the schema change if behavior-critical.

## Related skills

- `xflickr-docker-testing`
- `operator-dev-docker`
- `docker-dev-stack-safety`
