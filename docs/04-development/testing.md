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

Module-owned tests live under `Modules/{Name}/tests`. Host `tests/` keeps shared/cross-cutting suites only.

**Feature** (HTTP, Artisan, jobs — use `SafeRefreshDatabase`):

```
Modules/{M}/tests/Feature/Controllers/<ControllerClass>Test.php
Modules/{M}/tests/Feature/Controllers/Api/V1/<ControllerClass>Test.php   # API name collisions
Modules/{M}/tests/Feature/Commands/<CommandClass>Test.php
Modules/{M}/tests/Feature/Jobs/<JobClass>Test.php
```

**Unit** (services, repositories, architecture):

```
Modules/{M}/tests/Unit/Services/<ServiceClass>Test.php
Modules/{M}/tests/Unit/Repositories/<RepositoryClass>Test.php
tests/Unit/…   # host-shared (Architecture, Http/Requests, Support, Repositories)
```

Auth HTTP controllers use intent nouns: `LoginController`, `RegisterController`, `ForgotPasswordController`, `ResetPasswordController`.

### Test data (factories + Faker)

**Non-negotiable for new/changed tests:**

1. **Every Eloquent model** (host `app/Models`, `Modules/*/app/Models`) **must have a factory** (`HasFactory` + `Modules/{M}/database/factories/` or `database/factories/`).
2. **Incidental attributes use Faker** — prefer `Model::factory()->create()` / `fake()` / `FlickrNsid::fake()`. Do not hardcode NSIDs, emails, usernames, or paths unless the literal *is* the scenario under test (e.g. forbidden production password, seeder admin email from config).
3. **Assert from the model** — `$connection->connection_key`, `$user->email`, never re-type the same hardcoded string in expectations.
4. **Flickr connections** — use `CreatesFlickrConnection` (Faker defaults). Pass attribute overrides only when the test needs a specific value.
5. **Crawler package models** (no vendor `HasFactory`) — use app-side factories under `database/factories/Crawler/` (`ConnectionFactory`, `ContactFactory`, `PhotoFactory`, …) or `CreatesFlickrConnection` for connections.
6. **Scenario graphs** (A knows B) — generate each NSID with Faker into variables; wire relationships with those variables.

```php
// Prefer
$connection = $this->createFlickrConnection();
$response->assertInertia(fn ($page) => $page
    ->where('accounts.0.nsid', $connection->connection_key));

// Avoid
$connection = $this->createFlickrConnection(['connection_key' => 'me@N01']);
$response->assertInertia(fn ($page) => $page->where('accounts.0.nsid', 'me@N01'));
```

- Shared JSON fixtures: `tests/Fixtures/` loaded via host `TestCase::loadFixture()`.
- Module fixtures: each module TestCase exposes `loadModuleFixture()` / `loadJsonFixture()` reading `Modules/{Name}/tests/Fixtures/` (named to avoid clashing with host `loadFixture()` which returns decoded JSON arrays). Prefer fixture files for non-trivial 3rd-party payloads (≥ ~15 lines or nested envelopes the parser exercises). Tiny 3–4-key stubs may stay inline.
- Mock **only** 3rd-party wire (Http::fake, Guzzle MockHandler into SDKs, FakeFlickrTransport, Socialite). Do not Mockery own services/repositories — use real collaborators or sanctioned fakes (in-memory Flysystem via Storage `bindInMemoryDisk()`, crawler `StubFlickrPermitAcquirer`).
- Use `Event::fake()` for domain event assertions.
- Mock external APIs (Flickr, cloud storage) — do not call real services in tests.

## Frontend

```bash
npm run typecheck
npm run test          # Vitest component smoke tests
npm run test:coverage # Vitest with lib/ + hooks/ coverage thresholds
npm run test:e2e      # Playwright (requires dev stack + built assets)
```

Frontend coverage is enforced on a curated pure-logic allowlist under `resources/js/lib/**` and `resources/js/hooks/**` (see `vitest.config.ts` `coverage.include`, ≥90% lines via `npm run test:coverage`). Integration-heavy hooks (storage browse, operations stream, graph canvas) and Components/Pages sit outside that floor — covered by targeted component tests and Playwright smokes. Expand the allowlist as more unit tests land.

### Playwright smoke (T2)

Browser E2E targets the **operator dev stack** (`http://localhost:8082` by default), not the isolated PHPUnit test stack.

Prep demo data (operator — factory seeder is idempotent; wipe with `bash scripts/dev.sh refresh` first):

```bash
bash scripts/dev.sh seed --demo
```

Run smokes (set `ADMIN_PASSWORD` from `.env` when not using the local/testing default):

```bash
npm run build
ADMIN_PASSWORD=your-password npm run test:e2e
# optional: PLAYWRIGHT_BASE_URL=http://localhost:8082
```

Printed checklist: `bash scripts/test.sh e2e:prep` (does not touch the docker stacks).

Specs live under `tests/e2e/` (`contacts`, `catalog`, `transfers`, `storage`) and reuse `tests/e2e/helpers/auth.ts` for admin login.

## Coverage ratchet

CI enforces a **95%** statement coverage minimum via `bash scripts/test.sh gate:ci`.

PHPUnit suites mirror XCrawlerII: **one testsuite per module** (`Modules/{Name}/tests` containing `Unit/` + `Feature/`), plus a **Host** suite for `tests/Unit` and `tests/Feature`.

## Related

- Skill: `xflickr-docker-testing`, `operator-dev-docker`, `testing-and-quality-gates`
- [Docker stacks](../03-operations/docker-stacks.md)
