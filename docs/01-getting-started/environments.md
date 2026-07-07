# Environments

## Local Docker dev (operator)

| Variable | Dev stack value |
|---|---|
| `APP_URL` | `http://localhost:8082` (or `APP_HOST_PORT`) |
| `DB_HOST` | `mysql` (inside containers) / `localhost:3308` (from host) |
| `REDIS_HOST` | `redis` / `localhost:6381` |
| `MONGODB_URI` | `mongodb://mongodb:27017` / `localhost:27019` |
| `FLICKR_CALLBACK_URL` | `http://localhost:8082/flickr/callback` |

Compose file: `docker-compose.dev.yml` (project `xflickr-dev`)

Start: `bash scripts/dev.sh up`

## Test stack (CI and agents only)

| Variable | Test stack value |
|---|---|
| `DB_CONNECTION` | `sqlite` (`:memory:`) |
| `MONGODB_DATABASE` | `xflickr_test` |

Compose file: `docker-compose.test.yml` (project `xflickr-test`)

**Never run tests against the dev stack** — `RefreshDatabase` wipes MySQL.

```bash
bash scripts/test.sh gate:test
```

Port overrides: `.env.test.example` (loaded by `scripts/test.sh`).

## Production considerations

- Set `APP_DEBUG=false`
- Use strong database passwords
- Protect Horizon dashboard (configure `horizon` middleware in `config/horizon.php`)
- Run Horizon and scheduler via Supervisor or systemd
- See [Deploy](../03-operations/deploy.md)
