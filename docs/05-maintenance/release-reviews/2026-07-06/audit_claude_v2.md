# XFlickr — Re-Audit After v1.1.0 Implementation (Claude, 2026-07-06)

Scope: audit + improvement + feature suggestions only. No code changed.
Baseline: previous audit (`docs/05-maintenance/release-reviews/2026-07-06/audit_claude.md`), verified against commits `2f51961..0cdd854` (v1.1.0).
Extra dimensions this round: **strict test-suite audit** (per requested Unit/Feature structure) and **agents/skills correctness audit**.

---

## 0. Verification of previous findings (what Cursor actually fixed)

| Prev ID | Finding | Status | Evidence |
|---|---|---|---|
| F-01 | No authentication | **Implemented, but flawed** | `routes/web.php:21-80`, `routes/api.php:14` — all routes behind `auth`/`guest`. **New Critical:** default credential (see N-01) |
| F-02 | Flickr tokens plaintext | **NOT fixed** | `composer.lock` still `xflickr-crawler 1.1.2`; no `encrypted` cast in vendor `Connection` model |
| F-03 | Hardcoded `.jpg` extension | **Fixed** | `FlickrPhotoUrlHelper::resolveExtension()` (URL path → `originalformat` → jpg); used in `PhotoDownloadExecutionService::finalPathFor()` and upload remote path |
| F-04 | Raw `$e->getMessage()` to clients | **Fixed in API controller; not in web OAuth controllers** | `Api/StorageBrowseController::storageErrorResponse()` logs + generic message; `StorageAuthController.php:29,45,69,109` still silent `catch (\Throwable)` with zero logging; new `FlickrAuthController::callback` catch also silent |
| F-05 | No CI lint/coverage gates | **Fixed, one fake gate** | `ci.yml`: security job (composer validate+audit), lint matrix (Pint/PHPStan/Deptrac/AI-sync), ESLint, coverage ≥60% gate, pinned action SHAs. **But** `secret-scanning.yml` is a no-op echo stub (N-04) |
| F-06 | Deferrals consume retries | **Mostly fixed, new semantic bug** | `tries=100`, `maxExceptions=3`, `retryUntil(+6h)` on both jobs — but `$this->attempts()` (which counts deferrals) is passed as `$attempt` and compared against `maxExceptions` (N-05) |
| F-07 | Reconciler race | **Fixed** | `TransferBatchReconciler` now `DB::transaction` + `lockById()` |
| F-08 | Partial failure = success | **Fixed** | `TransferBatchStatus::CompletedWithErrors` in reconciler match |
| F-09 | Unbounded queueing in web request | **Half fixed** | Web request now dispatches `FanOutTransferBatchJob` (good); bulk insert `createPendingBulk` chunks of 500 (good). **But** fan-out still accumulates all pending photos in memory and runs 1–2 queries per photo (N-06) |
| F-10 | Dashboard query storm | **NOT fixed** | `git diff 2f51961..HEAD -- app/Services/DashboardService.php` is empty |
| F-11 | Horizon gate | **Fixed** | `HorizonServiceProvider::gate()` → `$user !== null` |
| F-12 | events/logging installed but unused | **Fixed (minimally)** | 4 domain events implementing `EventSourcingInterface`, dispatched in `FlickrOAuthService`/`StorageAccountService`; `AdminActionLogger` (laravel-logging `ActivityLog`) on settings writes. No transfer/crawl events; **zero `Event::fake` tests** |
| F-13 | PHPStan level 1 | **Fixed** | Level 6 + baseline (148 messages / 166 counted entries). Burn-down expected |
| F-14 | Flickr callback raw 500 | **Fixed** | `FlickrAuthController.php:43-63` try/catch with flash errors |
| F-15 | Double lock release | **Fixed** | Early release removed; `$lockHeld` guard in upload service |
| F-16 | Fixed 120 s download timeout | **NOT fixed** | `PhotoDownloadExecutionService.php:79` |
| F-17 | Audit files in repo root | **Fixed** | Moved to `docs/05-maintenance/release-reviews/2026-07-06/`; `.gitignore` updated |
| F-18 | Hardcoded APP_KEY in CI | **NOT fixed** | `ci.yml` env block; also copied into `release.yml` |
| F-19 | Dead params / 'unknown' nsid hack | **Partial** | `StorageUploadService::diskFor()` param removed; `'unknown'` hack still in `FlickrOAuthService.php:53-65` |
| F-20 | Test gaps | **Partial** | Added: `TransferBatchReconcilerTest`, `DownloadPhotoJobTest`, `PhotoDownloadExecutionServiceTest`, `FlickrPhotoUrlHelperTest`, `Auth/AuthenticationTest`. Still missing: controller coverage, events, frontend (§11) |
| — | Branch model / release workflow / semantic PR / labeler / scorecard | **Fixed** | `master` + `release/1.1.0` branches, tags `v1.0.0`/`v1.1.0`, `release.yml`, `semantic-pr.yml`, `pr-labeler.yml` + `labeler.yml`, `scorecard.yml`, `docs/05-maintenance/github-branch-protection.md` |
| — | Badges | **NOT fixed** | `README.md` head — no badges |

