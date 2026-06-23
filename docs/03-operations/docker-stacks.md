# Docker stacks

XFlickr uses two separate Docker Compose files. **Never mix them.**

| File | Purpose | Database |
|---|---|---|
| `docker-compose.yml` | Local development — **user data** | MySQL `xflickr`, MongoDB `xflickr` |
| `docker-compose.test.yml` | Tests and CI only | SQLite `:memory:`, MongoDB `xflickr_test` |

## Dev stack services

- `app` — PHP-FPM + Nginx (port 8082)
- `horizon` — queue worker
- `scheduler` — Laravel scheduler
- `mysql`, `redis`, `mongodb`
- `vite` (optional) — HMR on port 5174

## Bootstrap

```bash
./scripts/docker-up.sh
```

## Agent rule (absolute)

AI agents must **never** run tests, migrations, seeds, or any DB command on the dev stack. See [Docker safety](../05-maintenance/docker-safety.md).

## Tests

```bash
composer test:docker
./scripts/test-docker.sh --filter=ExampleTest
```

## User-initiated MongoDB reset

Only when explicitly requested:

```bash
./scripts/reset-local-mongodb.sh
```
