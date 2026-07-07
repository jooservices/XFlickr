# Docker stacks

XFlickr uses two separate Docker Compose files. **Never mix them.**

| File | Project | Purpose | Database |
|---|---|---|---|
| `docker-compose.dev.yml` | `xflickr-dev` | Local development — **user data** | MySQL `xflickr`, MongoDB `xflickr` |
| `docker-compose.test.yml` | `xflickr-test` | Tests and CI only | SQLite `:memory:`, MongoDB `xflickr_test` |

## Dev stack services

- `app` — Laravel (port `APP_HOST_PORT`, default 8082)
- `horizon` — queue worker
- `scheduler` — Laravel scheduler
- `frontend` — Node + Vite HMR (port `FRONTEND_HOST_PORT`, default 5174)
- `mysql`, `redis`, `mongodb`

## Bootstrap (operator)

```bash
bash scripts/dev.sh up          # full start + migrate (recommended)
bash scripts/dev.sh seed        # up + admin user seed
bash scripts/dev.sh quick       # start containers only
bash scripts/dev.sh help        # all commands
```

`./scripts/docker-up.sh` is an alias for `bash scripts/dev.sh up`.

## Volume migration

If you previously used `docker-compose.yml` (project `xflickr`), data volumes used names like `xflickr_mysql-data`. The dev stack now uses `xflickr-dev-mysql-data`. Either rename volumes or accept a fresh start.

## Agent rule (absolute)

AI agents must **never** touch the dev stack. Use `bash scripts/test.sh` only. See [Docker safety](../05-maintenance/docker-safety.md).

## Tests (AI + CI)

```bash
bash scripts/test.sh gate:test
```

## User-initiated MongoDB reset

Only when explicitly requested:

```bash
./scripts/reset-local-mongodb.sh
```
