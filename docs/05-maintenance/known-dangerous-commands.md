# Known dangerous commands

Commands that must **never** be run by AI agents on the local dev stack (`docker-compose.yml`).

## Tests (wipe MySQL)

```bash
docker compose exec app php artisan test    # FORBIDDEN
php artisan test                            # FORBIDDEN when .env points at dev DB
composer test                               # FORBIDDEN (runs artisan test locally)
```

Use instead:

```bash
composer test:docker
```

## Migrations and seeds

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate:fresh
docker compose exec app php artisan db:seed
docker compose exec app php artisan db:wipe
```

## MongoDB

```bash
docker compose exec mongodb mongosh
./scripts/reset-local-mongodb.sh            # USER-EXPLICIT ONLY
```

## Direct database clients on dev

```bash
docker compose exec mysql mysql ...
docker compose exec app php artisan tinker  # when writing to DB
```

## Config / events stores

```bash
docker compose exec app php artisan config-store:*
docker compose exec app php artisan events:*   # when writing indexes/data
```

## Why

`docker compose exec` bypasses `docker/entrypoint.sh`. There is no runtime safety net. A real incident (2026-06-22) wiped dev MySQL when an agent ran tests on the dev stack.

## Permitted on dev stack (agents)

```bash
docker compose up|down|build|restart|logs|ps|pull
docker compose exec app composer install
docker compose exec app npm ci
docker compose exec app npm run build
```

Full details: [Docker safety](docker-safety.md) and [`AGENTS.md`](../../AGENTS.md).
