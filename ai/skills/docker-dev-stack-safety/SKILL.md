---
name: docker-dev-stack-safety
description: Zero database contact on local dev Docker stack — no exceptions for agents.
---

# Skill: docker-dev-stack-safety

## Purpose

Enforce the absolute rule: local dev stack (`docker-compose.yml`) is **read-only for databases** from an agent's perspective.

## Two stacks

| File | Database | Agent use |
|---|---|---|
| `docker-compose.yml` | MySQL `xflickr`, MongoDB `xflickr` | **No DB commands** |
| `docker-compose.test.yml` | SQLite `:memory:`, MongoDB `xflickr_test` | Tests and migrations |

## Why

`docker compose exec` bypasses `docker/entrypoint.sh`. Incident 2026-06-22: agent ran tests on dev stack → `RefreshDatabase` wiped MySQL.

## Incident recovery

If dev MySQL was wiped: reconnect Flickr/storage accounts in Settings, re-run crawls.

## Related skills

- `xflickr-docker-testing`
- `database-migration-safety`
