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

Full details: [Docker safety](docker-safety.md) and [`AGENTS.md`](../../AGENTS.md).
