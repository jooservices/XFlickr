# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- Production Docker stack (`docker-compose.prod.yml`, project `xflickr-prod`) with nginx, scalable Horizon replicas, and external MySQL/Redis/MongoDB.
- `bash scripts/deploy.sh` production wizard: connectivity validation loop, optional HTTPS, install/update/configure commands.
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