**Scorecard: 14 fixed, 4 partial/flawed, 5 untouched — plus the two headline product features (spider, UI/UX) were not attempted.**

---

## 1. Executive summary

**Overall status: Needs fixes.** v1.1.0 is a genuine standards leap (auth, dto-style CI, branch model, transfer v2 core fixes), but three implementations are "green-looking but wrong" (default admin credential, no-op secret scanning, cosmetic chunking), two previous highs remain open (token encryption, dashboard query storm), and **none of the requested major-release features (spider, UI/UX) exist yet**.

**Best positioning:** unchanged — self-hosted Laravel application; README/code match.

**Standards alignment with jooservices/dto: Good (structurally) / Partial (substantively)** — the workflow surface now mirrors dto (release, semantic-pr, labeler, scorecard, coverage gate, branch model), but `jooservices/dto` itself is a required-yet-unused dependency, secret scanning is a stub, and badges are missing.

**Top 5 risks**

1. **Default admin credential** `admin@local` / `password` auto-seeded by the Docker entrypoint, advertised in README and `.env.example`, with **no password-change UI, command, or forced rotation** — authentication in name only for any non-localhost deployment.
2. Flickr OAuth tokens still plaintext at rest (crawler package unchanged).
3. `secret-scanning.yml` produces a green check while scanning nothing.
4. Upload pipeline scale trap: per-account serialization lock + `retryUntil(6h)` means a large upload batch mass-fails everything still queued after 6 hours (N-07).
5. Requested major features (spider all Flickr with depth limits; UI/UX) not started; AGENTS.md still explicitly forbids auto-spidering (`AGENTS.md:97`).

**Top 5 quick wins**

1. Force a generated admin password: entrypoint generates a random password, prints once to console (or requires `ADMIN_PASSWORD` env), and add `php artisan xflickr:user:password` — small change, kills the top risk.
2. Replace the secret-scanning stub with the gitleaks CLI run directly (no `GITLEAKS_LICENSE` needed when not using the marketplace action) or TruffleHog.
3. Fix the `attempts()` vs `maxExceptions` comparison in both execution services — one-line semantic fix each.
4. Add `Log::warning` to the six silent `catch (\Throwable)` blocks in `StorageAuthController` / `FlickrAuthController`.
5. Use `jooservices/dto` for at least one real contract (e.g. download candidate, OAuth app config) or remove it — currently a dead dependency listed as a changelog feature.

**Top 5 feature opportunities** — unchanged from previous audit, now with sharper priority: (1) spider mode with depth/limits, (2) password management + optional 2FA, (3) `xflickr:doctor`, (4) SSE/Reverb-based operations UX, (5) `jooservices/laravel-cloud-storage` extraction.

---

## 2. Standards alignment vs jooservices/dto (delta view)

| Item | Now | Remaining gap |
|---|---|---|
| Branch model | `develop`/`master`/`release/1.1.0` + tags | None — matches dto |
| Branch protection | Documented (`docs/05-maintenance/github-branch-protection.md`) | Apply rulesets on GitHub (not verifiable locally) |
| CI checks | security → lint matrix → frontend → tests+coverage(60%), pinned SHAs, concurrency groups | Coverage threshold parsing relies on `bc` and root `coverage.xml` — verify artifact path stability; consider `--min` PHPUnit flag instead |
| Release workflow | `release.yml` on `v*.*.*` tags | Good |
| Semantic PR / labeler | Present | `semantic-pr.yml` uses `pull_request_target` — safe as configured (read-only perms), keep it that way |
| Secret scanning | **Stub only** (`secret-scanning.yml` echoes "disabled") | Real scanner (gitleaks CLI / TruffleHog) — fake green is worse than absent |
| Scorecard | `scorecard.yml` on `master` + weekly | Good |
| Coverage gate | 60% clover-based gate in CI | Ratchet plan (60→70→80) not documented |
| Badges | **Still none** in README | Add CI / coverage / release badges |
| Changelog/SemVer | v1.1.0 entry accurate for most items | Two overclaims: "Chunked async fan-out" (chunking is cosmetic — N-06), "Direct dependency on jooservices/dto" (unused — N-02) |
| AGENTS/CLAUDE/AI | Sync verifier passes (`tools/verify-ai-instructions-sync.php` exit 0) | Content stale — see §12 |
| dto usage | `jooservices/dto ^1.0` in composer.json | **Zero usages in `app/`** (grep: not found) |

---

## 3. Architecture and code quality (delta)

- Transfer orchestration now: `Controller → Service.queueFromInput → FanOutTransferBatchJob → Service.fanOut* → per-photo jobs`. Direction stays clean; Deptrac still passes conceptually (Jobs → Services allowed).
- `FanOutTransferBatchJob` carries a `transferType` string switch (`'download'`/`'upload'`) — stringly-typed; an enum (`TransferType`) exists nowhere though `TransferBatchRepository` uses `'download'`/`'upload'` literals too. Minor: introduce a backed enum.
- **User's "reduce duplication / one-time classes" goal: not addressed.** Still present: `queueFromInput` twins (`PhotoDownloadService` / `PhotoUploadService` — same branching shape), `apiKeyHint()` vs `clientIdHint()` (identical masking, `FlickrAppProfileService.php:129` / `StorageAppProfileService.php:108`), three browse services with parallel scaffolding, rate-limit trio (`FlickrRateLimitPresenter` / `QueryService` / `UsageQueryService`) unmerged.
- New dead-ish code: `AdminActionLogger::record()` guards `class_exists(ActivityLog::class)` — the package is a hard dependency; the guard can never be false. Harmless but noise.
- `'unknown'` nsid workaround still in `FlickrOAuthService.php:53-65`.

