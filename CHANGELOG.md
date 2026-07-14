# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- FS-11 demo dataset seeder (`Database\Seeders\DemoDatasetSeeder`): one Flickr connection, ~30 contacts, ~100 photos across two photosets and one gallery, completed/failed download batches, and a Google Photos account with local browse cache. Operator path: `bash scripts/dev.sh seed --demo` (instructions: `bash scripts/test.sh e2e:prep`).
- T2 Playwright smoke specs: `tests/e2e/contacts.spec.ts`, `catalog.spec.ts`, `transfers.spec.ts`, `storage.spec.ts` with shared `tests/e2e/helpers/auth.ts`; sparse `data-testid` hooks on Contacts, Catalog Photos, Operations transfers, and Storage browse pages. `PLAYWRIGHT_BASE_URL` env override in `playwright.config.ts`.
- Feature test `DemoDatasetSeederTest` for seeder counts and idempotency.

### Changed

- F3 PascalCase domain dirs: dissolved `Components/macros/` into module-aligned folders (`Contacts/Graph/`, `Flickr/`, `Catalog/`, `Transfer/`); barrel re-exports updated.
- F2 large-shell splits: `useContactGraphState` + `ContactGraphLegend` extracted from contact graph shell (≤250 lines); `StorageCredentialsPanel` split into picker/OAuth/R2 modals and destination cards (each file ≤200 lines).
- A6 module facades: peer modules import only `FlickrAccountsService`, `StorageService`, and `ContactsService` from Flickr/Storage/Contacts `Services\` (architecture test `ModuleFacadeImportTest`). Focused services remain for internal module use.
- A8 host-test placement: module-owned leftovers migrated from `tests/` into `Modules/*/tests/`; host keeps architecture, crawler query repos, shared Support, and cross-cutting glue only (`ModuleOwnedHostTestPlacementTest`).
- Storage/Transfer refactor (A1–A5): removed per-provider `Drivers/` classes (browse/delete services implement contracts directly); moved `StorageBrowseResult`, `StorageStreamResult`, and `TransferQueueResult` into module `Dto` namespaces; grouped provider services under `GooglePhotos/`, `GoogleDrive/`, `OneDrive/`, `R2/`, `Tokens/` and Flickr rate-limit under `RateLimit/`; folded `CrawlOperationsService` into `CrawlOperationsController` and `StorageR2ConnectionVerifier` into `R2\BrowseService::verifyConnection`.
- Third-party I/O logging via `App\Support\ThirdPartyApiLogger` (StorageApiLogger wraps it). Google Photos `mediaItems:batchCreate` logs `upload_token_present` only; Flickr OAuth / token probe / crawl pause+invalid paths log fingerprints (≤12 hex) never raw secrets (`SECURITY.md`).
- Password reset logging omits the reset URL/token (email only); `SECURITY.md` documents hashed-at-rest tokens and flash/CLI delivery.
- A4 DAG edges: `DownloadCandidateDto` → `Modules/Flickr/Dto`; `OAuthAppConfigDto` → `App\Dto`; Spider uses `ConcurrentRunGuard` (Contacts binds) instead of importing Contacts repositories; download/upload queue controllers + FormRequests moved Transfer → Contacts (URIs unchanged). Residual `Storage→Transfer` import cleared: `StorageUpload` keeps `stored_file_id` FK only (no `storedFile()` Eloquent relation); `ModuleDependencyDirectionTest::KNOWN_VIOLATIONS` is empty.
- Crawler connection and crawl-run persistence go through repositories (`ConnectionRepository`, `CrawlRunRepository`, `CrawlTargetRepository`, `ApiLogRepository`, plus catalog repos). Crawler Services/Commands/Jobs must not call Eloquent directly (`CrawlerLayeringTest`). Docs/skills/`AGENTS.md` enforce Service → Repository → Model; Prefer model scopes in repositories.
- Absorbed `jooservices/xflickr-crawler` into in-repo **`Modules/Crawler`**. Engine PSR-4 is `Modules\Crawler\*` (aligned with other modules; was `JOOservices\XFlickrCrawler\*`). Removed the Packagist dependency and Docker bind-mounts of `../XFlickrCrawler`. Host `config/xflickr-crawler.php` remains the override; module config is defaults-only — do not delete the host file as a duplicate.
- Test coverage: set PCOV `pcov.directory=/var/www/html` so module code under `Modules/` is counted (previously only `app/` was covered).
- Connection-backed Flickr REST calls always use OAuth (`FlickrClientFactory` wraps clients with force-auth). Intentional anonymous API probes must use a plain `FlickrFactory` client and `RequestOptionsData(authenticated: false)`. Fixes private-photo downloads failing `flickr.photos.getSizes` with misleading `Photo not found`.

### Added

- Module service facades (A6): `FlickrAccountsService`, `StorageService`, `ContactsService` — cross-module entry points for Flickr accounts/crawl/health, storage settings/upload, and contact list queries.
- A8 host-test migration: module-owned leftovers moved into `Modules/*/tests/` (`FlickrPhotoUrlHelper`, `FlickrTokenHealth`, `StorageR2Config`, `FlickrAccountConnected` event, `XFlickrCrawlConfig`, `FlickrContactFrontendSupport`, `SpiderRuntimeConfig`, `ContactGraphRuntimeConfig`); architecture guard `ModuleOwnedHostTestPlacementTest`.
- A10 `ContactListQueryService::baseQuery()` shared starred/search builder for `paginateForConnection` and `listNsidsForConnection`; unit tests for starred+search and empty-starred cases.
- T1 money-path unit backfill: `PhotoDownloadExecutionService` missing-connection edge, `TransferBatchReconciler` all-success and null-batch edges, `FlickrCrawlService::crawlMany` global-pause edge.
- T1 waves 2–3 unit backfill: `ContactFullPassRunRepository` `ConcurrentRunGuard` contract (`hasActiveRun`, container binding); `ContactCatalogCountsService` and `ContactCrawlStateService` filter/owner matrices; Flickr `RateLimit\QueryService` / `UsageQueryService` empty and unhappy paths; `StorageQuotaQueryService` OneDrive token failure and empty-account snapshot.
- Module DAG allowlist test (`ModuleDependencyDirectionTest`: `ALLOWED` + `KNOWN_VIOLATIONS`) for audit A4; target matrix documented in `package-boundaries.md`.
- Maintenance backlog **N-16**: planned Flickr client layering (`jooservices/flickr` → Crawler `FlickrClientFactory` → peers); see `docs/05-maintenance/flickr-client-factory-layering.md`.
- Sticky app status footer (copyright + live Flickr API quota + Storage quota meters). Storage quota via `GET /api/v1/storage/quota` (OneDrive / Google Drive when available; Google Photos / R2 marked n/a).
- Operations console panels (**Overview** / **Crawl** / **Transfers**): activity metrics, session activity chart, MySQL/Redis/MongoDB dependency probes, databases panel with live 24h chart, per-account quota/pending, richer fetch-run detail, and Open Horizon link. Spider start/stop moved to Flickr account Expand actions.
- Dashboard **Databases** panel: MySQL/MongoDB reachability and data size, MySQL connection gauge, top MySQL tables, and a 24h size/connections chart (samples while Dashboard is open). Alerts for unreachable databases and high MySQL connection usage.

### Changed

- Catalog photos: larger table/grid thumbnails (Flickr `_n`), fewer grid columns, and click-to-open in-app photo detail modal (preview, memberships, download status, transfer actions) on Photos, Photoset show, and Favorites.
- Catalog Photos table (with a Flickr account context): bulk Download / Upload via selected `flickr_photo_ids`, same BulkActionBar pattern as Contacts.
- Contacts and Photos bulk selection supports Gmail-style **select all matching filters** (server resolves IDs from current search/starred or `owner_nsid`; no thousands of IDs from the browser).
- Sidebar Live jobs sit in sidebar **content** (above the footer); sidebar footer is an empty matching bottom rail beside the main status footer (copyright + Flickr/Storage quotas). Both footers use a fixed `h-12` rail so heights always align; status meters are single-line.
- Header **Pause** / **Resume** toggles global crawl pause; **Spider** opens a modal for `spider.enabled` + depth/caps with live crawl-load estimates. Enabling spider also clears global crawl pause. Expand Auto-expand preview includes account-scoped impact. Shared `Modal` (`Header` / `Body` / `Footer`, portals to `document.body`) keeps overlays viewport-centered. Flickr API quota meter lives in the sticky status footer (still also on Dashboard / Connections).
- Operations page path is `/operations` (`/crawl/operations` redirects). Overview metric tones match Dashboard (slate by default; accent only when active/alert).
- Operations live updates use short JSON polling only (no long-lived SSE). Dev `php artisan serve` is single-threaded; AppLayout SSE was freezing the whole site.
- Operations page no longer hosts Spider Start/Stop (read-only process console). Enable spider in Settings → General; control runs from Expand on Flickr accounts.
- Merged Flickr + Storage connect UX into **Connections** (`/connections`) with provider tabs (**Flickr** | **Storage**) only — credentials and connected accounts share one surface (no Accounts / Apps tabs). Top nav label is Connections. `/flickr/accounts` and Settings flickr/storage tabs redirect there.
- Settings → General uses `@jooservices/react-config` `ConfigPanel` (collapsible groups, search, expert/technical-key toggles, per-key descriptions); curated catalog includes `description`, `group_label`, `section`, and `tier`. **New** custom config sits in `PageShellIdentity` actions (not inside the panel).
- Header account menu (avatar initials + name/email) with Profile and Sign out; `/profile` Inertia page to update name, email, and password (current password required for email/password changes). Auth: `ProfileController` → `UpdateProfileRequest` → `UserService::updateProfile`.
- Documented Laravel module catalog (scope, purpose, features) in `docs/00-architecture/modules.md`; linked from docs hub, `AGENTS.md`, `class-purpose-and-module-map`, and related architecture docs.
- Frontend API wait affordances: shared `LoadingIndicator` / `PageLoading` / `BusyRegion`; `DataTable` accepts `busy` (page wait when empty, overlay when refetching). Catalog, Operations, Storage browse, and related panels use scoped waits instead of plain “Loading…” text.
- Test data convention: every Eloquent model has a factory; incidental values use Faker (`CreatesFlickrConnection`, `FlickrNsid`, `database/factories/Crawler/*`); assert from created models. Documented in `docs/04-development/testing.md`, `AGENTS.md`, and `testing-and-quality-gates`.
- Auth HTTP controllers use intent nouns: `LoginController`, `RegisterController`, `ForgotPasswordController`, `ResetPasswordController` (was Breeze-style `AuthenticatedSessionController` / `RegisteredUserController` / `PasswordResetLinkController` / `NewPasswordController`).
- Module Feature tests live under `Controllers/` / `Commands/` / `Jobs/` (API under `Controllers/Api/V1/`); module-owned host Feature tests migrated into `Modules/*/tests`; misfiled service/repo Feature tests moved to `Unit/Services` or `Unit/Repositories`.
- Operations standards parity: constructor DI + thin controllers (`CrawlOperationsService`, `OperationsStreamService`); dashboard uses `TransferCountsQueryService` and Flickr `listConnections()` (one-way `Operations → Flickr|Transfer|Spider`); co-located Operations Feature/Unit tests; architecture guard against reverse Operations imports.
- Module Artisan commands use `xflickr:<module>:<name>`: `xflickr:auth:reset-password` (was `xflickr:user:password`; class `ResetPasswordCommand`), `xflickr:contacts:full-pass-expand`, `xflickr:flickr:doctor`, `xflickr:flickr:audit-api`, `xflickr:storage:verify-google-photos`. Crawler package commands (`xflickr:dispatch`, `xflickr:prune`, package `xflickr:doctor`) unchanged.
- `ResetPasswordCommand` delegates to `UserService::resetPassword()` → `UserRepository` (Command → Service → Repository → Model).
- Auth session login/logout: `LoginRequest` / `LogoutRequest` → `AuthService` (dedicated FormRequest on logout; auth attempt/rate-limit moved out of FormRequest into the service).
- Prefer meaningful Eloquent model scopes in repositories (e.g. `User::scopeByEmail` used by `UserRepository::findByEmail`).
- Auth admin user seed moved to `Modules/Auth` (`AdminUserSeeder` via `UserService::ensureUser`); host `DatabaseSeeder` no longer auto-seeds — operators opt in (`dev.sh seed`, entrypoint, deploy, `module:seed Auth`).
- Auth module PHPUnit suites under `Modules/Auth/tests/{Unit,Feature}` (services + session controller + reset-password command); `phpunit.xml` discovers `Modules/*/tests/*`.
- Auth register / forgot-reset (token URL, no email) / `users.is_active` + `xflickr:auth:activate-user`; inactive users cannot sign in.
- JSON API hard-cut to **RESTful `/api/v1/*`** (crawl-runs, contact-graph, storage files/sync-runs, spider-runs/current, expand-previews, transfer retries). Frontend and Feature tests updated; imperative URI verb architecture test added.
- Introduced `nwidart/laravel-modules` with nine business modules (`Auth`, `Flickr`, `Contacts`, `Catalog`, `Spider`, `Transfer`, `Storage`, `Settings`, `Operations`).
- All SPA `/api/v1` JSON and web/Inertia routes register from Laravel modules; root `routes/web.php` and `routes/api.php` are stubs.
- Domain **services**, **repositories**, **FormRequests**, **models**, **jobs**, **listeners**, **commands**, and **enums** live under `Modules/*`. Shared host leftovers: `User`, crawler query repos, FormRequest base/traits, providers/middleware, and shared Support helpers.
- JSON API controllers extend `JOOservices\LaravelController\Http\Controllers\BaseApiController` and return Laravel `JsonResource` success payloads under `Modules/*/Http/Resources`.
- Frontend adopts JOO packages `@jooservices/react-layout`, `react-content`, `react-table`; adds `PageShell` barrel, `MetricCard`, `usePolledResource`, and `apiDelete`.
- `AppLayout` rebuilt on JOO `AppShell`; authenticated pages use `PageShell` (Dashboard, Flickr, Contacts, Catalog, Operations, Storage browse, Settings); contact graph toolbar extracted to `ContactGraphToolbar` macro.
- Architecture docs and agent skills updated for completed Modules migration (thin `app/` host; domain FormRequests/models/jobs/listeners/commands in modules).
- Frontend domain macros under `Components/macros/` (`ContactGraphShell`, crawl/expand bars, `AccountOpsCard`, `PhotoGridMacro`, `TransferBatchPanel`); unused `PageHeading` removed.
- Shared `FrontierExpansion` collaborator used by spider and full-pass planners.
- `StorageApiLogger` covers browse, thumbnail, and Google Photos connection-verifier HTTP calls (in addition to upload/delete/token refresh).
- Added Vitest coverage for `usePolledResource`, Playwright guest-shell smokes, and `FanOutTransferBatchJob` warning-path Feature tests.

### Fixed

- Crawl start (account/contact/API/graph expand) returns a flash or 422 when global crawl pause is active instead of a 500 from the crawler package; spider/full-pass `expandRun` no-ops while paused.
- Sidebar **Live** activity shows only in-flight (`running`) fetch/download/upload jobs; finished snapshot rows stay on Operations.
- `FlickrCrawlService::crawl()` reuses `crawlWithoutHealthCheck()` (DRY).
- `PhotoDownloadService::queuePendingPhotos()` batches completed-original lookups (no N+1).
- Contact graph visibility membership uses O(1) set lookups.
- Expand preview reads `saved_contacts_count` from full-pass planner (no Controller→Repository shortcut); deptrac Controllers may not depend on Repositories.
- `FanOutTransferBatchJob` logs warnings on missing connection/storage instead of silent returns.
- Google Photos upload streams file body instead of loading the whole file into memory.
- Contact photostream crawl could return zero photos when `safe_search=1` filtered the stream or when `people.getPhotos` was not OAuth-signed; crawler now omits visibility filters by default and signs subject-scoped API calls.
- Centralized crawl query builder (`FlickrCrawlQueryParams`) for all photo/photoset/gallery/favorites list jobs; `safe_search` and `privacy_filter` default to off (0).

### Added

- Flickr OAuth **token health** checks (`flickr.test.login`) after connect and before crawl; invalid tokens block crawls with a reconnect message.
- Settings Flickr account cards show **Token invalid — Reconnect** when the stored OAuth token cannot call the API (loaded asynchronously).
- `xflickr:flickr:audit-api` command probes Flickr API methods for a connection and optional contact NSID.
- `xflickr:flickr:doctor` validates each connected Flickr account token (profile + masked API key hint).

- Contacts graph: **force-directed** layout with small dots and thin edges; **hover popup**, pan/zoom, and **browser fullscreen** (Esc exits fullscreen first, then table).
- Contact graph **photos_count** visual encoding (dot size and darkness); starred contacts use amber color.
- Contact graph node panel: **desktop right sidebar** / **mobile bottom card** with photo strip (large single-column scroll, 10 per load), search, and reusable `ContactDetailPanel`.
- Contact graph **default direct contact cap** (Settings → General → Contact graph; initial limit and load-more step); header **+N contacts** / **Show all** controls.
- Local contact **Star** and **Note** annotations (table and graph); **Starred only** table filter.
- API: `GET /contacts/graph`, `GET /contacts/graph/delta`, `POST /contacts/graph/{nsid}/expand`, `PATCH /contacts/{nsid}/annotation`.

- Catalog **Photos** grid: `PhotoGridTile` overlay slots, per-photo download/upload actions, downloaded badge with view link, and infinite scroll; table adds **Downloaded** column.
- `GET /api/stored-files/{uuid}` — stream locally downloaded originals inline for authenticated users.
- Catalog photos API includes `download_status`, `stored_file_uuid`, and `stored_file_view_url`.
- Photoset detail page (`/photosets/{id}`): metadata header and member photo grid; list thumbnails link to detail only.
- Catalog photos API accepts `photoset_id` to list photos in a set; `GET /api/flickr/catalog/photosets/{id}` returns set metadata.

- `bash scripts/dev.sh reload` syncs npm dependencies in the app container when `package-lock.json` changes before building assets. choose **Docker** or **host** (`DEPLOY_TARGET` in `.env`) at `bash scripts/deploy.sh install`.
- Host deploy for Ubuntu 22.04: auto-install PHP 8.5+, Composer, Node, nginx, supervisor; nginx → `artisan serve`; supervisor for Horizon and scheduler.
- Shared release finalize pipeline: migrate, cache clear/recache, `horizon:terminate`, service restart, and verify-before-complete for all install/update paths.
- `bash scripts/test.sh gate:deploy-scripts` — deploy shell tests in Ubuntu 22.04 Docker harness (CI job on `ubuntu-22.04`).

### Changed

- Contact crawl UI distinguishes **Fetched · 0 in catalog** (warning) from a successful refetch; catalog **In API** shows `0` after an empty completed crawl instead of `—`.
- Settings no longer blocks page load on synchronous Flickr token health probes (badge loads via API).
- Flash success/error messages render as dismissible toasts instead of a full-width banner row.
- App layout sidebar stretches full viewport height below the header (catalog nav scrolls; live activity pinned at bottom).
- Contact graph rendering moved to **canvas** with ref-based pan/zoom for smoother interaction at scale.
- Settings Flickr tab: merged app and account cards into one **Flickr connections** list (Storage-style); account cards show short `public_id`, full UUID in details, and account-scoped **Crawl** actions (same menu as Flickr accounts index).
- Crawl action dropdown: optional gray header showing target display name and NSID (`Actions for` + title + monospace NSID).
- **Expand** actions on Settings Flickr account cards: **Auto-expand (Spider)** and **Full contact pass** with confirmation modals and preview API (`full_pass.max_depth` runtime config, default 1).
- Settings getting-started banner: dismissible with remembered preference (`localStorage`).
- Production deploy refactor: `app-init.sh`, `app-release.sh`, `finalize.sh`, mode-specific bootstrap/update/verify modules.

### Added

- `bash scripts/deploy.sh finish` — complete production install from an existing `.env` without re-running the wizard (installs Composer/npm assets, then starts the stack).

### Fixed

- Production deploy installs `jooservices/xflickr-crawler` from **Packagist** (removed default path repository to `../XFlickrCrawler`).
- Production bootstrap runs `composer install` and `npm run build` **before** starting containers (avoids crash loops in the app entrypoint).
- Removed `HOSTNAME` Compose warning for Horizon in `docker-compose.prod.yml`.
- Git “dubious ownership” warning in production containers (`safe.directory` for `/var/www/html`).
- Post-deploy worker verification uses `/proc/1/cmdline` instead of `ps` (not installed in the production PHP image).

### Added

### Fixed

- Production deploy wizard: Redis/Mongo connectivity checks no longer fail when Docker pulls images or `redis-cli` prints warnings (accepts `PONG` on last output line).
- Production deploy wizard: prefer host-native `mysql`/`redis-cli`/`mongosh` for connectivity tests; Docker fallback pulls images quietly.
- Production deploy preflight: detect Docker daemon permission errors with actionable guidance.
- `APP_URL` without `http://` or `https://` is normalized automatically during install.

