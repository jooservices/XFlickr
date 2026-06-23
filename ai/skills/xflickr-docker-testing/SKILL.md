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

**Agents NEVER run database-affecting commands on `docker-compose.yml` (local dev stack).**

**All database work uses `docker-compose.test.yml` only.**

## Data locations (local dev)

| Data | Store |
|---|---|
| Flickr/storage OAuth client credentials | MongoDB `xflickr` |
| Connected Flickr/storage accounts | MySQL `xflickr` |
| Crawled photos, uploads, cache | MySQL `xflickr` |

## Forbidden on local stack (agents)

```bash
docker compose exec app php artisan test
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate:fresh
docker compose exec app php artisan db:seed
docker compose exec app php artisan db:wipe
docker compose exec mongodb mongosh ...
docker compose exec mysql mysql ...
```

## Permitted on local stack (agents)

```bash
docker compose up -d --build
docker compose logs -f app
docker compose exec app composer install
docker compose exec app npm run build
```

## Tests — test stack only

```bash
composer test:docker
./scripts/test-docker.sh --filter=ExampleTest
docker compose -f docker-compose.test.yml run --rm test php artisan test
```

## User-only maintenance (explicit request required)

```bash
./scripts/reset-local-mongodb.sh
```

## Before any Docker command

1. Is it `docker-compose.yml`? → **No DB commands.**
2. Does it touch MySQL or MongoDB? → **Use test stack or stop.**
3. Did the user name a maintenance script? → **Run only that script.**

## Related skills

- `docker-dev-stack-safety`
- `database-migration-safety`
- `testing-and-quality-gates`

## References

- `AGENTS.md`
- `docs/05-maintenance/docker-safety.md`
- `.cursor/rules/docker-dev-stack-safety.mdc`
