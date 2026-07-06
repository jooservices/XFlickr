# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

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