## 4. DTO and data-contract report (delta)

- **N-02 (High):** `jooservices/dto ^1.0` added to `composer.json:11` and announced in CHANGELOG, with **no import anywhere in `app/`**. Same anti-pattern the v1.0 audit flagged for laravel-events/logging. Either hydrate real contracts with it — best first candidates: `array{url,variant}` download candidate, OAuth app config arrays (`StorageOAuthService::appConfig()` still does `client_id`/`clientId` dual-key reading), upload result `array{id,path,etag}` — or remove the dependency.
- Events (`FlickrAccountConnected` etc.) have typed readonly constructor props + explicit `payload()` — good contract style, consistent with the package interface.
- FormRequest boundary remains exemplary; `LoginRequest` follows the Breeze pattern with rate limiting.

---

## 5. Findings table (new + carried-over open items)

| ID | Sev | Category | Evidence | Problem | Why it matters | Recommended fix | BC | Tests | Docs |
|---|---|---|---|---|---|---|---|---|---|
| N-01 | **Critical** | Security | `database/seeders/AdminUserSeeder.php:14-21` (`admin@local`/`password`); `docker/entrypoint.sh:101` auto-seeds; `README.md:82` and `.env.example:76-80` advertise it; grep: **no password-change route/command exists** | Authentication was added, then defeated by a publicly documented default credential with no rotation path | Any reachable instance is one known login away from full takeover (incl. cloud-storage delete); login rate-limit (5/attempt-key) is irrelevant against a *known* password | Entrypoint: require `ADMIN_PASSWORD` env or generate random + print once; add `xflickr:user:password` command and/or Settings → Account page; force change on first login | No | Feature test: seeder refuses default password in production env | README, first-run guide |
| N-02 | High | Standards | `composer.json:11`; `grep -rl "JOOservices\\\\Dto" app/` → not found; `CHANGELOG.md` v1.1.0 "Added: Direct dependency on jooservices/dto ^1.0" | Dependency added for standards optics, never used; changelog presents it as a delivered feature | Dead dep + misleading changelog — exactly the overclaim class flagged last audit | Use it (§4 candidates) or drop it and amend changelog | No | Hydration tests once adopted | CHANGELOG |
| N-03 | High | Product | grep `spider` in `app/`, `config/`, `routes/`, `resources/js` → **not found** (only docs/audit mentions); `AGENTS.md:97` still forbids auto-spidering | Headline major-release feature not started; governing rules still prohibit it | The release goal cannot ship; agents following AGENTS.md will refuse the work | Record the product decision: update `AGENTS.md`, add spider design doc, then implement (design sketch in previous audit S-02) | No | — | AGENTS.md, README |
| N-04 | High | Security/CI | `.github/workflows/secret-scanning.yml:16-21` — job echoes "disabled until GITLEAKS_LICENSE is configured" | A green check named "Secret Scanning" that scans nothing | False assurance; worse than no workflow — reviewers/Scorecard see a passing gate | Run gitleaks CLI directly (`gitleaks detect --source .`, no license needed) or TruffleHog OSS | No | — | — |
| N-05 | Medium | Correctness | `DownloadPhotoJob.php:50-53` passes `$this->attempts()` + `$this->maxExceptions` into `execute()`; `PhotoDownloadExecutionService.php:114` `if ($attempt < $maxAttempts)`; same in `UploadPhotoJob`/`PhotoUploadExecutionService` | `attempts()` counts **all** attempts including lock deferrals; compared against `maxExceptions` (3). After ≥3 deferrals, the first real exception skips `markPending`/status+error recording even though the job will retry | Stored file stuck showing `downloading`/`uploading` with no error message during retry window; UI lies about state | Track exception count separately (pass `$this->job->maxExceptions` semantics correctly, e.g. compare against `tries`, or use a dedicated exceptions counter) | No | Job test: 3 deferrals + 1 exception → status `pending` with message | — |
| N-06 | Medium | Performance | `PhotoDownloadService::fanOutDownloads()` (`$pending = $pending->concat($filtered)` inside chunk callback; `hasCompletedOriginal()` per photo); `PhotoUploadService::fanOutUploads():148-172` (`findOriginalByFlickrPhotoId` + `hasCompleted` per photo) | Chunked *reading* but full in-memory accumulation + 1–2 queries **per photo** (10k photos ⇒ 10–20k queries) | The stated purpose of fan-out (spider-scale) is not achieved; CHANGELOG's "chunked async fan-out" overclaims | Set-based exclusion (LEFT JOIN `stored_files`/`storage_uploads` or `whereIn` per chunk); process/group per chunk instead of global accumulate (group keys are stable — batches can be created incrementally) | No | Fan-out test asserting bounded query count (e.g. `expectsDatabaseQueryCount`) | CHANGELOG wording |
| N-07 | Medium | Reliability | `UploadPhotoJob::retryUntil()` = +6 h; `PhotoUploadExecutionService.php:62-66` per-account lock (300 s TTL, `block(60)`) serializes uploads | Uploads run one-at-a-time per account; queued jobs deferred past 6 h hit `retryUntil` → `failed()` → marked failed en masse | A 5–10k-photo upload batch cannot finish within 6 h at ~5–10 s/upload; tail mass-fails | Chain uploads (dispatch next on completion), or dedicated single-worker queue per account instead of lock+retry, or extend `retryUntil` based on batch size | No | Simulated long-batch test with time travel | — |
| N-08 | Medium | Security | Carried: crawler `token_payload` plaintext (crawler 1.1.2 unchanged) | Prev F-02 | Same | Fix in `jooservices/xflickr-crawler` (encrypted cast + re-encrypt migration), bump dep | Maybe | Package tests | SECURITY note |
| N-09 | Medium | Performance | Carried: `DashboardService.php` unchanged — ~7 + 9×N queries per 5 s cache miss under constant nav polling | Prev F-10 | Grows with accounts; UI/UX revamp will add pollers | GROUP BY aggregates per metric | No | Query-count test | — |
| N-10 | Medium | Observability | `StorageAuthController.php:29,45,69,109`, `FlickrAuthController.php:52-58` — `catch (\Throwable)` no logging | OAuth failures invisible to operators | Support burden; contradicts new logging posture | `Log::warning($e)` before flash-redirect (or `report($e)`) | No | Log::spy assertions | — |
| N-11 | Low | Tests/DRY | `tests/TestCase.php:33-44` `authenticateAsAdmin()` re-hardcodes `admin@local`/`password`, duplicating `AdminUserSeeder` | Credential duplicated in 2 places; changing the seeder silently breaks the test bootstrap | Drift risk | Use `UserFactory` or call the seeder | No | — | — |
| N-12 | Low | Tests | `tests/Support/IgnoresAuthentication.php` marker trait + `class_uses_recursive` magic in base `TestCase::setUp` | Implicit auto-login for every RefreshDatabase test — invisible global state, contrary to explicit-arrange best practice | New contributors won't know why requests are authenticated; opt-out via marker trait is inverted logic | Prefer explicit `$this->actingAsAdmin()` helper called in tests, or at least document in testing guide | No | — | testing.md |
| N-13 | Low | Standards | `README.md:1-12` no badges; `ci.yml`/`release.yml` still embed static `APP_KEY` | Carried F-18 + badges gap | Optics/consistency vs dto | Badges; `php artisan key:generate --show` in CI | No | — | README |
| N-14 | Low | Cleanliness | `AdminActionLogger.php:17` impossible `class_exists` guard; `FanOutTransferBatchJob` stringly-typed `transferType` | Minor noise / missing enum | Readability | Drop guard; add `TransferType` enum | No | — | — |
| N-15 | Low | Config | `PhotoDownloadExecutionService.php:79` `Http::timeout(120)` hardcoded (carried F-16) | Large originals fail | Spurious failures | Curated runtime-config knob | No | — | settings docs |

