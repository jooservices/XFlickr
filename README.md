# XFlickr

**Self-hosted Flickr archive manager** — connect your Flickr accounts, crawl contacts and catalogs on your schedule, download photos to your server, and back them up to cloud storage.

Built with Laravel 12, React 19, and Inertia. Crawling is powered by [`jooservices/xflickr-crawler`](https://packagist.org/packages/jooservices/xflickr-crawler).

---

## What it does

XFlickr helps you **own your Flickr data**. Connect one or more Flickr accounts, discover your contacts, index their photos/photosets/galleries/favorites, download originals to local disk, and upload copies to the cloud — all from a clean web UI you run yourself.

Every crawl, download, and upload is **manual and user-triggered**. Nothing runs in the background unless you start it. That keeps Flickr API usage predictable and under your control.

### Highlights

| Area | What you get |
|---|---|
| **Flickr accounts** | OAuth 1.0a connect/disconnect, multiple accounts, active-account switching |
| **Contacts** | Browse contacts per account, crawl their catalogs, bulk download/upload selected contacts |
| **Catalog** | Browse photos, photosets, galleries, and favorites discovered by crawls |
| **Local downloads** | Download originals to server storage with deduplication (skip already-downloaded files) |
| **Cloud backup** | Upload to **Google Photos**, **Google Drive**, **OneDrive**, or **Cloudflare R2** |
| **Storage browser** | Browse, sync, and manage files already uploaded to each connected storage |
| **Dashboard** | Live stats — catalog size, active transfers, API quota, rate-limit cooldowns |
| **Operations** | Track crawl runs and download/upload batches in progress |
| **Settings** | Flickr API keys, storage OAuth apps, runtime config, global crawl pause |

---

## How it works

```
1. Settings     → Add your Flickr API key/secret and storage app credentials
2. Connect      → Authorize your Flickr account(s) and cloud storage account(s)
3. Crawl        → Index contacts, photos, photosets, galleries, or favorites
4. Download     → Pull photo files to local disk (deduplicated)
5. Upload       → Push local files to your chosen cloud storage (one at a time)
```

Crawl jobs are queued via **Laravel Horizon** and respect Flickr rate limits automatically. The **Operations** page and **Dashboard** show what is running, pending, or failed.

---

## Requirements

| Component | Purpose |
|---|---|
| PHP 8.5+ | Application runtime |
| MySQL | Flickr accounts, stored files, transfer tracking |
| Redis | Queues (Horizon) and crawler rate limiting |
| MongoDB | App credentials (`laravel-config`), domain events, audit logs |
| Node.js 20.19+ or 22.12+ | Frontend build (`package.json` `engines`) |

---

## Quick start (Docker — recommended)

```bash
cp .env.example .env
./scripts/docker-up.sh
```

The script builds containers, installs dependencies, runs migrations, and bootstraps MongoDB indexes.

| Service | URL / port |
|---|---|
| **App** | http://localhost:8082 |
| **Horizon** | http://localhost:8082/horizon |
| **MySQL** | `localhost:3308` (user `xflickr` / password `secret` / database `xflickr`) |
| **Redis** | `localhost:6381` |
| **MongoDB** | `localhost:27019` (database `xflickr`) |

Optional Vite HMR for frontend development:

```bash
docker compose up -d vite   # port 5174
```

### First-time setup

1. Sign in at **http://localhost:8082/login** with `admin@local` / `password` (seeded automatically on first Docker start).
2. Open **http://localhost:8082/settings**
3. **Flickr tab** — enter your [Flickr API](https://www.flickr.com/services/apps/create/) key and secret. Set the callback URL to `http://localhost:8082/flickr/callback` (or your `APP_URL` + `/flickr/callback`).
4. Click **Connect Flickr** and authorize your account.
5. **Storage tab** — add Google / Microsoft OAuth app credentials (or Cloudflare R2 keys), then connect a storage account.
6. Go to **Flickr** → pick an account → **Crawl** to index contacts and catalogs.
7. Use **Download** and **Upload** when you are ready to transfer files.

---

## Install (native PHP)

For running outside Docker (you manage MySQL, Redis, and MongoDB yourself):

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan config-store:ensure-index
php artisan events:install-indexes
npm run build
```

Start the app, queue worker, and scheduler:

```bash
php artisan serve
php artisan horizon
php artisan schedule:work   # or cron: * * * * * php artisan schedule:run
```

Configure Flickr app credentials in **Settings** before connecting accounts.

---

## UI overview

| Page | Path | Description |
|---|---|---|
| Dashboard | `/dashboard` | Global and per-account health snapshot |
| Contacts | `/contacts` | Contact list with crawl/download/upload actions |
| Photos | `/photos` | Catalog browser — all discovered photos |
| Photosets | `/photosets` | Catalog browser — photosets |
| Galleries | `/galleries` | Catalog browser — galleries |
| Favorites | `/favorites` | Catalog browser — favorites |
| Flickr | `/flickr/accounts` | Connected accounts — crawl, download, upload |
| Operations | `/crawl/operations` | Live crawl runs and transfer batches |
| Settings | `/settings` | Credentials, connections, runtime options |
| Google Photos | `/storages/google-photos` | Browse uploaded Google Photos |
| Google Drive | `/storages/google-drive` | Browse uploaded Google Drive files |
| OneDrive | `/storages/onedrive` | Browse uploaded OneDrive files |
| Cloudflare R2 | `/storages/r2` | Browse uploaded R2 objects |

---

## Storage providers

| Provider | Auth | Notes |
|---|---|---|
| Google Photos | OAuth 2.0 | Upload and browse app-created photos |
| Google Drive | OAuth 2.0 | Upload and browse app-created files |
| OneDrive | OAuth 2.0 | Upload and browse files |
| Cloudflare R2 | API keys (S3-compatible) | No OAuth — configure endpoint, bucket, and keys in Settings |

---

## Operations & background jobs

The Docker stack starts **Horizon** (queue worker) and the **scheduler** automatically.

| Task | Command |
|---|---|
| Queue worker | `php artisan horizon` |
| Scheduler | `php artisan schedule:work` or cron `* * * * * php artisan schedule:run` |
| Crawl dispatch | `xflickr:dispatch` runs every minute via scheduler (drains queued crawl targets only) |

Monitor job progress at **http://localhost:8082/horizon** or the in-app **Operations** page.

---

## Packages

| Package | Role |
|---|---|
| [`jooservices/xflickr-crawler`](https://packagist.org/packages/jooservices/xflickr-crawler) | Crawling engine — connections, rate limits, fetchers, jobs |
| [`jooservices/flickr`](https://packagist.org/packages/jooservices/flickr) | Flickr API SDK (OAuth 1.0a, photos, people, upload) |
| [`jooservices/laravel-config`](https://packagist.org/packages/jooservices/laravel-config) | App credentials stored in MongoDB |
| [`jooservices/laravel-logging`](https://packagist.org/packages/jooservices/laravel-logging) | Audit logs for settings and credential changes |
| [`jooservices/laravel-events`](https://packagist.org/packages/jooservices/laravel-events) | Domain events for account connect/disconnect lifecycle |
| [`jooservices/laravel-repository`](https://packagist.org/packages/jooservices/laravel-repository) | Repository pattern for app models |

---

## Development

### Tests

Use the **isolated test stack** only — never run tests against the local dev stack (they wipe MySQL via `RefreshDatabase`):

```bash
composer test:docker
./scripts/test-docker.sh --filter=ExampleTest
```

### Local maintenance

Reset MongoDB app credentials when you want a clean slate (user-initiated only):

```bash
./scripts/reset-local-mongodb.sh
```

### Further reading

- [Documentation hub](docs/README.md)
- [Docker safety (agents)](docs/05-maintenance/docker-safety.md)
- [Agent rules](AGENTS.md)

---

## Documentation

| Audience | Start here |
|---|---|
| Operators (self-hosters) | [Getting started](docs/01-getting-started/docker-installation.md) → [User guide](docs/02-user-guide/dashboard.md) |
| Developers | [Development setup](docs/04-development/setup.md) → [Coding standards](docs/04-development/coding-standards.md) |
| AI agents | [`AGENTS.md`](AGENTS.md) → [`ai/README.md`](ai/README.md) |

Full index: [`docs/README.md`](docs/README.md)

## AI support

| Entry | Purpose |
|---|---|
| [`AGENTS.md`](AGENTS.md) | Non-negotiable rules for every agent |
| [`CLAUDE.md`](CLAUDE.md) | Claude Code wrapper |
| [`ai/README.md`](ai/README.md) | Skills map and tool routing |
| [`ai/skills/`](ai/skills/) | Canonical skill bodies |

Non-trivial feature work: [AI development workflow](docs/04-development/ai-development-workflow.md).

## Community

- [Contributing](CONTRIBUTING.md)
- [Security policy](SECURITY.md)
- [Code of conduct](CODE_OF_CONDUCT.md)
- [Changelog](CHANGELOG.md)

---

## License

[MIT](LICENSE)
