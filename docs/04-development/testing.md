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

CI enforces a **95%** statement coverage minimum via `bash scripts/test.sh gate:ci`.

PHPUnit suites mirror XCrawlerII: **one testsuite per module** (`Modules/{Name}/tests` containing `Unit/` + `Feature/`), plus a **Host** suite for `tests/Unit` and `tests/Feature`.

## Related

- Skill: `xflickr-docker-testing`, `operator-dev-docker`, `testing-and-quality-gates`
- [Docker stacks](../03-operations/docker-stacks.md)