---

## 6. Claim vs implementation matrix (v1.1.0 claims)

| Claimed (CHANGELOG v1.1.0 / README) | Implemented? | Tested? | Documented accurately? | Verdict |
|---|---|---|---|---|
| "All web and API routes require login" | Yes (`routes/*` groups) | Yes (`AuthenticationTest`, guests-redirected test) | Yes | OK |
| "Session authentication with default admin account" | Yes — that's the problem | Seeder test exists | Documented *too* well (public creds) | **Works-as-designed flaw (N-01)** |
| "Structured audit logging for settings changes" | Yes, settings only (`AdminActionLogger` in 3 controllers) | **No** — no test asserts an activity log write | Slightly broad | Partial |
| "Domain events for account connect/disconnect" | Yes (4 events, dispatched) | **No** — `Event::fake` appears nowhere in tests | Yes | Partial |
| "Transfer pipeline v2: atomic reconcile, CompletedWithErrors, chunked async fan-out, resilient retry" | Reconcile ✓, status ✓, fan-out **half** (N-06), retry **flawed** (N-05, N-07) | Reconciler ✓, jobs partially | "Chunked" overclaims | **Partially correct** |
| "Dynamic media file extensions" | Yes | Yes (`FlickrPhotoUrlHelperTest`) | Yes | OK |
| "Dto-style CI pipeline ... 60% coverage gate" | Yes | n/a | Yes | OK |
| "GitHub workflows aligned with dto: ... secret-scanning" | Workflow file exists, **does nothing** | n/a | **No** — implies scanning happens | **Misleading (N-04)** |
| "Direct dependency on jooservices/dto" | Installed, unused | n/a | Misleading as "Added" feature | **Misleading (N-02)** |
| "PHPStan level 6 with baseline (166 entries)" | Yes (148 messages / 166 summed counts) | n/a | Yes | OK |
| Requested: spider all Flickr, better UI/UX | **Not implemented** (UI delta = Login page + StatusBadge/AppLayout tweaks) | — | Not claimed (honest) | Outstanding scope |

