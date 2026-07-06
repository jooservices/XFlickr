# XFlickr — Audit Report (Claude, 2026-07-06)

Scope: audit + improvement + feature suggestions only. No code changed.
Reference standard: `jooservices/dto`. Stack: Laravel 12 app (PHP 8.5, Inertia 3, React 19).

---

## 1. Executive summary

**Overall status: Needs fixes** — the codebase is well-structured and unusually well-governed (AGENTS/skills/docs), but it is not ready for the planned major release: it has **no authentication**, Flickr tokens at rest are **unencrypted** (crawler package), CI runs **no lint/static-analysis gates**, and the queue pipeline has retry/race defects that will surface at spider-scale volumes.

**Best positioning:** Self-hosted **Laravel application** (not a package) for Flickr archiving — internal JOOservices product, publishable as an open-source self-hosted app. README positioning matches the code accurately.

**Standards alignment with jooservices/dto: Partial** — docs/AI governance are at or above the dto standard; CI gates, release workflow, branch model, scanning, coverage, and (ironically) `jooservices/dto` usage itself are missing.

**Top 5 risks**

1. No authentication or authorization on any route — every destructive action (delete remote storage files, disconnect accounts, overwrite credentials) is open to anyone who can reach the host (`routes/web.php`, `routes/api.php`).
2. Flickr OAuth tokens stored plaintext in MySQL (`xflickr-crawler` migration `...000100_create_xflickr_tables.php:16`, no encrypted cast on `Connection`), inconsistent with encrypted `StorageAccount.credentials`.
3. CI has no Pint / PHPStan / Deptrac / coverage gate — `composer lint` exists but `.github/workflows/ci.yml` never calls it; quality can silently regress.
4. Transfer pipeline correctness at scale: lock-deferral consumes retry attempts, reconciler read-then-write race, per-photo dispatch loops in the web request.
5. README overclaims: "structured audit logging" and "domain event sourcing" packages are installed but **never used** by app code (0 references in `app/`).

**Top 5 quick wins**

1. Add `composer lint` (Pint+PHPStan+Deptrac) as a CI job — one workflow step.
2. Sanitize API error responses (stop returning `$e->getMessage()` to clients) and log the swallowed `catch (\Throwable)` blocks.
3. Fix the hardcoded `.jpg` extension for downloads/uploads (breaks PNG/GIF/video originals).
4. Move the six `audit*.md` files out of the repo root (docs/05-maintenance/ or gitignore).
5. Raise PHPStan from level 1 toward larastan level 6+ (baseline has only 1 entry — the code is already close).

**Top 5 feature opportunities**

1. **Spider mode** (the requested major feature): BFS auto-crawl over contacts-of-contacts with configurable depth/limits, built on the existing crawl-target queue + `xflickr:dispatch`.
2. Single-user authentication (login or app-token) — prerequisite for exposing the app beyond localhost.
3. `xflickr:doctor` command validating MySQL/Redis/MongoDB/Flickr credentials/storage tokens.
4. Real-time operations UX: replace 5-second polling with SSE or Laravel Reverb; failed-item drill-down with retry button.
5. Extract the cloud-storage driver layer into `jooservices/laravel-cloud-storage` for ecosystem reuse.

---

## 2. Standards alignment vs jooservices/dto

