# Docker installation

Recommended way to run XFlickr locally. **Docker only** — no host PHP or Node required.

## Prerequisites

- Docker Desktop or Docker Engine + Compose v2

## Steps

```bash
cp .env.example .env
bash scripts/dev.sh up
```

(`./scripts/docker-up.sh` runs the same command.)

The script:

1. Starts MySQL, Redis, MongoDB, app, Horizon, scheduler, and frontend (Vite)
2. Runs `composer install` and `npm ci && npm run build` inside containers
3. Runs migrations and MongoDB index bootstrap

## Services

| Service | URL / port |
|---|---|
| App | http://localhost:8082 |
| Vite HMR | http://localhost:5174 |
| Horizon | http://localhost:8082/horizon |
| MySQL | `localhost:3308` |
| Redis | `localhost:6381` |
| MongoDB | `localhost:27019` |

## Volume migration

If you previously used `docker-compose.yml` (project `xflickr`), see [Docker stacks](../03-operations/docker-stacks.md#volume-migration).

## Tests (developers / AI)

```bash
bash scripts/test.sh gate
```

Never run tests on the dev stack. See [Docker safety](../05-maintenance/docker-safety.md).