---

## 7. Public API classification (delta)

- **Stable:** auth routes (`/login`, `/logout`); everything previously listed, now auth-gated.
- **Should be internal/harden:** `AdminUserSeeder` (should refuse to run with default password outside local); CI `APP_KEY`.
- **Experimental/incomplete:** `FanOutTransferBatchJob` (works, needs N-05/N-06/N-07 before spider load); event coverage (accounts only).
- **Deprecated/remove:** ~~Download/UploadContactsPhotosJob~~ removed in v1.1.0 ✓; `AdminActionLogger` class-exists guard; `'unknown'` nsid hack (move to SDK).

---

## 8. Hidden bugs / hidden issues report

New this round: N-01 (default credential), N-04 (no-op scanner), N-05 (attempts/maxExceptions mismatch → stale item status + lost interim errors), N-06 (fan-out N+1: 1–2 queries/photo + unbounded accumulation), N-07 (upload serialization × 6 h retryUntil → mass tail failures on big batches), N-10 (silent OAuth catches), N-11/N-12 (test bootstrap magic + credential duplication).

Carried and still open: plaintext Flickr tokens (N-08), dashboard query storm (N-09), 120 s timeout (N-15), CI APP_KEY (N-13), `'unknown'` nsid hack.

Verified non-issues this round: lock double-release fixed; `original_name` column pre-existed (no schema break from the new write); reconciler transaction correct; OAuth state + open-redirect checks unchanged and sound; `pull_request_target` in semantic-pr restricted to read permissions.

---

## 9. Tests audit (strict, per requested structure)

Current shape vs the requested `tests/Unit/{Services,Repositories,Jobs,Events}` + `tests/Feature/Http/Controllers/<ControllerName>Test` model:

**What's good**

- Service tests assert **dispatch payloads**, not just counts (`PhotoDownloadServiceTest` uses `Bus::fake([DownloadPhotoJob::class])` and asserts grouping) — matches the "fake → assert job dispatched with right data" rule.
- External HTTP mocked (`Http::fake`/Mockery in 9 files); no test hits Flickr/Google for real.
- Isolated Docker test stack, sqlite `:memory:`, coverage now enforced at 60%.
- New regression tests track the new code: `TransferBatchReconcilerTest` (124 ln), `DownloadPhotoJobTest`, `PhotoDownloadExecutionServiceTest`, `FlickrPhotoUrlHelperTest`, `Auth/AuthenticationTest` (incl. seeder test).

**Gaps vs the requested standard**

1. **Layout:** almost everything lives flat in `tests/Feature/` regardless of subject. `tests/Unit/` has 5 files (2 of them Example/pagination). Services/repositories/jobs are tested as booted-app feature tests — acceptable for Laravel, but the requested `Unit/Services/<ServiceName>Test`, `Unit/Repositories`, `Unit/Jobs`, `Unit/Events` and `Feature/Http/Controllers/<ControllerName>Test` mapping does not exist. There is no per-controller HTTP test for: `DashboardController`, `CatalogController`, `SettingsController`, `ContactsController`, `CrawlOperationsController`, `StorageAuthController` (web), `RuntimeConfigController`, `PhotoDownload/UploadController`, `Api/CrawlStatusController`, `Api/TransferProgressController` (partial via `TransferWebRouteIsolationTest`).
2. **Events: zero tests.** `Event::fake` — not found in `tests/`. The 4 new domain events and the AdminActionLogger writes are unasserted (the exact "if we have event → fake → assert data" rule).
3. **No fixture convention.** No `tests/Fixtures/`, no `loadFixture()` helper on `TestCase`; HTTP fakes inline JSON arrays per test. Introduce module-level fixture loading as requested.
4. **`ExampleTest` ×2 still present** — junk per the strict standard.
5. **Implicit auth bootstrap** (N-12) and duplicated credentials (N-11).
6. **No frontend tests** (88 TS/TSX files; only `tsc` + ESLint). The planned UI/UX release needs Vitest + smoke E2E before, not after.
7. **Missing tests for known-fragile spots:** FanOut memory/query behavior (N-06), attempts/maxExceptions semantics (N-05), upload 6 h horizon (N-07), storage OAuth catch paths (N-10), dashboard snapshot query count (N-09).

**Recommended target layout** (align with the requested structure without a big-bang move):

```
tests/Unit/Support/...            (pure helpers: FlickrPhotoUrlHelper ✓ exists, move)
tests/Unit/Services/...           (services with mocked repos — new)
tests/Unit/Events/...             (payload/aggregateId assertions — new)
tests/Feature/Jobs/...            (job dispatch + handle with fakes)
tests/Feature/Http/Controllers/<Name>Test  (route + response contract per controller)
tests/Fixtures/ + TestCase::loadFixture()
```

Adopt per-PR: any touched class gets its test moved/created in the right slot; enforce via review checklist rather than a disruptive one-shot move.

