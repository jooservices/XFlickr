# Docker installation

Recommended way to run XFlickr locally.

## Prerequisites

- Docker Desktop or Docker Engine + Compose v2
- Node.js 20.19+ or 22.12+ (for host-side asset builds; optional if building inside container)

## Steps

```bash
cp .env.example .env
./scripts/docker-up.sh
```

The script:

1. Starts MySQL, Redis, MongoDB, app, Horizon, and scheduler
2. Runs `composer install` and `npm ci && npm run build`
3. Runs migrations and MongoDB index bootstrap

## Services

| Service | URL / port |
|---|---|
| App | http://localhost:8082 |
| Horizon | http://localhost:8082/horizon |
| MySQL | `localhost:3308` |
| Redis | `localhost:6381` |
| MongoDB | `localhost:27019` |

## Optional: Vite HMR

```bash
docker compose up -d vite   # port 5174
```

## Next step

[First run](first-run.md) — configure Flickr and storage credentials.

## See also

- [Environments](environments.md)
- [Docker stacks](../03-operations/docker-stacks.md)
