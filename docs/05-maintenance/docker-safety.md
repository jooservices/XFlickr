# Docker safety — dev data vs test stack

## Absolute rule (AI agents)

**NO EXCEPTIONS. Agents must not run any command on the local dev stack (`docker-compose.dev.yml`, project `xflickr-dev`) that reads, writes, migrates, seeds, tests, wipes, or otherwise modifies MySQL or MongoDB.**

The only path for database work is **`bash scripts/test.sh`** against **`docker-compose.test.yml`** (project `xflickr-test`).

## Two stacks

| File | Project | Purpose | Database |
|------|---------|---------|----------|
| `docker-compose.dev.yml` | `xflickr-dev` | **Local development** — user data | MySQL `xflickr` (volume `xflickr-dev-mysql-data`), MongoDB `xflickr` (`xflickr-dev-mongodb-data`) |
| `docker-compose.test.yml` | `xflickr-test` | **Tests only** | SQLite `:memory:`, MongoDB `xflickr_test` |

## Named volumes

Dev and test use explicit volume names to prevent cross-contamination. See [`docker/compose/volumes-dev.yml`](../../docker/compose/volumes-dev.yml) and [`volumes-test.yml`](../../docker/compose/volumes-test.yml).

**Migrating from old `docker-compose.yml` (project `xflickr`):** volumes like `xflickr_mysql-data` do not attach to `xflickr-dev-mysql-data`. Rename volumes manually or start fresh with `bash scripts/dev.sh up`.

## What is stored where

| UI / feature | MySQL | MongoDB |
|---|---|---|
| Flickr app API key/secret (Settings dropdown) | — | `xflickr_app.*` |
| Storage OAuth client IDs | — | `storage_app.*` |
| Connected Flickr accounts | `flickr_accounts` | — |
| Connected storage accounts | `storage_accounts` | — |
| Crawled photos, uploads, browse cache | various tables | — |

## Incident (2026-06-22)

Agent ran `docker compose exec app php artisan test` on the dev stack. `RefreshDatabase` → `migrate:fresh` → **MySQL wiped**. MongoDB survived until user-requested reset.

Code guard: `Tests\Support\RefreshDatabaseGuard` + `Tests\Concerns\SafeRefreshDatabase`.

## Forbidden on local dev stack (agents — zero exceptions)

- `scripts/dev.sh`, `scripts/deploy.sh`
- `docker compose` without test stack
- **`docker exec xflickr-dev-*`** (any command)
- `php artisan test`, `composer test:docker` (use `bash scripts/test.sh gate:test`)
- `php artisan migrate`, `migrate:fresh`, `db:seed`, `db:wipe`
- `mongosh`, `mysql` CLI against dev

## Permitted (agents only)

```bash
bash scripts/test.sh gate
bash scripts/test.sh gate:lint
bash scripts/test.sh gate:test
bash scripts/test.sh gate:ci
bash scripts/test.sh up
bash scripts/test.sh down
```

## User-initiated MongoDB reset

**Only when the user explicitly requests it:**

```bash
./scripts/reset-local-mongodb.sh
```

## Recovering after MySQL wipe

1. Reconnect Flickr accounts (Settings → Flickr → Connect)
2. Reconnect storage providers (Settings → Storage)
3. Re-run crawls / downloads as needed

Prevent recurrence: **`bash scripts/test.sh gate:test` only** — never `docker exec xflickr-dev-app-1 php artisan test`.
