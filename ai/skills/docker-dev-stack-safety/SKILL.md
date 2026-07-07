---
name: docker-dev-stack-safety
description: Zero database contact on local dev Docker stack — no exceptions for agents.
---

# Skill: docker-dev-stack-safety

## Purpose

Enforce the absolute rule: local dev stack (`docker-compose.dev.yml`, project `xflickr-dev`) is **off limits for AI agents**.

## Two stacks

| File | Project | Database | Agent use |
|---|---|---|---|
| `docker-compose.dev.yml` | `xflickr-dev` | MySQL `xflickr`, MongoDB `xflickr` | **No commands** |
| `docker-compose.test.yml` | `xflickr-test` | SQLite `:memory:`, MongoDB `xflickr_test` | `scripts/test.sh` only |

## Why

`docker exec xflickr-dev-*` bypasses `docker/entrypoint.sh`. Incident 2026-06-22: agent ran tests on dev stack → `RefreshDatabase` wiped MySQL. Code guard: `Tests\Support\RefreshDatabaseGuard`.

## AI single entry point

```bash
bash scripts/test.sh gate:test
```

## Related skills

- `operator-dev-docker`
- `xflickr-docker-testing`
- `database-migration-safety`