---

## 10. Agents / skills correctness audit (requested)

`composer instructions:verify` passes (three-way sync `ai/skills` ↔ `.github/skills` ↔ Cursor adapters is intact; `ai/skills` ≡ `.github/skills` byte-wise except README). The **sync is correct; the content is stale in five places**:

| File | Issue | Evidence | Fix |
|---|---|---|---|
| `AGENTS.md:97` | "Manual crawl only — do not add auto-spidering without explicit product decision" contradicts the current roadmap (spider is the next major feature) | This audit's product context | Record the product decision; rewrite rule as "spidering only via the approved spider design (depth/limit config)" |
| `AGENTS.md` (Quality gates section) | Lists only `composer test:docker`, `npm run typecheck`, `composer instructions:verify` — CI now also requires **Pint, PHPStan, Deptrac, ESLint, and 60% coverage**; agents following AGENTS.md will open failing PRs | `AGENTS.md:104-112` vs `ci.yml` lint matrix + `npm run lint` + coverage job | Add `composer lint` and `npm run lint`; mention coverage gate |
| `AGENTS.md` / `CLAUDE.md` | **No mention of authentication** — agents don't learn that routes need login, that `TestCase` auto-authenticates, that `IgnoresAuthentication` exists, or that a seeded admin credential exists | grep auth/login in `AGENTS.md` → route-table rows only | Add an "Authentication" note to Project identity + testing skill |
| `ai/skills/testing-and-quality-gates/SKILL.md` | References `composer test:docker` only; does not know `test:docker:coverage` or the coverage gate | skill lines 15–27 | Update commands |
| `ai/skills/release-and-deploy-flow/SKILL.md` | Describes CHANGELOG mechanics only; unaware of the new tag-triggered `release.yml`, `master`/`release/*` branch model, and branch-protection doc | skill lines 10–38 | Document the actual release procedure (branch → tag → workflow) |

Also: `.github/copilot-instructions.md` and `.cursor/rules/*.mdc` inherit the same staleness via sync — fixing the canonical `ai/skills` bodies fixes all adapters. `CLAUDE.md`'s slash-command list matches `.claude/commands/` (verified: 7 files, names match).

---

## 11. Feature suggestions table

| ID | Feature | Type | What/Why/How | Benefit | Risk | BC | Priority |
|---|---|---|---|---|---|---|---|
| S-01 | Admin credential hardening | Hardening | Random/env-driven initial password, `xflickr:user:password` command, Settings → Account page, force-change flag | Makes v1.1.0's auth real | Low | No | **P0** |
| S-02 | Real secret scanning | Standards | gitleaks CLI step (license-free) or TruffleHog | Honest CI gate | Low | No | **P0** |
| S-03 | Transfer pipeline v2.1 | Bug fix | N-05 exception counting; N-06 set-based fan-out; N-07 upload chaining or per-account queue | Prerequisite for spider load | Medium | No | **P0/P1** |
| S-04 | Spider mode | New feature | As designed in previous audit (S-02 there): `depth`/`origin` on crawl targets, `SpiderPlannerService`, curated config `spider.max_depth`/caps/pause, drained by `xflickr:dispatch`; manual + auto modes; **requires AGENTS.md rule change first** | The major-release headline | Medium-high | No | **P1** |
| S-05 | UI/UX revamp + frontend tests | DX/UX | SSE/Reverb ops feed, photo grid, failed-item retry, onboarding wizard; Vitest + Playwright smoke first | Second release goal | Medium | No | **P1** |
| S-06 | Adopt or drop jooservices/dto | Standards | §4 candidates or removal | Truthful deps | Low | No | P1 |
| S-07 | Token encryption in crawler | Hardening | Carried | Data-at-rest safety | Medium | Maybe | P1 |
| S-08 | Dashboard aggregates | Performance | Carried (GROUP BY) | Scales with accounts | Low | No | P1 |
| S-09 | Test restructure + fixtures + event tests | DX | §9 layout, `loadFixture`, `Event::fake` coverage, delete ExampleTests | Meets stated test standard | Low | No | P1 |
| S-10 | AI-guidance refresh | Standards | §10 five fixes | Agents produce passing PRs | Low | No | P1 |
| S-11 | `xflickr:doctor` | DX | Carried | Support burden ↓ | Low | No | P2 |
| S-12 | Coverage ratchet + PHPStan baseline burn-down | Standards | 60→70→80; baseline 148→0 (38 `property.notFound` likely fixable with crawler model `@property` docblocks) | Quality trend | Low | No | P2 |
| S-13 | Extract `jooservices/laravel-cloud-storage` | Integration | Carried | Ecosystem reuse | Medium | No | P2/P3 |
| S-14 | Retention/cleanup command; observability | Performance | Carried | Spider-scale ops | Low/Med | No | P2/P3 |

---

## 12. Recommended roadmap

**P0 — immediately (before exposing any instance / before spider work)**
1. Default-credential fix (S-01).
2. Real secret scanning (S-02).
3. N-05 attempts/maxExceptions fix + N-10 logging (small diffs).

