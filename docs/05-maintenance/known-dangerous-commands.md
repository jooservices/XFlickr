# Known dangerous commands

Commands that must **never** be run by AI agents on the local dev stack (`docker-compose.dev.yml`, project `xflickr-dev`).

## Dev stack (forbidden for AI)

```bash
scripts/dev.sh
scripts/deploy.sh
docker compose -f docker-compose.dev.yml ...
docker exec xflickr-dev-*              # ANY command
```

## Tests (wipe MySQL)

```bash
docker exec xflickr-dev-app-1 php artisan test    # FORBIDDEN
docker compose exec app php artisan test          # FORBIDDEN
php artisan test                                  # FORBIDDEN when .env points at dev DB
composer test:docker                              # use scripts/test.sh gate:test
```

Use instead:

```bash
bash scripts/test.sh gate:test
```

## Migrations and seeds

```bash
docker exec xflickr-dev-app-1 php artisan migrate
docker exec xflickr-dev-app-1 php artisan migrate:fresh
docker exec xflickr-dev-app-1 php artisan db:seed
docker exec xflickr-dev-app-1 php artisan db:wipe
```

## MongoDB

```bash
docker exec xflickr-dev-mongodb-1 mongosh
./scripts/reset-local-mongodb.sh            # USER-EXPLICIT ONLY
```

## Why

`docker exec xflickr-dev-*` bypasses `docker/entrypoint.sh`. Code guard: `Tests\Support\RefreshDatabaseGuard`. Incident 2026-06-22 wiped dev MySQL.

## Permitted (agents only)

```bash
bash scripts/test.sh gate
bash scripts/test.sh gate:lint
bash scripts/test.sh gate:test
bash scripts/test.sh gate:ci
bash scripts/test.sh up
bash scripts/test.sh down
```

## Queue job class renames (Horizon)

Redis-serialized Horizon payloads store **FQCN** strings. Renaming or moving any queued job class (or a class nested in a job payload) without draining queues causes `Class not found` on workers.

Operator sequence:

1. Pause crawl dispatchers / stop enqueuing new work.
2. Drain `xflickr*` queues to zero (Horizon dashboard or `php artisan horizon:status` / failed-jobs review).
3. Deploy the rename.
4. Resume dispatchers.

Do not rename queued classes mid-flight on a busy queue. Historical examples that require this drain-before-deploy sequence:

- Crawler absorption: `JOOservices\XFlickrCrawler\*` → `Modules\Crawler\*`.
- Crawl job consolidation: `Fetch*PageJob` → `FetchCrawlPageJob`.
- Storage/Transfer cleanup: `Modules\Storage\Jobs\*` → `Modules\Transfer\Jobs\*` for fan-out, download, and upload jobs.

Full details: [Docker safety](docker-safety.md) and [`AGENTS.md`](../../AGENTS.md).
