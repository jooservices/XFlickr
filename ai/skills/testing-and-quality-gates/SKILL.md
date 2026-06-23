---
name: testing-and-quality-gates
description: PHPUnit test stack and mandatory quality gates for XFlickr.
---

# Skill: testing-and-quality-gates

## Purpose

Define mandatory test and quality commands before PR.

## Mandatory gate

```bash
composer test:docker
```

Frontend (when changed):

```bash
npm run typecheck
```

AI instruction sync:

```bash
composer instructions:verify
```

## Forbidden

```bash
docker compose exec app php artisan test   # wipes dev MySQL
php artisan test                           # when .env points at dev
```

## Filtered tests

```bash
./scripts/test-docker.sh --filter=StorageBrowseTest
```

## Writing tests

- Feature tests: `tests/Feature/` for HTTP, jobs, integrations
- Unit tests: `tests/Unit/` for isolated logic
- Mock Flickr and cloud storage APIs — no real external calls
- Use test stack only (`docker-compose.test.yml`)

## Related skills

- `xflickr-docker-testing`
- `repo-quality-foundation`