### Added

- Production Docker stack (`docker-compose.prod.yml`, project `xflickr-prod`) with nginx, scalable Horizon replicas, and external MySQL/Redis/MongoDB.
- `bash scripts/deploy.sh` production wizard: connectivity validation loop, optional HTTPS, install/update/configure commands.
- Post-deploy verification (`deploy.sh verify`): connections, web readiness, doctor, and worker checks before install completes.
- Safe release updates for existing deployments: detect running stack, prompt before update, never wipe data.
- UI-configurable Horizon worker counts (Settings → General → Queue) with `horizon:terminate` on save.
- `.env.prod.example` and `docs/03-operations/production-deploy.md`.
- Docker dev/test parity (XCrawlerII model): `docker-compose.dev.yml`, `scripts/test.sh` quality gates, `scripts/dev.sh` operator commands, named volumes (`xflickr-dev-*` / `xflickr-test-*`), dedicated frontend container, `RefreshDatabaseGuard`, `operator-dev-docker` skill, git hooks.
- `.env.test.example` for test stack port overrides.
- Admin credential hardening: `ADMIN_PASSWORD` env, random Docker bootstrap password, `xflickr:user:password` command.
- Real gitleaks secret scanning in CI (replaces no-op workflow).
- Spider mode (opt-in): event-driven `SpiderPlannerService`, frontier tables, `xflickr:spider:expand`, Operations UI controls; requires crawler ^1.3.0.
- `jooservices/dto` at call sites: `DownloadCandidateDto` (photo size resolver), `OAuthAppConfigDto` (storage OAuth).
- `xflickr:doctor` health command.
- Vitest smoke tests for frontend components; Playwright e2e scaffold.
- Operations live feed via SSE (`/api/operations/stream`) with polling fallback.
- Per-item transfer retry API and Operations failed-item drill-down.
- Catalog photo grid view; Settings onboarding wizard.
- Maintenance docs: `BACKLOG.md`, `constraints.md`, `DISPOSITION.md`, spider user guide, crawler spider extensions spec.

