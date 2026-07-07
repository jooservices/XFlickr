# Production deploy

Install and operate XFlickr on a server with Docker. Production uses **external** MySQL, Redis, and MongoDB — no database containers in the prod stack.

## Prerequisites

- Docker Engine + Compose v2 (your user must be able to run `docker ps` — add yourself to the `docker` group if needed)
- Git
- External MySQL, Redis, and MongoDB reachable from Docker containers

## First install

```bash
git clone https://github.com/jooservices/XFlickr.git
cd XFlickr
bash scripts/deploy.sh install
```

The wizard will:

1. Optionally `git pull`
2. Prompt for `APP_URL`, HTTP port, and admin credentials (`APP_URL` without `http://` is normalized automatically)
3. Collect MySQL, Redis, and MongoDB host/credentials — **testing each connection before continuing** (host `mysql`/`redis-cli`/`mongosh` when available)
4. Re-prompt until each service is reachable; progress is saved to `storage/.xflickr-deploy-wizard` after each step so you can resume an interrupted install
5. Optionally enable HTTPS (self-signed or your own cert/key files)
6. Write `.env`, build images, and start nginx + app + horizon + scheduler
7. Run post-deploy verification (connections, web ready, workers, doctor)

If install is interrupted, run `bash scripts/deploy.sh install` again — completed steps are skipped using the saved file (removed after `.env` is written).

## Updates

When a new release is available, run from the project directory on the server:

```bash
bash scripts/deploy.sh
```

Or explicitly:

```bash
bash scripts/deploy.sh deploy
bash scripts/deploy.sh update
```

If containers already exist, the script shows the current deployment, lists incoming commits, and asks before proceeding. The update is **safe for existing data**:

- `git pull --ff-only` only
- `php artisan migrate --force` (additive migrations — never `migrate:fresh` or `db:wipe`)
- Rolling container restart — no `down --volumes`
- External MySQL, Redis, and MongoDB data is never touched

`bash scripts/deploy.sh install` refuses to run when a deployment already exists (use `deploy` or `update` instead).

## Post-deploy verification

After `install`, `update`, and `configure`, the script automatically checks:

| Check | What it validates |
|---|---|
| Docker services | `app`, `nginx`, `scheduler`, and `horizon` replicas are running |
| External connectivity | MySQL, Redis, MongoDB reachable from Docker |
| Web readiness | `/login` and built frontend assets respond via nginx |
| Application health | Migrations, `xflickr:doctor`, build manifest inside app container |
| Background workers | Horizon master and scheduler processes active |

Re-run manually anytime:

```bash
bash scripts/deploy.sh verify
```

## Other commands

```bash
bash scripts/deploy.sh configure        # Re-run service wizard (preserves APP_KEY)
bash scripts/deploy.sh configure-ssl    # Update HTTPS settings
bash scripts/deploy.sh verify           # Re-run health checks without restarting
bash scripts/deploy.sh scale 3          # Run 3 Horizon containers
bash scripts/deploy.sh ps
bash scripts/deploy.sh logs app
bash scripts/deploy.sh down
```

## Horizon workers

| Setting | Where |
|---|---|
| Workers per queue type | Settings → General → **Queue** (runtime config) |
| Horizon container count | `HORIZON_REPLICAS` in `.env` or `bash scripts/deploy.sh scale N` |

Total workers ≈ UI value × replica count.

## Same-host databases

If MySQL/Redis/Mongo run on the same machine as Docker, use `host.docker.internal` (Docker Desktop) or the Docker bridge gateway IP on Linux (often `172.17.0.1`). The wizard suggests this when `localhost` fails from inside a container.

## Flickr OAuth

Register this callback URL in your Flickr app settings (shown at end of wizard):

```
{APP_URL}/flickr/callback
```

## See also

- [Docker stacks](docker-stacks.md) — dev / testing / production separation
- [Deploy overview](deploy.md)
- [Environments](../01-getting-started/environments.md)
