---
name: xflickr-docker-testing
description: ABSOLUTE database safety for XFlickr Docker. Test stack only for any DB work.
---

# Skill: xflickr-docker-testing

## Purpose

Prevent agents from running database-affecting commands on the local dev Docker stack.

## When to use

Before **any** `docker compose`, `php artisan`, test, migrate, seed, `mongosh`, `mysql`, or database command.

## Hard rule (no exceptions)

**Agents NEVER run database-affecting commands on `docker-compose.dev.yml` (local dev stack).**

**All database work uses `docker-compose.test.yml` via `bash scripts/test.sh` only.**

## Two stacks

| File | Project | Database | Agent use |
|---|---|---|---|
| `docker-compose.dev.yml` | `xflickr-dev` | MySQL `xflickr`, MongoDB `xflickr` | **No commands** |
| `docker-compose.test.yml` | `xflickr-test` | SQLite `:memory:`, MongoDB `xflickr_test` | Tests via `scripts/test.sh` |

## Forbidden on dev stack (agents)

```bash
scripts/dev.sh
docker compose -f docker-compose.dev.yml ...
docker exec xflickr-dev-app-1 php artisan test
docker exec xflickr-dev-*   # any command
docker compose exec app php artisan migrate
composer test:docker        # use scripts/test.sh gate:test instead
```

## Permitted (agents only)

```bash
bash scripts/test.sh gate
bash scripts/test.sh gate:lint
bash scripts/test.sh gate:test
bash scripts/test.sh gate:ci
bash scripts/test.sh up
bash scripts/test.sh down
```

## User-only maintenance (explicit request required)

```bash
./scripts/reset-local-mongodb.sh
```

## Before any Docker command

1. Is it dev stack (`docker-compose.dev.yml` / `xflickr-dev`)? → **Stop.**
2. Does it touch MySQL or MongoDB on dev? → **Use `scripts/test.sh` only.**
3. Did the user name a maintenance script? → **Run only that script.**

## Related skills

- `operator-dev-docker`
- `docker-dev-stack-safety`
- `database-migration-safety`
- `testing-and-quality-gates`

## References

- `AGENTS.md`
- `docs/05-maintenance/docker-safety.md`
- `.cursor/rules/docker-dev-forbidden.mdc`
