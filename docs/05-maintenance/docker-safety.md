# Docker safety — dev data vs test stack

## Absolute rule (AI agents)

**NO EXCEPTIONS. Agents must not run any command on the local dev stack (`docker-compose.yml`) that reads, writes, migrates, seeds, tests, wipes, or otherwise modifies MySQL or MongoDB.**

The only compose file for database work is **`docker-compose.test.yml`**.

## Two stacks

| File | Purpose | Database |
|------|---------|----------|
| `docker-compose.yml` | **Local development** — user data | MySQL `xflickr` (volume `mysql-data`), MongoDB `xflickr` (volume `mongodb-data`) |
| `docker-compose.test.yml` | **Tests only** | SQLite `:memory:`, MongoDB `xflickr_test` |

## What is stored where

| UI / feature | MySQL | MongoDB |
|---|---|---|
| Flickr app API key/secret (Settings dropdown) | — | `xflickr_app.*` |
| Storage OAuth client IDs | — | `storage_app.*` |
| Connected Flickr accounts | `flickr_accounts` | — |
| Connected storage accounts | `storage_accounts` | — |
| Crawled photos, uploads, browse cache | various tables | — |

A MySQL-only wipe leaves MongoDB app profiles visible with **0 accounts**.

## Incident (2026-06-22)

Agent ran `docker compose exec app php artisan test` on the dev stack. `RefreshDatabase` → `migrate:fresh` → **MySQL wiped**. MongoDB survived until user-requested reset.

## Forbidden on local dev stack (agents — zero exceptions)

- `php artisan test`, `composer test`
- `php artisan migrate`, `migrate:fresh`, `migrate:refresh`, `migrate:reset`
- `php artisan db:seed`, `db:wipe`
- `mongosh`, `mysql` CLI against dev
- `docker compose exec app php artisan …` unless user explicitly approved a named maintenance script

## Permitted on local dev stack (agents)

- `docker compose up`, `build`, `restart`, `logs`, `ps`
- `composer install`, `npm run build` (no database)

## Tests (correct)

```bash
composer test:docker
./scripts/test-docker.sh --filter=ExampleTest
```

## User-initiated MongoDB reset

**Only when the user explicitly requests it:**

```bash
./scripts/reset-local-mongodb.sh
```

Drops MongoDB `xflickr`, rebuilds laravel-config and laravel-events indexes, clears config cache.

After reset: re-enter Flickr and storage app credentials in Settings.

## Recovering after MySQL wipe

1. Reconnect Flickr accounts (Settings → Flickr → Connect)
2. Reconnect storage providers (Settings → Storage)
3. Re-run crawls / downloads as needed

Prevent recurrence: **`composer test:docker` only** — never `docker compose exec app php artisan test`.
