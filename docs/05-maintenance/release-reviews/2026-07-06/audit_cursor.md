# XFlickr Codebase Audit (Cursor)

## Purpose of Audit

Evaluate the current XFlickr codebase (Laravel 12 backend + React 19 / Inertia 3 frontend) against:

- **Principles:** SOLID, DRY, KISS, YAGNI
- **PHP design patterns:** [refactoring.guru/design-patterns/php](https://refactoring.guru/design-patterns/php)
- **Laravel standards:** mandated flow `Controller → FormRequest → Service → Repository → Model`
- **React / Node.js standards:** component composition, hooks, typed API layer, Inertia page patterns
- **Project-specific rules:**
  - Controllers grouped by business feature (e.g. `MovieController` for all movie CRUD + views); no `__invoke` single-action controllers; no unnecessary splits like `MovieSearchController`
  - One Service per business concern (or tightly related concerns); domain events fired from the Service layer
  - Jobs delegate business logic to Services only
  - Services must not depend on each other bidirectionally — introduce orchestrator Service C when both directions are needed
  - Constructor DI when a dependency is used across most methods; method-level `app($class)` only for single-method use
  - Design patterns used to reduce complexity, eliminate duplication, and centralize logic
  - Consistency across the codebase; anything inconsistent should be listed for refactor

This audit is **read-only** — no source files were modified. Findings were verified against actual source code, not assumed from docs/skills alone (some AI-generated guides may be outdated or incorrect).

## Expectation

A concrete, evidence-backed issue list with file references. Each issue explains which principle/rule is broken, why it matters, how to fix it, and the risk of leaving it unfixed — so issues can be triaged and scheduled individually. After fixes are implemented, an AI agent must re-audit affected areas and report back (see [AI Check / Report-Back](#ai-check--report-back-after-implementation)).

---

## Format Check

| Section | Required | Status / Check |
|---|---|---|
| Purpose of audit | Yes | ✅ Present |
| Expectation | Yes | ✅ Present |
| Issue index with Status column | Yes | ✅ Present |
| Detail per issue (Break / Explain / Fix / Risk) | Yes | ✅ Present |
| Compliant areas documented | Yes | ✅ Present |
| AI check / report-back after implementation | Yes | ✅ Present |
| Issues verified against source (not docs alone) | Yes | ✅ Verified |
| Separated by Backend / Frontend sections | Yes | ✅ Present |

---

## Audit Results — Issue Index

### Backend

| # | Issue | Severity | Status / Check |
|---|---|---|---|
| [BE-1](#issue-be-1-storage-provider-dispatch-lacks-strategy-pattern) | Storage provider `match()` dispatch lacks Strategy pattern (OCP/DIP) | High | ⬜ Open |
| [BE-2](#issue-be-2-r2-download-logic-lives-in-controller) | `Api/StorageBrowseController::download()` implements R2 streaming inline | High | ⬜ Open |
| [BE-3](#issue-be-3-settings-controller-bypasses-service-layer) | `SettingsController` injects `StorageAccountRepository` directly | High | ⬜ Open |
| [BE-4](#issue-be-4-controllers-call-flickrservice-facade) | Controllers call `FlickrService` facade instead of app Services | High | ⬜ Open |
| [BE-5](#issue-be-5-query-services-couple-to-http-layer) | Query Services accept FormRequests and return `JsonResponse` | High | ⬜ Open |
| [BE-6](#issue-be-6-services-mutate-models-bypassing-repositories) | Services mutate Eloquent models directly, bypassing Repositories | High | ⬜ Open |
| [BE-7](#issue-be-7-transfer-index-performs-write-side-effects) | `TransferProgressQueryService::index()` reconciles batches during read | High | ⬜ Open |
| [BE-8](#issue-be-8-deprecated-jobs-use-repositories) | Deprecated contact jobs call Repositories in `handle()` | High | ⬜ Open |
| [BE-9](#issue-be-9-photo-transfer-controllers-contain-orchestration) | Photo download/upload controllers contain branching orchestration | Medium | ⬜ Open |
| [BE-10](#issue-be-10-flickrcontactcontroller-show-orchestrates-four-services) | `FlickrContactController::show` composes four Services in controller | Medium | ⬜ Open |
| [BE-11](#issue-be-11-oauth-connect-actions-skip-formrequests) | OAuth `connect` / `reauthorize` bypass FormRequest validation | Medium | ⬜ Open |
| [BE-12](#issue-be-12-runtimeconfig-destroyreset-lack-formrequests) | `RuntimeConfigController` destroy/reset lack FormRequests | Medium | ⬜ Open |
| [BE-13](#issue-be-13-apistoragebrowsecontroller-is-overloaded) | `Api/StorageBrowseController` is a god controller (~235 lines) | Medium | ⬜ Open |
| [BE-14](#issue-be-14-no-repository-interfaces) | No Repository interfaces registered (DIP gap vs documented standard) | Medium | ⬜ Open |
| [BE-15](#issue-be-15-flickrservice-facade-used-in-services) | `FlickrService` package facade used inside app Services | Medium | ⬜ Open / Document |
| [BE-16](#issue-be-16-inconsistent-di-vs-method-level-app) | Inconsistent constructor DI vs method-level `app()` usage | Medium | ⬜ Open |
| [BE-17](#issue-be-17-service-dispatches-job-creating-coupling) | `PhotoUploadExecutionService` dispatches `DownloadPhotoJob` from Service | Medium | ⬜ Open |
| [BE-18](#issue-be-18-no-domain-events-in-service-layer) | No domain events dispatched from Service layer | Medium | ⬜ Open / Watch |
| [BE-19](#issue-be-19-presenters-live-under-services-namespace) | Presenter classes live under `app/Services/` | Low | ⬜ Open |
| [BE-20](#issue-be-20-web-storagebrowsecontroller-duplicates-actions) | Web `StorageBrowseController` has four near-identical actions | Low | ⬜ Open |
| [BE-21](#issue-be-21-controllers-do-not-extend-base-controller) | Controllers do not extend base `Controller`; mostly method injection | Low | ⬜ Open / Document |
| [BE-22](#issue-be-22-duplicate-r2-metadata-logic) | Duplicate R2 metadata logic in upload/download Services | Low | ⬜ Open |
| [BE-23](#issue-be-23-flickrauthcontroller-manages-session-state) | `FlickrAuthController` manages OAuth session in controller | Low | ⬜ Open |
| [BE-24](#issue-be-24-crawlbulk-loop-in-controller) | `FlickrContactController::crawlBulk` loops crawl calls in controller | Low | ⬜ Open |
| [BE-25](#issue-be-25-photo-transfer-split-across-two-controllers) | Photo transfer split across `PhotoDownloadController` / `PhotoUploadController` | Low | ⬜ Open / Watch |

### Frontend

| # | Issue | Severity | Status / Check |
|---|---|---|---|
| [FE-1](#issue-fe-1-conflicting-accountlabel-implementations) | Conflicting `accountLabel` implementations across lib/pages | High | ⬜ Open |
| [FE-2](#issue-fe-2-crawl-summary-polling-duplicated) | Crawl summary polling duplicated in three places | High | ⬜ Open |
| [FE-3](#issue-fe-3-unused-crawl_state-on-contact-show) | `crawl_state` sent by backend but not wired on Contact Show | Medium | ⬜ Open |
| [FE-4](#issue-fe-4-polling-interval-logic-not-abstracted) | Polling interval logic repeated without shared hook | Medium | ⬜ Open |
| [FE-5](#issue-fe-5-catalog-photosetsgalleries-near-duplicates) | Catalog Photosets/Galleries pages are ~85% duplicate | Medium | ⬜ Open |
| [FE-6](#issue-fe-6-operations-transfer-progress-column-duplicated) | Operations page duplicates transfer progress column JSX | Medium | ⬜ Open |
| [FE-7](#issue-fe-7-settings-credential-panels-duplicate-ui) | Settings credential panels duplicate modal/form UI | Medium | ⬜ Open |
| [FE-8](#issue-fe-8-raw-buttons-bypass-design-system) | Raw styled buttons/links bypass `Button` design system | Medium | ⬜ Open |
| [FE-9](#issue-fe-9-useremotedatatable-has-no-error-surface) | `useRemoteDataTable` has no error state | Medium | ⬜ Open |
| [FE-10](#issue-fe-10-loose-status-typing) | Crawl/transfer status fields typed as loose `string` | Medium | ⬜ Open |
| [FE-11](#issue-fe-11-contactsindex-is-a-god-page) | `Contacts/Index.tsx` mixes too many concerns (~325 lines) | Medium | ⬜ Open |
| [FE-12](#issue-fe-12-actionbutton-referenced-but-missing) | `ActionButton` referenced in rules/docs but file does not exist | Medium | ⬜ Open |
| [FE-13](#issue-fe-13-flickr-id-link-components-structurally-identical) | Flickr `*IdLinks` components are structurally identical | Low | ⬜ Open |
| [FE-14](#issue-fe-14-pageprops-index-signature) | `PageProps` index signature weakens per-page typing | Low | ⬜ Open |
| [FE-15](#issue-fe-15-settings-types-not-centralized) | Settings page types duplicated, not in `types.ts` | Low | ⬜ Open |
| [FE-16](#issue-fe-16-inconsistent-inertia-prop-access) | Mixed `usePage().props` vs destructured props pattern | Low | ⬜ Open |
| [FE-17](#issue-fe-17-mixed-paginationstrategies) | Three pagination/sort strategies without documented rationale | Low | ⬜ Open / Document |
| [FE-18](#issue-fe-18-formatnumber-locale-scattered) | Number/locale formatting scattered across pages | Low | ⬜ Open |
| [FE-19](#issue-fe-19-usecrawloperations-dual-refresh) | `useCrawlOperations` couples API polling with Inertia reload | Low | ⬜ Open |

---

## Compliant Areas (Verified)

| Area | Status / Check | Evidence |
|---|---|---|
| No `__invoke` controllers | ✅ Pass | Grep across `app/` — zero matches |
| Controllers avoid direct DB/Model queries | ✅ Pass | No `Model::query()`, `DB::` in controllers |
| Active jobs delegate to Services | ✅ Pass | `DownloadPhotoJob`, `UploadPhotoJob` call execution Services in `handle()` |
| No bidirectional Service dependencies | ✅ Pass | Cross-service import graph is one-way only |
| FormRequests for most mutations/API reads | ✅ Pass | 36 FormRequest classes; catalog, crawl, transfer endpoints covered |
| Repository layer for app models | ✅ Pass | 17 repositories; crawler reads in `Repositories/Crawler/` |
| Central API client on frontend | ✅ Pass | All JSON via `lib/apiClient.ts`; no raw `fetch` in components |
| No explicit `any` in TS/TSX | ✅ Pass | Grep across `resources/js` — zero `any` usage |
| `CrawlActionBar` enforcement | ✅ Pass | Pages use `CrawlActionBar`; `CrawlDropdown` not imported from pages |
| Controller grouping by feature | ✅ Mostly pass | `FlickrContactController`, `FlickrAccountController`, `Api/CatalogController` follow feature grouping; minor splits (BE-25) are acceptable but worth watching |
| Shared table infrastructure | ✅ Pass | `DataTable` + `useTableSort` + `useRemoteDataTable` + `useCatalogOwnerNsidTable` |

---

## Issue Details — Backend

### Issue BE-1: Storage provider dispatch lacks Strategy pattern

**Break rule — OCP / DIP (SOLID):** Adding a new storage provider requires editing `match()` blocks in multiple Services instead of registering a new strategy implementation.

**Explain:** `StorageBrowseService`, `StorageUploadService`, `StorageDeleteService`, and `StorageFlysystemFactory` all use `match ($driver)` to select provider-specific implementations. This is a manual dispatch table, not a Strategy/Registry pattern. Each new provider touches 4+ files and violates Open/Closed Principle.

**Files:**
- `app/Services/Storage/StorageBrowseService.php` (lines 50–55)
- `app/Services/Storage/StorageUploadService.php` (line 129+)
- `app/Services/Storage/StorageDeleteService.php` (line 39+)
- `app/Services/Storage/StorageFlysystemFactory.php` (line 30+)

**What / Why / How to fix:**
- **What:** Introduce `StorageProviderStrategy` interface (or per-capability interfaces: `StorageBrowseStrategy`, `StorageUploadStrategy`, etc.) with one implementation per driver.
- **Why:** Centralizes provider registration; new drivers are added by binding a new class, not editing `match` arms.
- **How:** Create `StorageProviderRegistry` that maps `StorageDriver` → strategy; inject registry into facade Services. Alternatively use Laravel tagged bindings.

**Risk:** High maintenance cost and regression risk every time a storage driver is added or behavior diverges per provider.

---

### Issue BE-2: R2 download logic lives in controller

**Break rule — SRP / mandated flow:** Business logic (R2 config resolution, stream read, MIME detection) is in the controller, not a Service.

**Explain:** `Api/StorageBrowseController::download()` (lines 154–207) resolves credentials, builds object key, calls `StorageFlysystemFactory`, checks existence, reads stream, and builds `StreamedResponse`. `StorageDownloadService` already has R2 download-to-path logic but is unused by this endpoint.

**What / Why / How to fix:**
- **What:** Add `StorageDownloadService::streamResponse(StorageAccount $account, string $remotePath): StreamedResponse`.
- **Why:** Keeps controller thin; reuses existing R2 knowledge; testable in isolation.
- **How:** Move lines 177–204 into Service; controller becomes a one-line delegate after FormRequest validation.

**Risk:** Untested streaming logic in controller; harder to add provider download support later.

---

### Issue BE-3: Settings controller bypasses service layer

**Break rule — Layered architecture / DIP:** Controller injects Repository directly.

**Explain:** `SettingsController::index()` injects `StorageAccountRepository` and calls `listOrderedForSettings()` directly (lines 27–28, 47–49), bypassing `StorageAccountService` or `StorageAppProfileService`.

**What / Why / How to fix:**
- **What:** Add `StorageAccountService::listForSettings(): Collection` (or extend `StorageAppProfileService`).
- **Why:** Consistent layering; settings page logic can evolve without controller knowing persistence details.
- **How:** Move repository call + presenter mapping into Service; controller injects Service only.

**Risk:** Settings page becomes a second entry point for storage account queries, encouraging more controller→repo shortcuts.

---

### Issue BE-4: Controllers call FlickrService facade

**Break rule — Mandated flow:** Controllers must delegate to app Services, not package facades.

**Explain:**
- `CrawlOperationsController::index()` — `FlickrService::connections()->list()` (lines 16–18)
- `FlickrAccountController::list()` — same pattern (lines 25–27)

Both map results with `ConnectionPresenter` in the controller.

**What / Why / How to fix:**
- **What:** Add `FlickrConnectionListService::listForInertia(): Collection` wrapping facade + presenter.
- **Why:** Single place for account listing; controllers stay thin; easier to mock in tests.
- **How:** Inject new Service; replace facade calls in both controllers.

**Risk:** Duplicated facade usage spreads; package coupling leaks into presentation layer.

---

### Issue BE-5: Query services couple to HTTP layer

**Break rule — SRP / DIP:** Services depend on HTTP FormRequests and return HTTP responses.

**Explain:**
- `CatalogQueryService` — imports `ListPhotosRequest` etc.; methods like `photosResponse()` return `JsonResponse` (lines 7–10, 27–40)
- `CrawlStatusQueryService` — same pattern with `ListCrawlRunsRequest`
- `TransferProgressQueryService` — same with `ListTransferBatchesRequest`; also calls `abort(404)` (line 28)

Services should not know about HTTP. Controllers should build responses from Service return values.

**What / Why / How to fix:**
- **What:** Services return DTOs/arrays/`LengthAwarePaginator`; controllers wrap in `response()->json()`.
- **Why:** Services become reusable from Jobs, CLI, tests without HTTP stack.
- **How:** Pass validated primitives from FormRequest accessor methods (e.g. `$request->ownerNsid()` extracted in controller, passed to Service).

**Risk:** Services cannot be reused outside HTTP; harder to unit test; HTTP concerns leak into business layer.

---

### Issue BE-6: Services mutate models bypassing repositories

**Break rule — Mandated flow:** Persistence mutations should go through Repository methods.

**Explain:** `StorageAccountService` calls `$account->update()` (lines 37, 64, 87) and `$account->delete()` (line 76) directly on Eloquent models returned from the repository. `StorageBrowseSyncService` calls `$state->save()` / `$state->update()` directly. `TransferBatchRepository::updateCounts()` also calls `$batch->update()` inline.

**What / Why / How to fix:**
- **What:** Add repository methods: `updateDefaultFlag()`, `updateCredentials()`, `deleteAccount()`, `saveSyncState()`, etc.
- **Why:** Single persistence gateway; easier to audit writes and add caching/events later.
- **How:** Refactor Service methods to call repo mutations; Services hold business rules only.

**Risk:** Scattered write paths; repository layer becomes read-only in practice, defeating its purpose.

---

### Issue BE-7: Transfer index performs write side-effects

**Break rule — SRP / CQRS:** A read/query endpoint mutates data.

**Explain:** `TransferProgressQueryService::index()` (lines 75–80): when `isActive()`, loops batches and calls `$this->batchReconciler->reconcile($batch)` then re-fetches. Listing active transfers triggers reconciliation writes.

**What / Why / How to fix:**
- **What:** Keep `index()` read-only; reconcile in execution Services/Jobs or expose `POST /transfers/reconcile`.
- **Why:** Predictable API semantics; avoids surprise DB writes on poll.
- **How:** Move reconciliation to `TransferBatchReconciler` invoked from job completion or a dedicated command.

**Risk:** Race conditions under concurrent polling; performance degradation; violates principle of least surprise for API consumers.

---

### Issue BE-8: Deprecated jobs use repositories

**Break rule — Jobs delegate to Services only.**

**Explain:**
- `DownloadContactsPhotosJob` — injects `ConnectionQueryRepository`, resolves connection, then calls `PhotoDownloadService` (lines 30–36)
- `UploadContactsPhotosJob` — injects `ConnectionQueryRepository` + `StorageAccountRepository` (lines 32–47)

Both are marked `@deprecated` but remain for in-flight queue jobs.

**What / Why / How to fix:**
- **What:** Add `PhotoDownloadService::queueForConnectionKey(string $key, string $nsid)` and `PhotoUploadService::queueForConnectionKey(...)`.
- **Why:** Jobs become one-line delegates even for legacy payloads.
- **How:** Encapsulate lookup + queueing in Service; remove repo injection from Jobs; drain queue then delete Jobs.

**Risk:** Legacy jobs perpetuate wrong pattern; new contributors may copy the anti-pattern.

---

### Issue BE-9: Photo transfer controllers contain orchestration

**Break rule — DRY / thin controller.**

**Explain:** `PhotoDownloadController::store()` (lines 18–62) and `PhotoUploadController::store()` contain nearly identical branching: single photo → contact list → single contact → all contacts, with duplicated flash-message logic.

**What / Why / How to fix:**
- **What:** Extract `PhotoTransferQueueService::queueFromDownloadRequest()` / `queueFromUploadRequest()` returning a result DTO with message + count.
- **Why:** One place for queue orchestration; controllers become 3–5 lines.
- **How:** Shared private methods or a single orchestrator Service used by both controllers.

**Risk:** Bug fixes must be applied twice; divergent behavior between download and upload over time.

---

### Issue BE-10: FlickrContactController show orchestrates four services

**Break rule — SRP (controller as composer).**

**Explain:** `FlickrContactController::show()` (lines 93–117) wires `ContactListQueryService`, `ContactCatalogCountsService`, `ContactCrawlStateService`, `ContactCatalogDetailStatsService` and assembles Inertia props. Also sends duplicate `contact` + `contact_detail` props (lines 113–114).

**What / Why / How to fix:**
- **What:** Introduce `ContactDetailService::forShow(Connection, string $nsid): array`.
- **Why:** Contact detail page data is one business operation; controller delegates once.
- **How:** Move composition into Service; remove duplicate prop.

**Risk:** Contact show page logic scattered; hard to add new detail sections consistently.

---

### Issue BE-11: OAuth connect actions skip FormRequests

**Break rule — FormRequest mandate.**

**Explain:**
- `FlickrAuthController::connect()` uses raw `Request` for `app_profile` query param (line 18)
- `StorageAuthController` uses raw `Request` for `account_id`, `return_url` on connect/reauthorize

Callback actions correctly use FormRequests (`FlickrOAuthCallbackRequest`, `StorageOAuthCallbackRequest`).

**What / Why / How to fix:**
- **What:** Add `FlickrOAuthConnectRequest`, `StorageOAuthConnectRequest` with validated query params.
- **Why:** Consistent validation; `sanitizeReturnUrl()` belongs in FormRequest or Support class.
- **How:** Create FormRequests; move session prep into `FlickrOAuthService::begin()` return value where possible.

**Risk:** Unvalidated query input; inconsistent error handling for OAuth entry points.

---

### Issue BE-12: RuntimeConfig destroy/reset lack FormRequests

**Break rule — FormRequest mandate.**

**Explain:** `RuntimeConfigController::store()` uses `StoreRuntimeConfigRequest`, but `destroy(string $path)` and `reset(string $path)` accept raw route params without validation (lines 22–33).

**What / Why / How to fix:**
- **What:** Add `DestroyRuntimeConfigRequest` / `ResetRuntimeConfigRequest` validating/decoding `path`.
- **Why:** Consistent with other runtime config endpoints; path validation centralized.
- **How:** FormRequest with `prepareForValidation()` for path decoding.

**Risk:** Invalid paths handled ad hoc; authorization rules not co-located with validation.

---

### Issue BE-13: Api/StorageBrowseController is overloaded

**Break rule — SRP / thin controller.**

**Explain:** ~235 lines. Contains provider parsing, auth error heuristics (`browseErrorResponse`, lines 219–234), reauthorization responses, and inline download (BE-2). Multiple responsibilities in one class.

**What / Why / How to fix:**
- **What:** Extract `StorageBrowseOrchestrator` or split action Services (`StorageBrowseAction`, `StorageSyncAction`, `StorageDeleteAction`).
- **Why:** Each endpoint concern testable independently.
- **How:** Move private helper methods to dedicated Support or Service classes.

**Risk:** Controller becomes unmaintainable as storage features grow.

---

### Issue BE-14: No repository interfaces

**Break rule — DIP:** Documented standard says "Depend on Repository interfaces"; zero `interface *Repository` in `app/`.

**Explain:** `RepositoryServiceProvider` binds concrete classes only. Services type-hint concrete repositories everywhere.

**What / Why / How to fix:**
- **What:** Either implement interfaces for core repos OR update `application-standards.md` to state concrete repos are intentional (YAGNI).
- **Why:** Avoid doc/code drift; clarify testability strategy.
- **How:** If interfaces: create `StorageAccountRepositoryInterface`, bind in provider. If YAGNI: document decision.

**Risk:** False expectation from docs; harder to swap implementations if ever needed.

---

### Issue BE-15: FlickrService facade used in services

**Break rule — Strict repository boundary (partial; may be intentional package boundary).**

**Explain:** App Services call `FlickrService::connections()` directly:
- `FlickrOAuthService` (throughout)
- `DashboardService` (line 35)
- `FlickrCrawlService` (lines 23–41)

Crawler package owns `Connection` persistence; app has `ConnectionQueryRepository` for reads only.

**What / Why / How to fix:**
- **What:** Document as explicit package boundary exception OR add `ConnectionCommandRepository` in app layer wrapping facade.
- **Why:** Clarifies where crawler package ends and app layer begins.
- **How:** Architecture doc update + optional adapter class.

**Risk:** Unclear ownership of connection mutations; harder to mock in app-level tests.

---

### Issue BE-16: Inconsistent DI vs method-level app()

**Break rule — DI policy:** Constructor DI for whole-class deps; `app()` for single-method only.

**Explain:**
- `Api/StorageBrowseController::download()` — `app(StorageFlysystemFactory::class)` (line 181)
- `DownloadPhotoJob::failed()` / `UploadPhotoJob::failed()` — `app(Photo*ExecutionService::class)` (line 54)
- `StorageFlysystemFactory::oneDriveDisk()` — `app(StorageMicrosoftTokenService::class)` (line 101)
- `SettingsController` — `app()->bound('config-store')` (line 59)

Some are legitimate single-method use; others (`StorageFlysystemFactory`) use `app()` in a class that already has constructor deps.

**What / Why / How to fix:**
- **What:** Audit each `app()` call; inject via constructor where used in multiple methods; document exceptions.
- **Why:** Consistent, testable dependency graph.
- **How:** Constructor inject `StorageFlysystemFactory` into controller; inject token service into factory.

**Risk:** Hidden dependencies; harder to trace service container resolution.

---

### Issue BE-17: Service dispatches job creating coupling

**Break rule — Layer coupling (Service → Job → Service chain).**

**Explain:** `PhotoUploadExecutionService` (lines 47–52) dispatches `DownloadPhotoJob` when local file is missing. `PhotoUploadService` and `PhotoDownloadService` also dispatch jobs directly.

**What / Why / How to fix:**
- **What:** Introduce `PhotoTransferCoordinator` orchestrator or a `QueuePort` abstraction.
- **Why:** Breaks circular Service↔Job coupling; orchestrator owns cross-pipeline workflows.
- **How:** Coordinator decides download-then-upload; execution Services stay focused.

**Risk:** Tight coupling between upload and download pipelines; hard to change queue strategy.

---

### Issue BE-18: No domain events in service layer

**Break rule — Project expectation:** Events should be fired from Services.

**Explain:** Grep for `event(`, `Event::dispatch` across `app/` returns zero matches. State changes (account connect, crawl start, transfer complete) do not emit domain events.

**What / Why / How to fix:**
- **What:** If events are needed: add Events + Listeners for key transitions (e.g. `StorageAccountConnected`, `CrawlStarted`, `TransferBatchCompleted`).
- **Why:** Enables decoupled side effects (notifications, metrics) without bloating Services.
- **How:** Start with highest-value transitions; fire from Service after successful repo write.
- **Note:** May be YAGNI if no listeners exist yet — mark as Watch unless product needs async reactions.

**Risk:** Future features (webhooks, audit log, notifications) require invasive Service edits.

---

### Issue BE-19: Presenters live under Services namespace

**Break rule — Naming/structure consistency.**

**Explain:** `app/Services/Flickr/FlickrRateLimitPresenter.php`, `ContactListPresenter.php` are presentation helpers, not business Services. `ConnectionPresenter` correctly lives in `app/Support/Flickr/`.

**What / Why / How to fix:**
- **What:** Move to `app/Presenters/` or `app/Support/Flickr/`.
- **Why:** Clear separation of presentation vs business logic.
- **How:** Move files; update imports.

**Risk:** Low; mainly discoverability and misleading namespace.

---

### Issue BE-20: Web StorageBrowseController duplicates actions

**Break rule — DRY / KISS.**

**Explain:** `StorageBrowseController` has `googlePhotos()`, `googleDrive()`, `oneDrive()`, `r2()` — each calls `render()` with a different enum (lines 13–31).

**What / Why / How to fix:**
- **What:** Single route `storages/{provider}` with `StorageDriver::fromRouteSlug()`.
- **Why:** One action instead of four.
- **How:** Route refactor + one `show(StorageDriver $driver)` method.

**Risk:** Low; maintenance noise when adding providers.

---

### Issue BE-21: Controllers do not extend base Controller

**Break rule — Structural consistency (minor).**

**Explain:** All 20 concrete controllers are `final class X` without extending `app/Http/Controllers/Controller.php`. Most use Laravel method injection rather than constructor DI.

**What / Why / How to fix:**
- **What:** Align with project skill (constructor DI for recurring deps) OR document method injection as intentional Laravel convention.
- **Why:** Avoid conflicting guidance.
- **How:** Pick one convention; update skill/docs.

**Risk:** Low; stylistic unless skills mandate constructor DI strictly.

---

### Issue BE-22: Duplicate R2 metadata logic

**Break rule — DRY.**

**Explain:** R2 `headObject`/etag logic duplicated in `StorageUploadService` (lines 100–127) and `StorageDownloadService` (lines 89–113).

**What / Why / How to fix:**
- **What:** Extract `R2ObjectMetadataService` or add method on `StorageFlysystemFactory`.
- **Why:** Single source for R2 metadata reads.
- **How:** Private trait or shared Support class.

**Risk:** Low; bug fixes applied inconsistently.

---

### Issue BE-23: FlickrAuthController manages session state

**Break rule — Thin controller.**

**Explain:** `FlickrAuthController::connect()` writes OAuth tokens to session (lines 35–39). `callback()` reads session via FormRequest.

**What / Why / How to fix:**
- **What:** Move session read/write into `FlickrOAuthService::begin()` / `complete()`.
- **Why:** OAuth flow is one business operation; controller only redirects.
- **How:** Service returns redirect URL + stores session internally or via dedicated `OAuthSessionStore`.

**Risk:** Low; OAuth state scattered between controller and service.

---

### Issue BE-24: crawlBulk loop in controller

**Break rule — Thin controller.**

**Explain:** `FlickrContactController::crawlBulk()` (lines 129–130) loops `$contactNsids` calling `$crawlService->crawlMany()` per contact.

**What / Why / How to fix:**
- **What:** Add `FlickrCrawlService::crawlManyContacts(Connection, array $types, array $nsids)`.
- **Why:** Bulk crawl is one business operation.
- **How:** Move loop into Service.

**Risk:** Low; bulk behavior harder to extend (rate limiting, batching).

---

### Issue BE-25: Photo transfer split across two controllers

**Break rule — Controller grouping (watch item).**

**Explain:** `PhotoDownloadController` and `PhotoUploadController` are separate single-action controllers. Project rule prefers feature-grouped controllers (e.g. one `PhotoTransferController` with download + upload actions).

**What / Why / How to fix:**
- **What:** Merge into `PhotoTransferController` with `download()` and `upload()` actions OR document intentional split (different routes/queues).
- **Why:** Align with stated controller grouping convention.
- **How:** Merge if simplicity wins; keep split if upload/download lifecycles diverge significantly.

**Risk:** Low; mostly consistency. Current split is not wrong functionally.

---

## Issue Details — Frontend

### Issue FE-1: Conflicting accountLabel implementations

**Break rule — DRY / single source of truth.**

**Explain:**
- `lib/breadcrumbs.ts` — `account.username ?? account.nsid` (line 6)
- `lib/crawlOperations.ts` — `account.fullname || account.username || account.nsid` (line 8)
- `Pages/Dashboard.tsx` — uses fullname-first pattern

Same account can display different labels in breadcrumbs vs operations vs dashboard.

**What / Why / How to fix:**
- **What:** One `accountDisplayName(account)` in `lib/flickrAccount.ts`.
- **Why:** User-visible consistency.
- **How:** Pick one precedence rule; replace all call sites.

**Risk:** Confusing UX; hard to grep for display bugs.

---

### Issue FE-2: Crawl summary polling duplicated

**Break rule — DRY / separation of concerns.**

**Explain:** Nearly identical `Promise.all` + `apiGet<CrawlSummary>` + 10s interval in:
- `Pages/Flickr/Index.tsx` (lines 51–76)
- `Components/Settings/FlickrCredentialsPanel.tsx` (lines 53–79)

**What / Why / How to fix:**
- **What:** Extract `useCrawlSummaries(accounts, { intervalMs: 10000 })`.
- **Why:** Single place for crawl summary fetching logic.
- **How:** Hook returns `{ summaries, loading }`; consume from both sites.

**Risk:** Polling bugs fixed in one place only; unnecessary network load if intervals diverge.

---

### Issue FE-3: Unused crawl_state on Contact Show

**Break rule — KISS / complete features.**

**Explain:** Backend sends `crawl_state` and duplicate `contact_detail` (`FlickrContactController` lines 113–116). `Contacts/Show.tsx` declares both in `Props` but only destructures `account`, `contact`, `catalog_stats` (line 52). `CrawlActionBar` does not receive `typeStates={crawl_state}` unlike `Contacts/Index`.

**What / Why / How to fix:**
- **What:** Wire `typeStates={crawl_state}` into `CrawlActionBar`; remove `contact_detail` duplicate from backend + frontend.
- **Why:** Contact detail crawl UI should match Index behavior.
- **How:** Destructure `crawl_state`; pass to `CrawlActionBar`.

**Risk:** Missing per-type crawl state on detail page; wasted SSR payload.

---

### Issue FE-4: Polling interval logic not abstracted

**Break rule — DRY / KISS.**

**Explain:** Each page reimplements `AbortController` + `setInterval` + silent `.catch()`:
- Dashboard (5s), Contacts/Index (3s), Flickr/Index (10s), `useCrawlOperations` (5s), FlickrCredentialsPanel (10s)

**What / Why / How to fix:**
- **What:** Generic `usePollingQuery(fn, { intervalMs, enabled })`.
- **Why:** Consistent abort/cleanup; less boilerplate.
- **How:** Shared hook in `hooks/usePollingQuery.ts`.

**Risk:** Memory leaks or duplicate intervals if cleanup differs per page.

---

### Issue FE-5: Catalog Photosets/Galleries near-duplicates

**Break rule — DRY.**

**Explain:** `Pages/Catalog/Photosets.tsx` and `Pages/Catalog/Galleries.tsx` (~120 lines each, ~85% identical). Same layout, columns, `CrawlActionBar` pattern; only entity type and fetch path differ.

**What / Why / How to fix:**
- **What:** `CatalogCollectionPage` with config object or shared column factory.
- **Why:** One place for catalog collection table behavior.
- **How:** Thin Inertia page wrappers per route; shared component/hook for table.

**Risk:** Feature drift between photosets and galleries tables.

---

### Issue FE-6: Operations transfer progress column duplicated

**Break rule — DRY.**

**Explain:** `Pages/Crawl/Operations.tsx` — Download section (~210–233) and Upload section (~297–311) render identical `ProgressBar` + completed/failed/total footer.

**What / Why / How to fix:**
- **What:** `TransferProgressCell({ batch })` component.
- **Why:** One render path for transfer progress.
- **How:** Extract component; use in both sections.

**Risk:** UI inconsistency between download and upload progress display.

---

### Issue FE-7: Settings credential panels duplicate UI

**Break rule — DRY / component composition.**

**Explain:** `FlickrCredentialsPanel.tsx` and `StorageCredentialsPanel.tsx` repeat raw `<button>`, `<input>`, modal open/close, `useForm` + `preserveScroll`. Both bypass `Button`/`Input` components per project rules.

**What / Why / How to fix:**
- **What:** Shared `SettingsFormModal`, `CredentialFormField`; use `Button` + `Input`.
- **Why:** Consistent settings UX; matches `ui-buttons.mdc`.
- **How:** Extract shared modal shell; migrate inputs to design system.

**Risk:** Inconsistent settings UI; harder to apply global style changes.

---

### Issue FE-8: Raw buttons bypass design system

**Break rule — Project conventions (`ui-buttons.mdc`, `react-inertia-frontend`).**

**Explain:** Raw Tailwind buttons/links in:
- `Pages/Dashboard.tsx` (lines 87–107)
- `Pages/Storage/Browse.tsx`
- `Pages/Settings/Index.tsx` (tab buttons)
- `Components/Settings/*`, `StorageReauthorizeBanner.tsx`, `CrawlDropdown.tsx`

`Button` + `buttonVariants.ts` exist but are not used consistently.

**What / Why / How to fix:**
- **What:** Replace raw patterns with `Button` variants incrementally.
- **Why:** Consistent interactive styling; single place for focus/hover/disabled states.
- **How:** Page-by-page migration; prioritize high-traffic pages.

**Risk:** Visual inconsistency; accessibility gaps in ad-hoc buttons.

---

### Issue FE-9: useRemoteDataTable has no error surface

**Break rule — Separation of concerns / UX resilience.**

**Explain:** `hooks/useRemoteDataTable.ts` uses `try/finally` without `catch`. Failures leave stale data with `loading: false` and no `error` state. Contrast: `useStorageBrowse` surfaces errors.

**What / Why / How to fix:**
- **What:** Return `{ error, reload }` from hook; show error banner on catalog pages.
- **Why:** Users see failed API calls instead of stale data.
- **How:** Add `catch` setting error state; optional retry button.

**Risk:** Silent failures on catalog browse; hard to debug user reports.

---

### Issue FE-10: Loose status typing

**Break rule — Type safety.**

**Explain:** `types.ts` — `CrawlRun.status`, `TransferBatch.status` typed as `string`. `StatusBadge` branches on known values but types allow any string. `fetchRunSortValue` in `crawlOperations.ts` uses inline partial type instead of `CrawlRun`.

**What / Why / How to fix:**
- **What:** Union types e.g. `type CrawlRunStatus = 'running' | 'completed' | 'failed' | ...`.
- **Why:** Compile-time checks for status handling.
- **How:** Define unions in `types.ts`; use in components and lib.

**Risk:** Typos in status comparisons go undetected at compile time.

---

### Issue FE-11: Contacts/Index is a god page

**Break rule — SRP / separation of concerns.**

**Explain:** `Pages/Contacts/Index.tsx` (~325 lines) inline: `CountLink`, `isContactSelectable`, search routing, progress polling, bulk actions, column definitions, pagination, sort.

**What / Why / How to fix:**
- **What:** Extract `useContactsProgressPoll`, `useContactsListNavigation`, `contactsTableColumns()`.
- **Why:** Page becomes composition root; logic testable in hooks.
- **How:** Move blocks to hooks/lib; keep page under ~100 lines.

**Risk:** Hard to modify contact list without regressions.

---

### Issue FE-12: ActionButton referenced but missing

**Break rule — Doc/code consistency.**

**Explain:** `.cursor/rules/ui-buttons.mdc` references `ActionButton` at `resources/js/Components/ActionButton.tsx`. File does not exist (glob returns zero). Pages import `ActionBar`, not `ActionButton`.

**What / Why / How to fix:**
- **What:** Either create `ActionButton` component OR update rules/docs to reference `Button` only.
- **Why:** AI agents and developers follow wrong guidance.
- **How:** Add thin wrapper around `Button` for action bars, or fix rule file.

**Risk:** Agents generate imports to non-existent component; inconsistent button guidance.

---

### Issue FE-13: Flickr ID link components structurally identical

**Break rule — DRY (low priority).**

**Explain:** `FlickrPhotoIdLinks`, `FlickrPhotosetIdLinks`, `FlickrGalleryIdLinks` — same props; only URL builder and `externalTitle` differ.

**What / Why / How to fix:**
- **What:** `FlickrEntityIdLink` with entity config map in `lib/catalog.ts`.
- **Why:** Less file churn when adding entities.
- **How:** Optional consolidation; named exports can remain as aliases.

**Risk:** Low; mild maintenance overhead.

---

### Issue FE-14: PageProps index signature

**Break rule — Type safety (Inertia best practice).**

**Explain:** `types.ts` lines 303–313 — `[key: string]: unknown` on `PageProps` allows prop name typos without compile errors when using `usePage<Props>()`.

**What / Why / How to fix:**
- **What:** Remove index signature; page-specific `interface Props extends PageProps`.
- **Why:** Stricter compile-time checks.
- **How:** Gradual migration per page.

**Risk:** Low; missed typos in prop access.

---

### Issue FE-15: Settings types not centralized

**Break rule — DRY / type centralization.**

**Explain:** `FlickrAppSummary` defined in both `Pages/Settings/Index.tsx` and `Components/Settings/FlickrCredentialsPanel.tsx`. Settings payload types overlap `types.ts` but are page-local.

**What / Why / How to fix:**
- **What:** Move to `types.ts` or `types/settings.ts`.
- **Why:** Single import for panels + page.
- **How:** Extract interfaces; update imports.

**Risk:** Low; type drift between panel and page.

---

### Issue FE-16: Inconsistent Inertia prop access

**Break rule — Consistency.**

**Explain:** `Dashboard.tsx` and `Crawl/Operations.tsx` use `usePage<Props>().props`; other pages destructure props in function signature.

**What / Why / How to fix:**
- **What:** Standardize on destructured props (preferred for Inertia).
- **Why:** Easier to scan page data requirements.
- **How:** Refactor two pages to match convention.

**Risk:** Low; style inconsistency only.

---

### Issue FE-17: Mixed pagination/sort strategies

**Break rule — Consistency / predictability.**

**Explain:** Three coexisting patterns:
- `Contacts/Index` — server-side via `router.get` + Inertia props
- `Catalog/*` — client-side via `useRemoteDataTable` + `/api/*`
- `Flickr/Index` — client sort on server props

**What / Why / How to fix:**
- **What:** Document when to use each in `frontend-standards.md`.
- **Why:** Prevents future "why double fetch?" confusion.
- **How:** Architecture note in docs; no forced unification unless warranted.

**Risk:** Low if documented; medium if new pages pick wrong pattern.

---

### Issue FE-18: formatNumber / locale scattered

**Break rule — DRY.**

**Explain:** `formatNumber` in `Dashboard.tsx`, `formatInApi` in `Contacts/Show.tsx`, ad-hoc `.toLocaleString()` in `CatalogStatCard`, `Pagination`, `Storage/Browse`. `lib/format.ts` has bytes/dates only.

**What / Why / How to fix:**
- **What:** Add `formatInteger(n)` / `formatNullableCount(n)` to `lib/format.ts`.
- **Why:** Consistent number display.
- **How:** Replace inline formatters.

**Risk:** Low; inconsistent null/zero display.

---

### Issue FE-19: useCrawlOperations dual refresh

**Break rule — SRP.**

**Explain:** `hooks/useCrawlOperations.ts` (lines 67–70): every 5s runs `router.reload({ only: ['accounts'] })` AND parallel `apiGet` for runs/transfers.

**What / Why / How to fix:**
- **What:** Use one refresh mechanism; document why both exist if truly needed.
- **Why:** Avoid redundant server round-trips.
- **How:** Fetch accounts via API too, or drop `router.reload` if account list is static on operations view.

**Risk:** Low; unnecessary load on operations page.

---

## AI Check / Report-Back After Implementation

When one or more issues from this audit are fixed, the implementing agent **must** run a targeted re-check and update this file.

### Required steps

1. **Identify fixed issues** — List issue IDs (e.g. BE-2, FE-1) addressed in the change set.
2. **Re-verify source** — Read changed files; confirm the violation is resolved, not just moved.
3. **Run quality gates** (as applicable):
   - `composer test:docker` (backend changes)
   - `npm run typecheck` (frontend changes)
   - `composer instructions:verify` (if instructions/skills touched)
4. **Update Status / Check** — In the issue index tables above, change `⬜ Open` to:
   - `✅ Fixed` — verified resolved
   - `🟡 Partial` — improved but not fully resolved (add note)
   - `⬜ Won't fix` — rejected with documented reason
5. **Add implementation note** below the issue detail (one line: PR/commit reference + what changed).
6. **Check for regressions** — Ensure fix did not introduce new violations (e.g. moving logic from controller to Service but adding new `app()` calls elsewhere).

### Report-back template

```markdown
## Audit Report-Back — YYYY-MM-DD

**Agent:** <tool name>
**Change scope:** <PR / commit / summary>

### Issues addressed
| ID | New Status | Verification |
|---|---|---|
| BE-2 | ✅ Fixed | `StorageDownloadService::streamResponse` added; controller delegates |
| FE-1 | ✅ Fixed | `accountDisplayName` in `lib/flickrAccount.ts`; all call sites updated |

### Quality gates
- [ ] composer test:docker
- [ ] npm run typecheck
- [ ] composer instructions:verify

### New issues found during re-audit
- <none | list new IDs>

### Notes
<optional context>
```

### Priority order for implementation (recommended)

**Backend (high impact first):**
1. BE-2, BE-5, BE-6, BE-7 — layer violations with highest architectural debt
2. BE-1 — Strategy pattern for storage providers
3. BE-3, BE-4 — controller bypass fixes
4. BE-8 — legacy jobs (after queue drain plan)
5. Medium/low items in index order

**Frontend (high impact first):**
1. FE-1, FE-2, FE-3 — user-visible consistency + largest DRY win
2. FE-9 — error handling on catalog pages
3. FE-5, FE-7, FE-8 — structural DRY + design system
4. FE-12 — fix doc/code drift for `ActionButton`
5. Low items as capacity allows

---

*Audit generated: 2026-06-23. Scope: 21 controllers, 40 services, 17 repositories, 4 jobs, 36 FormRequests, 11 Inertia pages, 83 frontend TS/TSX files. Method: source inspection with automated search; subagent exploration cross-checked against primary files.*
