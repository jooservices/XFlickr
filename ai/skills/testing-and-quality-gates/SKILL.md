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
./scripts/test-docker.sh --filter=LoginControllerTest
```

## Writing tests

- Feature: `Modules/{M}/tests/Feature/{Controllers,Commands,Jobs}/` — Controllers mirror app path (`Api/V1/` when needed). Use `SafeRefreshDatabase`, not raw `RefreshDatabase`.
- Unit: `Modules/{M}/tests/Unit/{Services,Repositories}/` and host `tests/Unit/` for shared architecture/support.
- Host `tests/Feature/` only for cross-module / non-module suites.
- Auth HTTP: `LoginController`, `RegisterController`, `ForgotPasswordController`, `ResetPasswordController`; Auth Feature examples under `Modules/Auth/tests/Feature/Controllers/*` and `…/Commands/*`.
- **Factories + Faker (required):** every app/module Eloquent model has a factory. Incidental test data uses `Model::factory()`, `fake()`, or `Tests\Support\FlickrNsid::fake()` — not hardcoded NSIDs/emails. Assert from created models (`$connection->connection_key`). Use `CreatesFlickrConnection` for Flickr connections. Crawler models: `database/factories/Crawler/*`. Hardcode only when the literal is the scenario (admin seeder email, forbidden production password).
- Mock Flickr and cloud storage APIs — no real external calls
- Use test stack only (`docker-compose.test.yml` via `scripts/test.sh`)
- Prefer happy / unhappy / weird / security cases for every public Service method (rate limits, production password bans, idempotent seeders, token redaction)
- PHPUnit: one **testsuite per module** (`Modules/{Name}/tests` with `Unit/` + `Feature/`); Host suite owns `tests/Unit` + `tests/Feature`
- Coverage floor: **95%** statement coverage (`TEST_COVERAGE_MIN`, `gate:ci`)
- Lint: zero ESLint warnings (`npm run lint --max-warnings 0`); Pint/PHPStan/Deptrac must be clean
- Filter example: `./scripts/test-docker.sh --filter=LoginControllerTest`

## Related skills

- `xflickr-docker-testing`
- `operator-dev-docker`
- `repo-quality-foundation`
