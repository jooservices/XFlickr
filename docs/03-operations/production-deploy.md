# Production deploy

Install and operate XFlickr on a server. Choose **Docker** (default) or **host** (Ubuntu 22.04) at first install. Both use **external** MySQL, Redis, and MongoDB.

## Deploy targets

| Target | `DEPLOY_TARGET` | Stack |
|---|---|---|
| Docker (recommended) | `docker` | nginx + app + horizon + scheduler containers (`xflickr-prod`) |
| Host | `host` | nginx → `php artisan serve`, supervisor for horizon + scheduler |

```bash
bash scripts/deploy.sh install   # wizard asks Docker vs host on first run
```

## Release pipeline (all targets)

Every `install`, `finish`, `update`, `deploy`, and `configure` runs the same finalize steps **before reporting success**:

1. `php artisan migrate --force`
2. Clear caches (`config`, `route`, `view`, `cache`, `optimize`)
3. Rebuild caches (`config`, `route`, `view`)
4. `php artisan horizon:terminate --wait`
5. Restart web and worker services
6. Post-deploy verification (`bash scripts/deploy.sh verify` checks)

If verification fails, the script exits with an error and does **not** print “complete”.

## Docker prerequisites

- Docker Engine + Compose v2
- Git
- External MySQL, Redis, and MongoDB reachable from containers

## Host prerequisites (Ubuntu 22.04)

The wizard can auto-install on Ubuntu 22.04:

- PHP 8.5+ with required extensions
- Composer 2, Node.js 22+
- nginx, supervisor

Other distros: install prerequisites manually, then run `bash scripts/deploy.sh install` and choose **host**.

## First install

```bash
git clone https://github.com/jooservices/XFlickr.git
cd XFlickr
bash scripts/deploy.sh install
```

The wizard will:

1. Choose deploy target (Docker or host)
2. Optionally `git pull`
3. Prompt for `APP_URL`, HTTP port, and admin credentials
4. Collect MySQL, Redis, and MongoDB — **testing each connection before continuing**
5. Save progress to `storage/.xflickr-deploy-wizard` (resumable until `.env` is written)
6. Optionally enable HTTPS
7. Write `.env` (includes `DEPLOY_TARGET`), build assets, start services, run finalize pipeline + verify

Finish without re-running the wizard:

```bash
bash scripts/deploy.sh finish
```

## Updates

```bash
bash scripts/deploy.sh          # or deploy / update
```

Safe updates:

- `git pull --ff-only`
- Rebuild assets
- Migrate + cache clear/recache + worker restart
- Verify before success message
- Never `migrate:fresh`, `db:wipe`, or `down --volumes`

### Transfer namespace cutover

The Storage/Transfer boundary release moves serialized jobs from `Modules\Storage\Jobs\*` to
`Modules\Transfer\Jobs\*`. For the first deployment containing that change:

1. Stop users and automation from queuing new downloads or uploads.
2. In Horizon, wait until `xflickr-downloads` and `xflickr-uploads` both have zero pending and running jobs.
3. Review failed jobs before deploying; retry or discard them according to their actual outcome.
4. Run the normal deploy/update command only after the queues are empty.
5. Verify Horizon restarted with the release, then resume transfer queueing.

Do not deploy this namespace move over in-flight jobs. Redis payloads contain their PHP class names,
and the new release intentionally provides no legacy Storage job aliases.

## Post-deploy verification

| Check | Docker | Host |
|---|---|---|
| Services | compose `app`, `nginx`, `horizon`, `scheduler` | supervisor programs |
| External DBs | MySQL, Redis, MongoDB connectivity | same |
| Web | `/login`, `/build/manifest.json` via nginx | same |
| App health | `migrate:status`, `xflickr:flickr:doctor` | same |
| Workers | Horizon + scheduler processes | same |

```bash
bash scripts/deploy.sh verify
```

## Commands

```bash
bash scripts/deploy.sh configure
bash scripts/deploy.sh configure-ssl
bash scripts/deploy.sh scale 3
bash scripts/deploy.sh ps
bash scripts/deploy.sh logs app
bash scripts/deploy.sh down
```

## Same-host databases

- **Docker**: use `host.docker.internal` or bridge gateway (`172.17.0.1` on Linux)
- **Host**: use `127.0.0.1` (wizard default)

## Flickr OAuth

Register `{APP_URL}/flickr/callback` in your Flickr app settings.

## See also

- [Docker stacks](docker-stacks.md)
- [Deploy overview](deploy.md)
- [Native installation](../01-getting-started/native-installation.md)