| Item | dto standard | XFlickr | Verdict / recommendation |
|---|---|---|---|
| Branch model | develop / master / release/<v> | Only `develop` exists (plus dependabot branches); tag `v1.0.0` | **Missing** — create `master`, adopt release branches for the planned major release |
| Branch protection | Protected default + required checks | Not verifiable locally; CI targets `main, master, develop` (`ci.yml:4-6`) — `main` listed but doesn't exist | Align workflow triggers with the real branch model |
| CI workflows | Lint + static analysis + tests + coverage | One `ci.yml`: AI-sync verify, typecheck, `npm run build`, `composer test:docker`. **No Pint, no PHPStan, no Deptrac, no coverage** | **Add lint job**; add coverage report + gate |
| Release workflow | Tag-driven release workflow | Not found | Add release workflow (changelog extraction + GitHub release) |
| Semantic PR title | Enforced via workflow | Not found | Add `amannn/action-semantic-pull-request` or equivalent |
| PR labeler | Present | Not found (`.github/labeler.yml` absent) | Add |
| Secret scanning | gitleaks/secret-scanning workflow | Not found | Add gitleaks workflow (especially important: OAuth-heavy app) |
| OpenSSF Scorecard | Present | Not found | Add |
| Coverage gate | Enforced % | None; phpunit has `<source>` but CI never produces coverage | Add (start ~60%, ratchet) |
| Packagist readiness | n/a (this is an app, `"type": "project"`) | Correctly not on Packagist | OK |
| Badges | CI/coverage/version badges | **None** in README | Add CI badge at minimum |
| Changelog / SemVer | Keep a Changelog + SemVer | `CHANGELOG.md` follows Keep a Changelog, `[Unreleased]` + `[1.0.0]` | **Good** |
| License | MIT | MIT present | Good |
| Security policy | SECURITY.md | Present, concrete | Good |
| Contributing | CONTRIBUTING.md | Present (+ docs/04-development/07-contributing.md) | Good |
| Docs structure | docs hub | `docs/00–05` with ownership rules — **exceeds** dto | Good |
| AGENTS/CLAUDE/AI skills | Present | AGENTS.md, CLAUDE.md, 20 skills ×3 adapters (`ai/`, `.github/skills/`, `.cursor/`) + sync verifier in CI | **Exceeds standard**; sync tool keeps duplication honest |
| PR/issue templates | Present | Present | Good |
| Dependabot | Present | `.github/dependabot.yml` present, PRs flowing | Good |
| PHPStan level | High/max | **Level 1** (`phpstan.neon`), baseline has 1 entry | Raise to ≥6; the small baseline says the code can take it |
| Quality commands | composer scripts | `lint`, `lint:pint`, `lint:phpstan`, `lint:deptrac`, `test:docker` — well done | Good, just not wired to CI |
| jooservices/dto usage | The standard itself | **Not a dependency** | Adopt for typed payloads (see §4) |

**Repo hygiene:** `audit.md`, `audit_codex.md`, `audit_cursor.md`, `audit_final.md`, `audit_grok.md`, `audit_sonnet.md` sit untracked in the root and are not gitignored (`.gitignore` covers `plan.md` but not `audit*.md`). Move to `docs/05-maintenance/` (an audit already lives there: `codebase-audit-2026-06.md`) or ignore.

---

## 3. Architecture and code quality

**Structure quality: good.** The mandated flow `Controller → FormRequest → Service → Repository → Model` is followed consistently; Deptrac layers enforce it (`deptrac.yaml`). No god classes — largest file is 237 lines (`StorageBrowseSyncService`). 184 app classes, small and single-purpose.

**Dependency direction:** clean. Controllers are `final`, constructor-injected services, repositories extend `Jooservices\LaravelRepository\Repositories\EloquentRepository` (e.g. `app/Repositories/StoredFileRepository.php:15`). Crawler-owned tables are accessed read-only through dedicated `app/Repositories/Crawler/*QueryRepository` classes — a well-executed package boundary.

**SOLID/DRY/KISS/YAGNI:**

