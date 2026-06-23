# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

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