**P1 — the major release itself**
1. Transfer v2.1 scale fixes (S-03) → then Spider (S-04, with AGENTS.md decision recorded).
2. UI/UX + frontend test harness (S-05).
3. dto adopt-or-drop (S-06); crawler token encryption (S-07); dashboard aggregates (S-08); test restructure (S-09); AI-guidance refresh (S-10).

**P2** — doctor command, coverage/PHPStan ratchets, badges, CI APP_KEY, timeout config, duplication cleanups (hint fns, queueFromInput twins, rate-limit trio, browse scaffolding), `TransferType` enum.

**P3** — cloud-storage package extraction, retention, observability, datastore-count reduction.

## 13. Package split recommendation

Unchanged: keep one app repo; extract `jooservices/laravel-cloud-storage` at P2/P3; move `FlickrPhotoUrlHelper` (now including `resolveExtension`) into `jooservices/flickr`; token encryption + spider depth support belong in `jooservices/xflickr-crawler`.

---

## 14. Documentation tracking audit (`docs/05-maintenance` and friends)

### 14.1 The problem, evidenced

`docs/05-maintenance/` is 392 KB — 10× any other docs section — and work-status information is scattered across **five competing places** with no single source of truth:

| Location | What it tracks | State |
|---|---|---|
| `risks-legacy-and-gaps.md` | Two "resolved" tables + open gaps + constraints + pointers + process, all in one 51-line file | **Already stale** (see 14.2) |
| `codebase-audit-2026-06.md` (213 ln) | Original audit with per-issue `**Status: Resolved**` notes edited inline | **All 11 issues Resolved** — a finished document still living in the active dir |
| `release-reviews/2026-07-06/` (~350 KB, 7 audit files) | v1.1.0 pre-release audits; README calls `audit_claude.md` "the implementation checklist" | **No per-finding disposition** — nothing records which findings were implemented / deferred / rejected in v1.1.0 |
| `02-docs-changelog.md` | Doc/governance changes | **Stopped at 2026-06-23** — v1.1.0's own doc changes (branch-protection doc, audit relocation, README auth) never recorded |
| `CHANGELOG.md` | Release notes | Accurate, but resolution facts are re-stated here *and* in risks doc *and* inline in the old audit — triple bookkeeping |

Triple bookkeeping has already produced drift.

### 14.2 Stale/incorrect status entries (verified against code)

| Doc & line | Claim | Reality | Verdict |
|---|---|---|---|
| `risks-legacy-and-gaps.md` → Open gaps: "CI: Coverage gate not yet enforced — Low" | Not enforced | `ci.yml` enforces **60% since v1.1.0** | **Stale — resolved item listed as open** |
| `risks-legacy-and-gaps.md` → Open gaps: "Transfer pipeline scale/reliability (deferrals, **reconciler race**)" | Race open | Reconciler race **fixed** (`DB::transaction` + `lockById`); the *remaining* issues are different ones (N-05/N-06/N-07) | **Half-stale — wrong item names** |
| `risks-legacy-and-gaps.md` → Resolved: "User authentication … all routes require auth" | Done | True but omits the default-credential caveat (N-01) — reads as fully closed | **Overstated** |
| `risks-legacy-and-gaps.md` → Constraints: "Manual crawl only" | Current policy | Contradicts the agreed next-release spider feature; same conflict as `AGENTS.md:97` | **Needs product-decision update** |
| `risks-legacy-and-gaps.md` → Missing rows | — | Known-open items absent entirely: plaintext Flickr tokens is listed ✓, but default admin credential, secret-scanning stub, unused `jooservices/dto`, dashboard query storm, upload 6 h horizon are **not tracked anywhere** | **Backlog incomplete** |
| `docs/04-development/testing.md` | Testing guide | Zero mentions of `coverage` or the new `composer test:docker:coverage` / 60% CI gate (grep count: 0) | **Stale after v1.1.0** |
| `02-docs-changelog.md` | "Record of significant documentation changes" | Last entry 2026-06-23; v1.1.0 doc changes unrecorded | **Stale by its own charter** |
| `codebase-audit-2026-06.md` residues | Issues #3/#10 contain "also noted" leftovers | Spot-checked: `CrawlStatusController` `app()` calls gone; `Operations.tsx` now 339 ln matching the "~340" resolution note | Residues genuinely resolved — the whole doc is finished |

### 14.3 Root cause

Point-in-time documents (audits, plans) are being **edited after the fact** to carry status (`**Status: Resolved**` stamps inside `codebase-audit-2026-06.md`), while the "living" tracker (`risks-legacy-and-gaps.md`) mixes five concerns and depends on manual sync with the CHANGELOG. Nobody dispositioned the 7-file release-review set at all. Result: to answer "what is still open?" you must read ~400 KB across 10+ files — exactly the complaint.

### 14.4 Recommended structure

Principles: **(1)** point-in-time docs are immutable and archived, never status-stamped; **(2)** status lives in exactly one place; **(3)** one item = one row, linking back to the audit that raised it; **(4)** resolved rows carry the version that resolved them and get pruned to archive after the next release.

