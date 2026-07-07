---
name: operator-dev-docker
description: Hand off Docker dev-stack work to the operator. AI uses test stack only.
---

# Skill: operator-dev-docker

## Purpose

Tell **AI agents** how to hand off Docker work to the **operator** (human). Dev stack is the operator's local workspace — **persistent data must never be touched by AI**. AI uses the **test stack** only.

## When to use

- After backend/frontend changes that need container reload
- When docs or skills mention Horizon reload, Vite refresh, or stack restart
- When an agent would otherwise run `dev.sh`, `deploy.sh`, or `docker compose` against dev

## Two stacks (non-negotiable)

| Stack | Script | Project | Who | Persistent data |
|-------|--------|---------|-----|-----------------|
| **Dev** | `scripts/dev.sh` | `xflickr-dev` | **Operator only** | Operator-owned — **AI must not read, write, migrate, or wipe** |
| **Test** | `scripts/test.sh` | `xflickr-test` | **AI + CI** | Isolated; `migrate:fresh` allowed in test container |

**Never** run bare `docker compose -f docker-compose.dev.yml up` — it skips `-p xflickr-dev` and can create orphan volumes. Always `bash scripts/dev.sh …`.

Named volumes (dev): `xflickr-dev-mysql-data`, `xflickr-dev-mongodb-data`, `xflickr-dev-redis-data`, etc. See [`docker/compose/volumes-dev.yml`](../../docker/compose/volumes-dev.yml).

## AI rules

1. **MAY ONLY run:** `bash scripts/test.sh` (`gate`, `gate:lint`, `gate:test`, `gate:ci`, `ensure-hooks`, `verify-hooks`, `up`, `down`, `down --volumes`)
2. **MUST NOT run:** `scripts/dev.sh`, `scripts/deploy.sh`, bare `docker compose` on dev, or **`docker exec xflickr-dev-*`** (includes `php artisan test` — `RefreshDatabase` wipes dev MySQL when Compose sets `DB_HOST=mysql`)
3. **Before commit / push:** run `bash scripts/test.sh gate` then `bash scripts/test.sh gate:ci`
4. **`gate:test` failure:** report to operator; do not run PHPUnit in dev containers as a workaround
5. After code changes, **tell the operator** which dev commands to run — do not run them
6. Do not suggest `dev.sh refresh` or `dev.sh reset-data` unless the operator explicitly asks to wipe dev data

## Operator command reference (copy to user)

### Start / stop (DB preserved)

```bash
bash scripts/dev.sh up              # start + migrate only (no seed)
bash scripts/dev.sh seed            # up + admin user
bash scripts/dev.sh down            # stop; volumes kept
```

### Reload after code changes (no DB impact)

```bash
docker exec xflickr-dev-horizon-1 php artisan horizon:terminate
bash scripts/dev.sh restart-frontend    # UI / Vite only
bash scripts/dev.sh refresh-frontend    # npm ci in frontend container + restart Vite
bash scripts/dev.sh reload              # build assets + restart app/horizon/scheduler
```

### Destructive (operator explicit opt-in only)

```bash
bash scripts/dev.sh refresh       # migrate:fresh MySQL + admin seed only (schema wipe)
bash scripts/dev.sh reset-data    # stop + remove ALL dev volumes
bash scripts/dev.sh down --volumes   # same as reset-data
```

## Output requirements

When implementation needs operator stack refresh, end with an **Operator actions** block listing exact dev commands — never run them as AI.

## Common mistakes

- Running `dev.sh up` or `refresh` as AI after every task
- Suggesting bare `docker compose` for dev
- **`docker exec xflickr-dev-app-1 php artisan test`** — wipes dev MySQL via `RefreshDatabase` + `DB_HOST=mysql`
- Confusing `restart-frontend` (safe) with `refresh` / `reset-data` (destructive)

## Related skills

- `xflickr-docker-testing`
- `docker-dev-stack-safety`
- `testing-and-quality-gates`
