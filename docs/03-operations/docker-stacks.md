# Docker stacks

XFlickr uses three separate Docker Compose files. **Never mix them.**

| File | Project | Script | Purpose | Database |
|---|---|---|---|---|
| `docker-compose.dev.yml` | `xflickr-dev` | `scripts/dev.sh` | Local development — **user data** | Bundled MySQL `xflickr`, MongoDB `xflickr` |
| `docker-compose.test.yml` | `xflickr-test` | `scripts/test.sh` | Tests and CI only | SQLite `:memory:`, MongoDB `xflickr_test` |
| `docker-compose.prod.yml` | `xflickr-prod` | `scripts/deploy.sh` | Production server | **External** MySQL, Redis, MongoDB |

## Dev stack services

- `app` — Laravel (port `APP_HOST_PORT`, default 8082)
- `horizon` — queue worker
- `scheduler` — Laravel scheduler
- `frontend` — Node + Vite HMR (port `FRONTEND_HOST_PORT`, default 5174)
- `mysql`, `redis`, `mongodb`

## Production stack services

- `nginx` — reverse proxy (port `HTTP_PORT`, default 80)
- `app` — Laravel (`artisan serve`, built assets)
- `horizon` — queue worker (scalable via `HORIZON_REPLICAS`)
- `scheduler` — Laravel scheduler

## Bootstrap (operator)

**Dev:**

```bash
bash scripts/dev.sh up          # full start + migrate (recommended)
bash scripts/dev.sh seed        # up + admin user seed
bash scripts/dev.sh reload      # rebuild assets + restart (no migrations)
bash scripts/dev.sh help
```

**Production:**

```bash
bash scripts/deploy.sh install
bash scripts/deploy.sh update
bash scripts/deploy.sh help
```

## Volume migration

If you previously used `docker-compose.yml` (project `xflickr`), data volumes used names like `xflickr_mysql-data`. The dev stack now uses `xflickr-dev-mysql-data`. Either rename volumes or accept a fresh start.

## Agent rule (absolute)

AI agents must **never** touch the dev or production stacks. Use `bash scripts/test.sh` only. See [Docker safety](../05-maintenance/docker-safety.md).

## Tests (AI + CI)

```bash
bash scripts/test.sh gate:test
```

## See also

- [Production deploy](production-deploy.md)
- [Deploy overview](deploy.md)