```
docs/05-maintenance/
├── BACKLOG.md                     ← THE single live tracker (see format below)
├── constraints.md                 ← durable product/architecture constraints only
│                                     (manual-crawl policy → rewrite on spider decision,
│                                      API quota, two-stack rule)
├── docker-safety.md               ← keep as-is (durable reference)
├── known-dangerous-commands.md    ← keep as-is
├── github-branch-protection.md    ← keep as-is (durable setup doc)
├── docs-changelog.md              ← keep only if maintained; otherwise fold into CHANGELOG
└── archive/                       ← ALL point-in-time material, immutable
    ├── README.md                  ← index with dates (existing pattern is good — extend it)
    ├── 2026-06-plans/             ← XFlickr.md, final_plan.md, codex.md, grok.md
    ├── 2026-06-codebase-audit.md  ← MOVE here (fully resolved; strip nothing)
    └── release-reviews/
        └── 2026-07-06/            ← MOVE here + add DISPOSITION.md (see below)
```

**`BACKLOG.md` format** — one table per status, newest first, no prose bodies (detail stays in the source audit, linked by ID):

```markdown
# Maintenance backlog

Single source of truth for known gaps. Detail lives in the linked audit finding —
do not restate it here. Move rows between sections; never edit archived audits.

## Open
| ID | Item | Source | Priority | Since |
|----|------|--------|----------|-------|
| N-01 | Default admin credential, no rotation path | [re-audit §5](archive/release-reviews/2026-07-06/…) | P0 | v1.1.0 |
| F-02/N-08 | Flickr token_payload plaintext (crawler) | audit_claude §5 | P1 | v1.0.0 |
| …

## Blocked
| ID | Item | Blocked on | Source |
| S-04 | Spider mode | Product decision + AGENTS.md:97 rule change; N-05/06/07 fixes | re-audit §5 |

## Resolved (prune to archive after next release)
| ID | Item | Resolved in | Evidence |
| F-07 | Reconciler race | v1.1.0 | TransferBatchReconciler + test |
| …
```

**`DISPOSITION.md`** inside each `release-reviews/<date>/`: one line per finding ID → `implemented in v1.1.0` / `deferred → BACKLOG N-xx` / `rejected (reason)`. This is the missing artifact that would have made "Cursor implemented but not fully correct" visible immediately.

**What gets deleted vs moved:**

- Move (don't delete): `codebase-audit-2026-06.md` → archive; its inline status stamps stay as historical record but no future edits.
- Replace: `risks-legacy-and-gaps.md` → split into `BACKLOG.md` + `constraints.md`; delete the old file (its two resolved-tables become the initial "Resolved" section, corrected per 14.2).
- Delete candidates: none of the audit content — but the **7 overlapping release-review files** could be pruned to `audit_final.md` + the canonical one + `DISPOSITION.md` if repo size matters (optional; archive is cheap).
- Consider GitHub Issues + labels (`backlog`, `blocked`, label per priority) as the tracker instead of `BACKLOG.md`, keeping the file as a generated index — the risks doc already tells reporters to "open a GitHub issue with the documentation label", so the repo is halfway there. For a solo-maintainer repo, the flat `BACKLOG.md` is the lower-friction choice.

**Also update when restructuring** (all currently point at the old layout): `docs/README.md:50,60` (section description + ownership row), `AGENTS.md` "Key docs" links (`risks-legacy-and-gaps.md` reference), and `ai/skills/documentation-sync/SKILL.md` if it names the risks doc (sync via `composer instructions:verify` after edit).

### 14.5 Additional findings for the table

| ID | Sev | Category | Evidence | Problem | Fix | Priority |
|---|---|---|---|---|---|---|
| D-01 | Medium | Docs/tracking | `risks-legacy-and-gaps.md` "Open gaps": coverage-gate row vs `ci.yml` coverage job | Live tracker lists resolved work as open (and misnames remaining transfer issues) | Correct now; adopt §14.4 structure so this class of drift can't recur | P1 |
| D-02 | Medium | Docs/tracking | `release-reviews/2026-07-06/README.md` ("implementation checklist") + absence of any disposition file | 7 audits, zero recorded dispositions — cannot tell what v1.1.0 implemented vs deferred | Add `DISPOSITION.md` retroactively for 2026-07-06 (this re-audit's §0 table is the raw material) | P1 |
| D-03 | Low | Docs/hygiene | `codebase-audit-2026-06.md` — all 11 issues **Resolved**, still in active maintenance dir | Finished point-in-time doc adds 213 lines of noise to the live section | Move to `archive/` | P2 |
| D-04 | Low | Docs/stale | `docs/04-development/testing.md` grep "coverage" → 0 | Testing guide predates the coverage gate and `test:docker:coverage` | Document both + the 60% threshold and ratchet plan | P1 (bundle with S-09) |
| D-05 | Low | Docs/stale | `02-docs-changelog.md` last entry 2026-06-23 | Doc changelog abandoned after one day of use | Backfill v1.1.0 entry or fold into `CHANGELOG.md` and delete | P2 |

---
*Method: every claim verified against `git diff 2f51961..HEAD`, current working tree, and vendor sources at the cited paths. Tests not executed (AGENTS.md test-stack rule). "Not found" statements are grep-verified.*