- SRP largely respected; some over-splitting in the rate-limit area: `FlickrRateLimitPresenter`, `FlickrRateLimitQueryService`, `FlickrRateLimitUsageQueryService` are three small classes around one concept — a candidate to merge (user's "reduce 1-time-use classes" goal).
- Duplication: `PhotoDownloadService::queueFromInput` and `PhotoUploadService::queueFromInput` share the same input-routing shape (photo-id / contact-list / single-contact branches); `apiKeyHint()` (`FlickrAppProfileService.php`) and `clientIdHint()` (`StorageAppProfileService.php`) are the same masking function; the three browse services (`GooglePhotosBrowseService` 198 ln, `OneDriveBrowseService` 193 ln, `R2BrowseService` 189 ln) repeat pagination/normalization scaffolding. Extract shared helpers rather than new layers.
- Dead code: `app/Models/User.php` + `0001_01_01_000000_create_users_table.php` are unused (no auth, zero references). Either delete or make them real by adding auth.
- Dead params: `StorageUploadService::diskFor()` and `objectKey()` accept `$credentials` they don't use (`StorageUploadService.php:79-97`).
- Deprecated-but-kept: `DownloadContactsPhotosJob` / `UploadContactsPhotosJob` correctly annotated `@deprecated ... kept for in-flight queue jobs` — remove in the next major.
- Code smell: `FlickrOAuthService::complete()` registers a connection under key `'unknown'` then disconnects and re-registers when `userNsid` arrives (`FlickrOAuthService.php:57-76`) — the workaround belongs in the `jooservices/flickr` SDK.

**Package/app boundary:** correct overall. One leak in reverse: `App\Support\FlickrPhotoUrlHelper` (159 ln) implements `flickr.photos.getSizes` parsing/candidate selection — generic Flickr SDK logic that should live in `jooservices/flickr`.

---

## 4. DTO and data-contract report

**Good DTO usage**

- SDK boundary: `jooservices/flickr` DTOs consumed properly (`AccessTokenData`, `FlickrConfig` in `FlickrOAuthService`).
- `App\Support\Storage\StorageR2Config` — hand-rolled readonly config DTO with validation and unit tests (`tests/Unit/StorageR2ConfigTest.php`). Model for others.
- `App\Services\Transfer\TransferQueueResult` — typed result object instead of ad-hoc arrays.
- FormRequest boundary is exemplary: one request class per action (`app/Http/Requests/**`), typed accessor methods (`$request->driver()`, `->accountId()`, `->returnUrl()`), validation stays in requests, services receive scalars/models. FormRequest is not replaced by DTOs — matches the required Laravel boundary.

**Raw arrays that should become DTOs** (adopt `jooservices/dto`, which is currently not even a dependency):

- `array{url: string, variant: string}` download candidate (`FlickrPhotoSizeResolver::resolve()`, `FlickrPhotoUrlHelper`).
- `array<string,string>` OAuth app config (`StorageOAuthService::appConfig()`, `FlickrAppProfileService::flickrClientConfig()`) — with camelCase/snake_case fallback reading (`client_id`/`clientId`) that a hydrating DTO would eliminate.
- Storage upload result `array{id, path, etag}` (`StorageUploadService::uploadStream()`).
- Dashboard snapshot payload (`DashboardService::snapshot()` returns a deep `array<string,mixed>`).
- Token payload arrays passed to `FlickrService::connections()->register()`.

**Raw arrays fine to keep raw:** Inertia page props assembled by presenters (presentation edge), repository aggregate maps (`ContactCatalogCountsService` count maps), config file arrays.

**Naming/consistency:** presenters (`ConnectionPresenter`, `ContactPresenter`, `StorageAccountPresenter`, catalog presenters) act as the response-transformer layer instead of Laravel Resources — a deliberate, consistent choice for an Inertia app; acceptable, keep it uniform. No enum issues: backed string enums used consistently (`StorageDriver`, `TransferItemStatus`, etc.), though status comparisons via `->value` string equality (`$storedFile->status === StoredFileStatus::Completed->value`) would be cleaner with enum casts on models.

---

## 5. Findings table

| ID | Sev | Category | Evidence | Problem | Why it matters | Recommended fix | BC | Tests | Docs |
|---|---|---|---|---|---|---|---|---|---|
| F-01 | **Critical** | Security | `routes/web.php`, `routes/api.php` — zero `auth` middleware; `app/Models/User.php` unused | No authentication/authorization anywhere | Anyone reaching the host can delete cloud files (`POST /api/storage/{provider}/delete`), disconnect accounts, replace app credentials, trigger crawls | Add single-user auth (login or signed app token) + `auth` middleware group; document reverse-proxy auth as interim | No (additive) | Feature tests asserting 401/redirect on every route group | README + getting-started |
| F-02 | **High** | Security | `vendor/jooservices/xflickr-crawler/.../2026_06_21_000100_create_xflickr_tables.php:16` (`text token_payload`); `Connection` model has no encrypted cast; contrast `app/Models/StorageAccount.php:23` (`encrypted:array`) | Flickr OAuth token + secret plaintext in MySQL | DB dump/backup leaks account takeover credentials; inconsistent with storage credentials | Fix in `xflickr-crawler`: `encrypted` cast + migration to re-encrypt; app upgrade note | Maybe (data migration) | Package test: round-trip encryption | SECURITY.md note |
| F-03 | High | Correctness | `app/Services/Flickr/PhotoDownloadExecutionService.php:132-137` (`finalPathFor` → `.jpg`); `PhotoUploadExecutionService.php:80` (`_original.jpg`) | All originals saved/uploaded as `.jpg` regardless of real format | PNG/GIF and video originals get wrong extension; provider-side type sniffing breaks (esp. Google Photos video) | Derive extension from size-candidate URL or `originalformat`; store `media`/format in `stored_files` | No | Unit test with png/video size payloads | — |
| F-04 | High | Security | `app/Http/Controllers/Api/StorageBrowseController.php:194,221,151` return `$e->getMessage()` to client | Raw provider/internal exception text sent to browser | Can leak URLs, ids, bucket names, internal paths | Generic client message + server-side log with context | No | Feature test asserting sanitized body on thrown exception | — |
| F-05 | High | Standards/CI | `.github/workflows/ci.yml` (jobs: quality) vs `composer.json` `lint` scripts | Pint/PHPStan/Deptrac never run in CI | Style, static-analysis, and architecture rules can regress unnoticed | Add CI job: `composer install` + `composer lint` | No | n/a | ci docs |
| F-06 | Medium | Reliability | `app/Jobs/DownloadPhotoJob.php:23,47-49` (`tries=3`, Deferred → `release(10)`); `PhotoUploadExecutionService.php:41-51` | Lock/readiness deferrals consume retry attempts | Photo waiting on a busy lock or pending download gets marked *failed* without any real failure | Use `WithoutOverlapping` middleware with `releaseAfter`, or `retryUntil()` + higher `tries`, or don't count deferrals against `tries` | No | Job test simulating held lock ×3 | — |
| F-07 | Medium | Concurrency | `app/Services/Transfer/TransferBatchReconciler.php:34-46` count-then-update | Read-modify-write race between concurrently finishing jobs | Stale counts / batch stuck `Running` or flapping status | Single atomic `UPDATE ... SET counts = (SELECT ...)` or `lockForUpdate()` transaction | No | Concurrency-ish test with interleaved reconciles | — |
| F-08 | Medium | UX/Correctness | `TransferBatchReconciler.php:40-42` — batch = `Completed` unless *all* items failed | Partial failures reported as success | Users believe archive is complete when files are missing | Add `completed_with_errors` status or surface `failed_count` prominently | Maybe (enum value) | Reconciler test: 1 failed + N completed | user guide |
| F-09 | Medium | Performance | `app/Services/Flickr/PhotoDownloadService.php:70-77,135-143` — loads **all** photos for owner, then per-photo `createPending` + `dispatch` in loop, inside web request | Unbounded memory + N inserts + N Redis round-trips synchronously | With spider-scale catalogs (10k+ photos/contact) the queueing request times out | Chunk query; bulk-insert transfer items; move fan-out into a queued job (or `Bus::batch`) | No | Test asserting chunked dispatch for large sets | — |
| F-10 | Medium | Performance | `app/Services/DashboardService.php:60-97` — ~7 + 9×N queries per snapshot, 5 s cache, UI polls every 5 s | Dashboard is effectively a permanent query storm proportional to account count | DB load grows linearly with connections; blocks the "better UI/UX" goal | Replace per-connection loops with GROUP BY aggregates per metric; consider 15–30 s TTL | No | Snapshot test asserting query count ceiling | — |
| F-11 | Medium | Security | `app/Providers/HorizonServiceProvider.php:30-34` empty allowlist; local stack runs `APP_ENV=local`; README advertises `/horizon` | Horizon fully open on local env (which is also the documented deployment mode); unusable (deny-all) in production | Job payload disclosure + retry/delete controls exposed on the network | Gate Horizon behind the new app auth; document production env expectations | No | Feature test: `/horizon` requires auth | ops docs |
| F-12 | Medium | Truthfulness | `composer.json` requires `jooservices/laravel-events` + `laravel-logging`; `grep` finds **0 usages** in `app/`; `app/Providers/EventServiceProvider.php` is empty; README "Packages" table claims audit logging + event sourcing | Two ecosystem packages installed but unused; README overclaims | Dead dependencies, misleading docs, missed audit-trail value | Either wire them (domain events: `CrawlRequested`, `TransferBatchCompleted`, `StorageAccountConnected`; activity log on settings/credential changes) or drop them and fix README | No | Event/log assertion tests once wired | README |
| F-13 | Medium | Standards | `phpstan.neon` level **1** | Static analysis far below ecosystem standard | Type bugs (like F-03) slip through | Raise stepwise to ≥6 with baseline | No | n/a | coding-standards |
| F-14 | Low | Correctness | `app/Http/Controllers/FlickrAuthController.php:44-53` — `callback()` has no try/catch (contrast `StorageAuthController::callback`) | Flickr API failure during token exchange → raw 500 | Inconsistent error UX between the two OAuth flows | Wrap and redirect with flash error | No | Feature test: SDK throws → redirect+flash | — |
| F-15 | Low | Reliability | `PhotoDownloadExecutionService.php:66` + `finally` at `:121` double-release; lock instance released even when `block()` timed out (`PhotoUploadExecutionService.php:65-98`) | Benign on Redis (owner-checked) but fragile on other cache stores | Latent bug if `CACHE_STORE` changes | Release once, in `finally` only; guard release after failed `block()` | No | Unit test with array lock store | — |
| F-16 | Low | Reliability | `PhotoDownloadExecutionService.php:79` `Http::timeout(120)` hardcoded | Large originals/videos on slow links fail at 120 s | Spurious failures for exactly the biggest files users care about | Make timeout configurable via curated runtime config | No | — | settings docs |
| F-17 | Low | Hygiene | Root: `audit*.md` ×6 untracked, not ignored; `.gitignore` covers `plan.md` only | Repo-root clutter, risk of accidental commit of tool output | Standards optics; noise | Move to `docs/05-maintenance/` or add `audit*.md` to `.gitignore` | No | — | — |
| F-18 | Low | Security | `phpunit.xml:23`, `ci.yml:13` hardcoded `APP_KEY` | Shared static test key | Low risk (test-only), but scanners will flag it | Generate per-run in CI (`php artisan key:generate --show`) | No | — | — |
| F-19 | Low | Cleanliness | `StorageUploadService.php:79-97` unused `$credentials` params; `FlickrOAuthService.php:57-76` `'unknown'` connection workaround | Dead params / SDK workaround in app layer | Minor confusion; smell | Drop params; move nsid guarantee into `jooservices/flickr` | No | — | — |
| F-20 | Low | Tests | `tests/` — no tests for `DashboardService`, `FlickrAuthController` callback, `CatalogController`; zero JS tests (88 TSX/TS files, only `tsc`+eslint) | Blind spots in the layers about to change most (UI revamp) | UI/major-release regressions undetected | Add feature tests for dashboard/catalog/auth-callback; add Vitest + a couple of Playwright smoke flows | No | (is the fix) | testing docs |

---

## 6. Claim vs implementation matrix

| Claimed feature | Source | Implemented? | Tested? | Documented accurately? | Verdict |
|---|---|---|---|---|---|
| OAuth 1.0a multi-account connect/disconnect/switch | README | Yes (`FlickrAuthController`, `FlickrOAuthService`) | Partially (`FlickrAccountListingTest`; callback untested) | Yes | OK |
| Manual crawl of contacts/photos/photosets/galleries/favorites | README | Yes (`FlickrCrawlService` → crawler package) | Yes (`XFlickrCrawlConfigTest`, crawler-side) | Yes | OK |
| "Nothing runs in the background unless you start it" | README | Yes — scheduler's `xflickr:dispatch` only drains user-queued targets (`routes/console.php`) | Indirect | Yes (footnoted) | OK |
| Download with deduplication | README | Yes (`hasCompletedOriginal`, `dedup_key` unique, sha256) | Yes (`PhotoDownloadServiceTest`, `DownloadPhotoJobTest`) | Yes | OK |
| Upload to Google Photos / Drive / OneDrive / R2 | README | Yes (`StorageUploadService`, driver services) | Yes (`GooglePhotosUploadTest`, `StorageR2Test`, etc.) | Yes | OK |
| Storage browser (browse/sync/delete) | README | Yes (`Api/StorageBrowseController` + services) | Yes (`StorageBrowseTest`, `StorageSyncReconcileTest`, `StorageDeleteTest`) | Yes | OK |
| Live dashboard incl. API quota | README | Yes (`DashboardService`, rate-limit endpoints) | Yes (`DashboardSnapshotTest`, rate-limit tests) | Yes | OK |
| Rate limits respected automatically | README | Yes — crawler throttle config (`config/xflickr-crawler.php:28-33`) | Crawler-side | Yes | OK |
| **Structured audit logging** (`laravel-logging`) | README Packages table | **No** — 0 usages in `app/` | No | **No** | **Overclaimed** |
| **Domain event sourcing** (`laravel-events`) | README Packages table | **No** — subscriber registered, but app defines/dispatches no domain events; `EventServiceProvider` empty | No | **No** | **Overclaimed** |
| Repository pattern via `laravel-repository` | README | Yes (all repos extend `EloquentRepository`) | Yes (`RepositoryServiceProviderTest`) | Yes | OK |
| Credentials in MongoDB via `laravel-config` | README | Yes (`RuntimeConfig` facade throughout) | Yes (`FlickrAppProfileTest`, `StorageAppProfileTest`) | Yes | OK |

---

## 7. Public API classification (app surface)

**Stable public surface (keep):** all web routes in `routes/web.php`; API routes under `web` middleware for the UI; artisan `xflickr:dispatch` (crawler package); Docker entrypoints/scripts.

**Should be internal / gated:** `/horizon` (gate — F-11); `POST /api/storage/{provider}/delete`, `POST /storage/disconnect`, settings credential writes — must sit behind auth (F-01).

**Experimental/incomplete:** Google Photos browse (API limits browsing to app-created media — worth a docs caveat); `StorageRemoteSyncState` reconcile flow (young, keep iterating).

**Deprecated / should deprecate:** `DownloadContactsPhotosJob`, `UploadContactsPhotosJob` (already `@deprecated`) — delete in the major release once queues drain.

**Remove candidates:** `app/Models/User.php` + users migration (unless auth lands — preferred); unused `$credentials` params (F-19); `laravel-events`/`laravel-logging` deps **if** the decision is not to wire them (F-12).

---

## 8. Hidden bugs / hidden issues

Covered as findings: wrong file extension (F-03), deferral-eats-retries (F-06), reconciler race (F-07), partial-failure = success (F-08), unbounded queueing loop (F-09), dashboard query storm (F-10), double lock release (F-15), fixed 120 s download timeout (F-16), silent `catch (\Throwable)` ×6 with zero logging (F-04-adjacent; `StorageAuthController.php:29,45,69,109`, `StorageUploadService.php:117`).

Additional notes:

- **Stream hygiene is good**: `Http::sink()` streams downloads to `.part` then moves; uploads use `writeStream` with `finally { fclose }`; browse download closes the resource after `fpassthru` — no obvious leaks.
- `Content-Disposition` filename sanitization in `StorageBrowseController::downloadDisposition()` is careful (null-byte/slash stripping + ASCII fallback) — good.
- OAuth state: storage flow generates and validates its own state (`hash_equals`) despite `stateless()` — correct; return-URL is host-checked against open redirects (`consumeReturnUrl()`), good.
- 3-datastore requirement (MySQL + Redis + MongoDB) is a real DX/ops tax for a self-hosted app; MongoDB exists only for `laravel-config` (+ unused events/logging). If F-12 resolves to "drop", evaluate a `laravel-config` SQL driver to cut the stack to 2 stores. (Optional, ecosystem decision.)

---

## 9. Feature suggestions table

| ID | Feature | Type | What / Why / How | Benefit | Risk / complexity | BC | Priority |
|---|---|---|---|---|---|---|---|
| S-01 | Single-user authentication | Hardening | Login (or token) + `auth` middleware on all routes; gate Horizon with it. Why: F-01. How: Laravel Breeze-minimal or a signed-cookie single-user guard; seed admin from env | Safe to expose beyond localhost | Low–medium | Routes now require login (by design) | **P0** |
| S-02 | **Spider mode (auto-crawl all Flickr)** | New feature | Manual or automatic BFS over contacts-of-contacts with configurable **depth limit**, per-hour budget, max contacts/photos caps, pause switch. How: add `depth`/`origin` to crawl targets (crawler package), a `SpiderPlannerService` that expands the frontier respecting curated config (`spider.enabled`, `spider.max_depth`, `spider.max_new_contacts_per_run`), drained by the existing `xflickr:dispatch`; Operations page shows frontier/depth progress. **Note:** AGENTS.md non-negotiable #3 says "manual crawl only — no auto-spidering without explicit product decision"; this release *is* that decision — update AGENTS.md, README, and skills in the same PR | The headline major-release feature | Medium–high (rate-limit safety, runaway growth control); crawler package changes required | No (opt-in) | **P1** (blocked by P0 + F-06/F-07/F-09) |
| S-03 | CI quality gates | Standards | `composer lint` job, coverage gate, gitleaks, semantic PR title, labeler, release workflow, Scorecard | Matches dto standard; protects the major release | Low | No | **P0** |
| S-04 | Fix transfer pipeline scale defects | Bug fix | F-06, F-07, F-08, F-09 as one workstream ("transfer pipeline v2") | Spider mode multiplies volume; these break first | Medium | No | **P0/P1** |
| S-05 | UI/UX revamp | DX/UX | Photo-grid with lazy thumbnails, SSE/Reverb instead of 5 s polling, failed-item drill-down + retry button, onboarding wizard (settings→connect→crawl), empty states, dark mode | The requested "better UI UX" | Medium | No | **P1** |
| S-06 | `xflickr:doctor` | DX | Console command checking MySQL/Redis/MongoDB connectivity, Flickr app creds, storage token freshness, queue workers, disk space | First-run and support friction drops | Low | No | P1 |
| S-07 | Adopt `jooservices/dto` | Standards | Typed DTOs for the arrays in §4 | Static-analysis-friendly contracts, less `array<string,mixed>` | Low | No | P2 |
| S-08 | Wire or drop events/logging packages | Standards | Decision per F-12; if wire: activity log on credential/settings changes + domain events on crawl/transfer lifecycle | Truthful README, real audit trail | Low–medium | No | P1 |
| S-09 | Extract `jooservices/laravel-cloud-storage` | Integration | Move `StorageFlysystemFactory`, token services, browse/upload/delete driver services into a bridge package | Reuse across JOO apps (any app needing GDrive/OneDrive/R2/GPhotos) | Medium | No (app keeps facade) | P2 |
| S-10 | Push `getSizes` logic into `jooservices/flickr` | API coverage | Move `FlickrPhotoUrlHelper` candidate logic into the SDK; add `originalformat` support (fixes F-03 properly) | SDK completeness; removes app-level API parsing | Low | No | P2 |
| S-11 | Retention & cleanup command | Performance | Prune `xflickr_api_logs`, finished transfer items/batches older than N days (curated config) | DB growth control for spider volumes | Low | No | P2 |
| S-12 | Observability | Performance | Horizon metrics + `/up` already exist; add queue-depth & rate-limit gauges to dashboard; optional Prometheus endpoint | Operate spider mode safely | Medium | No | P3 |
| S-13 | Frontend test harness | DX | Vitest for hooks/components + 2–3 Playwright smoke flows against the test stack | Protects the UI revamp | Medium | No | P1 |
| S-14 | Reduce datastore count | DX | Evaluate SQL driver for laravel-config to drop MongoDB (only if F-12 = drop) | Simpler self-hosting | High (ecosystem decision) | Maybe | P3 |

---

## 10. Recommended roadmap

**P0 — before the major release ships**
1. Authentication + Horizon gate (F-01, F-11 / S-01).
2. Encrypt Flickr `token_payload` in `xflickr-crawler` (F-02).
3. CI gates: lint job, gitleaks, coverage report (F-05 / S-03).
4. Error sanitization + log the swallowed Throwables (F-04).
5. File-extension fix (F-03).

**P1 — during the major release**
1. Transfer pipeline v2: deferral/retry semantics, reconciler atomicity, partial-failure status, chunked fan-out (F-06–F-09 / S-04).
2. Spider mode with depth/limits + AGENTS.md/product-docs update (S-02).
3. UI/UX revamp + frontend tests (S-05, S-13).
4. Wire-or-drop laravel-events/laravel-logging; fix README claims (F-12 / S-08).
5. Dashboard aggregate queries (F-10).

**P2 — soon after**
PHPStan ≥6 (F-13), jooservices/dto adoption (S-07), `xflickr:doctor` (S-06), release workflow + master/release branches, badges, repo-root cleanup (F-17), retention command (S-11), SDK getSizes move (S-10).

**P3 — future**
Storage bridge package extraction (S-09), observability (S-12), datastore reduction (S-14), delete dead User model / deprecated jobs at next major boundary.

---

## 11. Package split / extraction recommendation

**Keep XFlickr as a single application repository.** It is an app, not a package; splitting the app itself buys nothing.

Two extractions do pay off:

1. **`jooservices/laravel-cloud-storage`** (P2) — responsibility: OAuth token refresh (Google/Microsoft), Flysystem factory, browse/upload/delete/thumbnail driver services for Google Photos, Google Drive, OneDrive, R2. Dependencies: socialite, flysystem adapters, google/microsoft SDKs. Moves: `app/Services/Storage/{StorageFlysystemFactory, Storage*TokenService, *BrowseService, GooglePhotos*Service, StorageDownloadService, StorageDeleteService}` + `StorageR2Config`. XFlickr keeps: account model/repo, transfer orchestration, presenters, UI. Why: this is the most reusable code in the repo — any JOO app needing cloud storage benefits, and it already has near-package-quality tests.
2. **Upstream moves, not new packages:** `FlickrPhotoUrlHelper` → `jooservices/flickr`; token encryption + spider depth support → `jooservices/xflickr-crawler`.

---
*Method note: all findings verified by direct source inspection at the paths/lines cited. Tests were not executed (AGENTS.md forbids test runs outside the Docker test stack; not needed for this audit). "Not found" claims are grep-verified.*