### Changed

- `scripts/deploy.sh` is now production-only (no longer aliases `dev.sh reload`). Local reload: `bash scripts/dev.sh reload`.

- Renamed `docker-compose.yml` → `docker-compose.dev.yml` (project `xflickr-dev`). Existing `xflickr_*` volumes do not auto-migrate — see [Docker stacks](docs/03-operations/docker-stacks.md#volume-migration).
- Bumped `jooservices/xflickr-crawler` to **^1.3.0** for event-driven spider BFS (subject contacts, `ContactsCrawlCompleted`).
- `AdminUserSeeder` creates admin only on first run — no password overwrite on container restart.
- Operations SSE stream ends after 60s (EventSource reconnects); dev `app` service sets `PHP_CLI_SERVER_WORKERS=4`.
- Pre-commit hook runs lint only (`gate:lint`); full gate remains on pre-push and CI.
- `UploadPhotoJob` uses `WithoutOverlapping` per storage account; defers via `retryUntil` without a fixed tries cap.
- `DownloadCandidateDto` and `OAuthAppConfigDto` wired at resolver and OAuth call sites.
- AI agents must use `bash scripts/test.sh` only — never `scripts/dev.sh` or `docker exec xflickr-dev-*`.
- Feature tests use `Tests\Concerns\SafeRefreshDatabase` instead of raw `RefreshDatabase`.
- Transfer fan-out uses per-chunk set-based exclusion (bounded queries); upload jobs scale `retryUntil` with batch size.
- Dashboard snapshot uses aggregate queries and 15s cache; dashboard poll interval 15s.
- Retry/deferral semantics: file-not-ready upload deferrals are not capped at three attempts.
- OAuth failure logging in storage and Flickr auth controllers.
- `TransferType` enum replaces string literals in fan-out jobs.

### Security

- Production refuses default `password` for admin seeder and password command.

## [1.1.0] - 2026-07-06

### Added

- Session authentication with default admin account (`admin@local` / `password`); all web and API routes require login.
- Structured audit logging for settings changes via `jooservices/laravel-logging`.
- Domain events for Flickr and storage account connect/disconnect via `jooservices/laravel-events`.
- Transfer pipeline v2: atomic batch reconcile with row locks, `CompletedWithErrors` status, chunked async fan-out via `FanOutTransferBatchJob`, and resilient job retry settings.
- Dynamic media file extensions from Flickr URLs (`FlickrPhotoUrlHelper::resolveExtension()`).
- Dto-style CI pipeline: security audit, lint matrix (Pint, PHPStan level 6, Deptrac, AI instructions), frontend build, tests with 60% coverage gate.
- GitHub workflows aligned with `jooservices/dto`: release, semantic-pr, pr-labeler, scorecard, secret-scanning.
- Branch model documentation (`docs/05-maintenance/github-branch-protection.md`) — `develop` / `master` with dto-style ruleset.
- Direct dependency on `jooservices/dto ^1.0`.
- `composer test:docker:coverage` script; pcov in Dockerfile for coverage in test stack.
- Auth feature tests and `IgnoresAuthentication` test helper.

### Changed

- PHPStan raised to level 6 with baseline (166 entries).
- Bumped `jooservices/laravel-events ^1.4`, `jooservices/laravel-logging ^1.1`, `jooservices/laravel-repository ^1.3`.
- Storage browse API returns generic client errors; internal details logged server-side.
- Flickr OAuth callback failures redirect with flash error instead of raw 500.
- Horizon dashboard requires authenticated session.
- Docker entrypoint seeds admin user on first `artisan serve` start.

### Removed

- Deprecated `DownloadContactsPhotosJob` and `UploadContactsPhotosJob` (use services directly).
- Root-level ephemeral audit outputs moved to `docs/05-maintenance/release-reviews/2026-07-06/`.

### Security

- Horizon dashboard gated behind auth middleware.
- Storage browse thumbnail errors no longer leak internal validation messages.

[1.1.0]: https://github.com/jooservices/XFlickr/releases/tag/v1.1.0

## [1.0.0] - 2026-06-23

### Added

- Self-hosted Flickr archive manager with Laravel 12, React 19, and Inertia 3.
- Flickr account management: OAuth 1.0a connect/disconnect, multiple accounts, active-account switching.
- Manual crawl operations for contacts, photos, photosets, galleries, and favorites via `jooservices/xflickr-crawler`.
- Contact list with bulk crawl, download, and upload actions per connected account.
- Catalog browsers for photos, photosets, galleries, and favorites with search and filters.
- Local photo downloads with deduplication (`stored_files` tracking).
- Cloud upload to Google Photos, Google Drive, OneDrive, and Cloudflare R2.
- Storage browser UI for each connected storage provider (browse, sync, delete).
- Live dashboard with crawl progress, catalog stats, transfer health, and API quota meters.
- Operations page for crawl runs and download/upload batch monitoring.
- Settings UI for Flickr app credentials, storage OAuth apps, runtime config, and global crawl pause.
- Docker local stack (`docker-compose.yml`) and isolated test stack (`docker-compose.test.yml`).
- Documentation hub (`docs/`), AI skills (`ai/skills/`), and agent governance (`AGENTS.md`, `CLAUDE.md`).
- GitHub CI workflow running `composer test:docker`, `npm run typecheck`, and `composer instructions:verify`.
- Full documentation hub (`docs/00`–`05`), governance files (LICENSE, CONTRIBUTING, SECURITY, CODE_OF_CONDUCT).
- 20 canonical AI skills (`ai/skills/`), multi-LLM workflow scripts, and `composer instructions:verify`.

[1.0.0]: https://github.com/jooservices/XFlickr/releases/tag/v1.0.0
