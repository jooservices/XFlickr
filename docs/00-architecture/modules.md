# Laravel modules catalog

Business domains live under `Modules/{Name}` (`nwidart/laravel-modules`). Thin `app/` is host bootstrap only (providers, middleware, shared FormRequest traits, crawler query repos, `User`).

**Agent map (short):** [`AGENTS.md`](../../AGENTS.md) · **Skill:** [`class-purpose-and-module-map`](../../ai/skills/class-purpose-and-module-map/SKILL.md) · **Package vs crawler:** [Package boundaries](package-boundaries.md)

Each module owns its `routes/web.php`, `routes/api.php` (`/api/v1/*`), controllers, FormRequests, services, repositories, models, jobs, listeners, commands, and co-located tests under `Modules/{Name}/tests`.

---

## Summary

| Module | Purpose (one line) | Typical UI / entry |
|---|---|---|
| [Auth](#auth) | Session identity and account lifecycle | `/login`, `/register`, `/profile`, password reset |
| [Crawler](#crawler) | Flickr crawl engine, catalog models, `xflickr` queue jobs | Artisan `xflickr:dispatch` / Horizon |
| [Settings](#settings) | Runtime config and app credentials | `/settings` |
| [Operations](#operations) | Live ops overview (dashboard, crawl ops) | `/dashboard`, `/operations` |
| [Flickr](#flickr) | Connect accounts and trigger crawls | `/connections?provider=flickr` (legacy `/flickr/accounts`) |
| [Contacts](#contacts) | Contact list, graph, annotations, full-pass | `/contacts`, account contacts |
| [Catalog](#catalog) | Browse crawled photos / sets / galleries / favorites | `/photos`, `/photosets`, … |
| [Spider](#spider) | Opt-in multi-contact frontier expansion | Settings + Flickr Expand start/stop |
| [Transfer](#transfer) | Download to disk and upload to storage | Download/upload actions on accounts |
| [Storage](#storage) | Cloud provider OAuth, browse, sync, delete | `/connections?provider=storage`, `/storages/*` |

---

## Crawler

**Scope:** Flickr crawl **execution** — `FlickrService` / `FlickrCrawlerManager`, fetchers, crawl runs/targets, rate limiter, catalog Eloquent models (`xflickr_*` tables), and Horizon jobs on the `xflickr` queue. Engine FQCNs stay `Modules\Crawler\*` (serialized jobs + host imports). Leaf module: must not import other `Modules\*`.

**Purpose:** Own the former Packagist package `jooservices/xflickr-crawler` in-repo so host modules call one in-tree engine without vendor bind-mounts.

**Features:**

- Public API: `FlickrService::connection(...)` / manager-driven crawl dispatch
- Fetchers for contacts, people photos, photosets, galleries, favorites, subject contacts
- Redis-backed request limiter + API audit / outcome classification
- Artisan: `xflickr:dispatch`, `xflickr:doctor`, `xflickr:prune`
- Config defaults in `Modules/Crawler/config/xflickr-crawler.php`; host root `config/xflickr-crawler.php` remains the committed override (do not delete as a “duplicate”)
- Host still owns shared query repos (`app/Repositories/Crawler/*`) and crawler model factories (`database/factories/Crawler/*`) until a follow-up move

**User guide:** [Crawling](../02-user-guide/crawling.md) (if present), [Rate limiting](../02-user-guide/rate-limiting.md)

---

## Auth

**Scope:** Session authentication and local `User` lifecycle. Does not own Flickr or storage OAuth.

**Purpose:** Gate the SPA and APIs behind login; support register → activate → sign-in; self-serve profile edit and CLI password reset.

**Features:**

- Guest: login, register (new users inactive until activated), forgot-password, reset-password (token in `password_reset_tokens`; reset URL flashed — email not sent yet)
- Auth: profile (name / email / password), logout
- CLI: `xflickr:auth:activate-user`, `xflickr:auth:reset-password`
- Optional `AdminUserSeeder` (`module:seed Auth` / operator seed flows)
- Services: `AuthService`, `UserService` → repositories → host `app/Models/User`

**User guide:** (auth flows are covered in first-run / install docs)

---

## Settings

**Scope:** Operator-editable runtime configuration and OAuth **app** profiles (API keys). Does not store connected user tokens (those live in Flickr/Storage account tables).

**Purpose:** Configure crawler/spider behavior and Flickr/storage application credentials (MongoDB via laravel-config: `xflickr_app.*`, `storage_app.*`).

**Features:**

- Settings Inertia page (`/settings`) — General runtime config (Flickr/storage tabs redirect to `/connections`)
- Header **Pause** / **Resume** for `xflickr.global_pause` (`POST /settings/crawl-pause`); same key remains in General runtime config
- Header **Spider** control opens a modal for `spider.enabled` + depth/caps (`POST /settings/spider`); Settings → General remains available
- General tab: `@jooservices/react-config` `ConfigPanel` (collapsible groups, search, expert/technical toggles, descriptions) over curated + custom runtime keys
- Runtime config create / delete / reset (`RuntimeConfigAdminService`)
- Flickr app profile create/delete (web routes under Settings; UI on Connections → Flickr → Apps)
- Storage app profile upsert (UI on Connections → Storage → Apps)
- Connections hub (`/connections`) composes Flickr + Storage on **Flickr** | **Storage** tabs (credentials and accounts on one surface; Settings owns the Inertia page; OAuth stays in Flickr/Storage modules)
- No dedicated `/api/v1` routes yet (web-only)

**User guide:** [Settings](../02-user-guide/settings.md)

---

## Operations

**Scope:** Read-only operational views that aggregate status from Flickr, Transfer, and related queues. Must not own crawl/transfer mutation APIs (one-way imports: Operations → Flickr | Transfer).

**Purpose:** Give operators a single place to see health and activity (dashboard + crawl operations console).

**Features:**

- Web: `/dashboard`, `/operations` (Overview / Crawl / Transfers panels; `/crawl/operations` redirects)
- API: `/api/v1/dashboard/snapshot`, `/api/v1/operations/snapshot`, `/api/v1/operations/stream`
- Services: `DashboardService`, `DatabaseUsageService`, `ServicesDependencyProbeService`, `CrawlOperationsService`, `OperationsSnapshotService`, `OperationsStreamService`
- Dashboard databases panel: MySQL/MongoDB reachability, size, MySQL connections, top tables, 24h size/connections samples
- Operations Overview: activity metrics, MySQL/Redis/MongoDB probes, compact DB usage, per-account quota/pending

**User guide:** [Dashboard](../02-user-guide/dashboard.md), [Operations](../02-user-guide/operations.md)

---

## Flickr

**Scope:** Connected Flickr **user** accounts, OAuth 1.0a connect/disconnect/activate, crawl-run triggers, rate-limit and token-health queries. Crawl **execution** (fetchers, crawl tables) stays in the **Crawler** module (`Modules\Crawler\*`).

**Purpose:** Bridge the host app to Flickr identity and on-demand indexing via the Crawler module (`connection_key` / NSID).

**Features:**

- Web: OAuth connect/callback/disconnect/activate; account detail; per-account crawl; list hub redirects to Connections
- API: account show, crawl-runs, crawl summary/runs/logs, rate-limit + usage, token-health
- Services: `FlickrOAuthService`, `FlickrCrawlService`, `FlickrTokenHealthService`, rate-limit/crawl status query services
- CLI: `xflickr:flickr:doctor`, `xflickr:flickr:audit-api`
- Support: public connection IDs / presenters

**User guide:** [Connections](../02-user-guide/connections.md), [Rate limiting](../02-user-guide/rate-limiting.md)

---

## Contacts

**Scope:** Contact directory UX on top of crawler contact tables — list/detail, annotations, contact graph, expand preview, and contact full-pass planning. Does not own generic catalog photo grids (Catalog) or spider frontier (Spider).

**Purpose:** Help operators explore who is connected to an account, annotate contacts, expand the graph, and run a structured full-pass crawl over contacts.

**Features:**

- Web: global contacts index; per-account contact list/show; bulk/single contact crawl; full-pass start/stop
- API: contacts progress/suggest, contact-graph (+ delta/expansions), annotations, expand-previews, per-contact crawl-runs
- Services: list/detail/graph/expand/full-pass planner, annotation, catalog/download counts, crawl state
- CLI: `xflickr:contacts:full-pass-expand`
- Models: annotations, full-pass runs/frontier items (module-owned)

**User guide:** [Contacts](../02-user-guide/contacts.md)

---

## Catalog

**Scope:** Read/browse UI and JSON for crawled catalog entities (photos, photosets, galleries, favorites). Data rows live in crawler tables; Catalog owns queries/presentation only.

**Purpose:** Let operators browse indexed Flickr content for an account (and global shortcuts).

**Features:**

- Web: photos, photosets (+ show), galleries, favorites (account-scoped and global routes)
- API: `/api/v1/flickr/catalog/{photos|photosets|galleries|favorites}`
- Service: `CatalogQueryService` (+ presenters)

**User guide:** [Catalog](../02-user-guide/catalog.md)

---

## Spider

**Scope:** Opt-in spider mode — frontier planning and run start/stop/status. Disabled unless `spider.enabled` in runtime config; depth/caps configured in Settings → General.

**Purpose:** Expand contact discovery beyond a single manual target when the operator explicitly enables spider mode.

**Features:**

- Web: spider start/stop per connection via Flickr account Expand actions (not Operations)
- API: current spider-run status
- Service: `SpiderPlannerService`
- Models: `SpiderRun`, `SpiderFrontierItem`
- CLI: `xflickr:spider:expand`
- Manual per-contact crawls (Contacts/Flickr) remain valid without spider

**User guide:** [Spider mode](../02-user-guide/spider-mode.md)

---

## Transfer

**Scope:** Photo download to local disk and upload to connected storage — batching, progress, retries, and `stored_files` tracking. Provider HTTP/drivers live in Storage; Transfer orchestrates transfer jobs and bookkeeping.

**Purpose:** Move Flickr originals onto the server and/or cloud destinations with deduplication and observable progress.

**Features:**

- Web: enqueue download / upload for a connection
- API: stored-file show; transfer list/show; item retry
- Services: download/upload (+ execution), progress/counts, retry, stored-file stream
- Jobs: fan-out batch, download (and related) — call Services only
- Models: `TransferBatch`, `TransferItem`, `StoredFile`

**Related skill:** [`transfer-pipeline-safety`](../../ai/skills/transfer-pipeline-safety/SKILL.md)

---

## Storage

**Scope:** Cloud storage **user** accounts and provider drivers — OAuth (Google / Microsoft), R2 connect, browse, sync, download, delete, upload helpers used by Transfer.

**Purpose:** Connect backup destinations and manage remote files/albums for Google Photos, Google Drive, OneDrive, and Cloudflare R2.

**Features:**

- Web: OAuth connect/callback/reauthorize/disconnect/set-default; R2 connect; browse pages per provider
- API: accounts list; browse/sync/delete/download; Google Photos thumbnail; storage quota snapshot (`GET /api/v1/storage/quota`)
- Services: OAuth + token refresh, browse/sync/delete/upload per driver, account scope/settings, quota query (cached)
- CLI: `xflickr:storage:verify-google-photos`
- Models: `StorageAccount`, remote albums/items/sync state, uploads
- Live Storage quota (OneDrive / Google Drive when the provider exposes it) appears in the sticky app status footer

**User guide:** [Storage browse](../02-user-guide/storage-browse.md) · **Skill:** [`storage-driver-safety`](../../ai/skills/storage-driver-safety/SKILL.md)

---

## Host `app/` (not a module)

| Area | Owns |
|---|---|
| `app/Models/User.php` | Auth identity (Auth module tests) |
| `app/Repositories/Crawler/*` | Shared reads of crawler tables |
| `app/Http/Requests/*` | Shared FormRequest base + pagination/sort/crawl traits |
| Providers / Middleware | Bootstrap |
| `resources/js/` | Inertia UI (pages are domain-shaped but not module-packaged) |

Root `routes/web.php` and `routes/api.php` are stubs — modules register routes.

## Dependency notes

- **Crawler module** owns crawl fetchers and `xflickr_*` crawl/catalog tables — peer modules call `FlickrService` / shared crawler repos; do not reimplement fetchers.
- **Operations** aggregates; it must not be imported by Flickr/Transfer/Spider for core flows.
- **Transfer** depends on **Storage** drivers for uploads; **Storage** does not own transfer batches.
- **Contacts** full-pass and **Spider** frontier share expansion patterns (`FrontierExpansion`) but remain separate products (manual full-pass vs opt-in spider).
