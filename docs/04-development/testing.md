# Testing

XFlickr uses PHPUnit. All tests run in the **isolated Docker test stack only**.

## Run tests (AI + CI)

```bash
bash scripts/test.sh gate:test
bash scripts/test.sh gate:ci       # before push — includes coverage threshold
```

Filtered:

```bash
./scripts/test-docker.sh --filter=AuthenticationTest
```

## Forbidden

**Never** run tests on the local dev stack:

```bash
# FORBIDDEN — wipes dev MySQL via RefreshDatabase
docker exec xflickr-dev-app-1 php artisan test
scripts/dev.sh
php artisan test   # when pointed at dev .env
```

Use `Tests\Concerns\SafeRefreshDatabase` in feature tests (not raw `Illuminate\Foundation\Testing\RefreshDatabase`).

## Test stack

- Compose file: `docker-compose.test.yml` (project `xflickr-test`)
- MySQL: SQLite `:memory:`
- MongoDB: `xflickr_test`
- Entrypoint: `docker/test-entrypoint.sh`

## Writing tests

- Feature tests in `tests/Feature/` for HTTP, jobs, and integration flows.
- Unit tests in `tests/Unit/` for isolated logic and DTO hydration.
- Shared JSON fixtures: `tests/Fixtures/` loaded via `TestCase::loadFixture()`.
- Use `Event::fake()` for domain event assertions.
- Mock external APIs (Flickr, cloud storage) — do not call real services in tests.

## Frontend

```bash
npm run typecheck
npm run test          # Vitest component smoke tests
npm run test:e2e      # Playwright (requires dev stack + built assets)
```

## Coverage ratchet

CI enforces a **60%** statement coverage minimum via `bash scripts/test.sh gate:ci`.

## Related

- Skill: `xflickr-docker-testing`, `operator-dev-docker`, `testing-and-quality-gates`
- [Docker stacks](../03-operations/docker-stacks.md)
