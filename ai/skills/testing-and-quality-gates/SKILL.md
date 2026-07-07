---
name: testing-and-quality-gates
description: PHPUnit test stack and mandatory quality gates for XFlickr.
---

# Skill: testing-and-quality-gates

## Purpose

Define mandatory test and quality commands before PR.

## Mandatory gates (AI single entry)

```bash
bash scripts/test.sh gate          # before commit
bash scripts/test.sh gate:ci       # before push
```

## Gate breakdown

```bash
bash scripts/test.sh gate:lint     # Pint, PHPStan, Deptrac, instructions:verify, npm typecheck/lint
bash scripts/test.sh gate:test     # gate:lint + PHPUnit (Docker) + Vitest
```

## Forbidden

```bash
scripts/dev.sh
docker exec xflickr-dev-* 
docker compose exec app php artisan test
composer test:docker              # use scripts/test.sh gate:test
php artisan test                    # when .env points at dev
```

## Filtered tests

```bash
bash scripts/test.sh gate:test -- # not supported; use:
./scripts/test-docker.sh --filter=StorageBrowseTest
```

## Writing tests

- Feature tests: `tests/Feature/` — use `Tests\Concerns\SafeRefreshDatabase`, not raw `RefreshDatabase`
- Unit tests: `tests/Unit/` for isolated logic
- Mock Flickr and cloud storage APIs — no real external calls
- Use test stack only (`docker-compose.test.yml` via `scripts/test.sh`)

## Related skills

- `xflickr-docker-testing`
- `operator-dev-docker`
- `repo-quality-foundation`
