# XFlickr — Consolidated Architecture Audit (Final)

**Date:** 2026-06-23  
**Sources merged:** `audit.md`, `audit_grok.md`, `audit_codex.md`, `audit_cursor.md`, `audit_sonnet.md`  
**Method:** Cross-audit deduplication + spot verification against source (not docs alone).  
**Scope:** Laravel 12 backend + React 19 / Inertia 3 frontend — read-only audit.

---

## Purpose

Evaluate XFlickr against:

- SOLID, DRY, KISS, YAGNI
- Mandated backend flow: `Controller → FormRequest → Service → Repository → Model`
- Jobs delegate business logic to Services only
- React composition, shared UI primitives, typed props
- Project rules: feature-grouped controllers, no circular Service dependencies, constructor DI only when a dependency is used across most methods

## Expectation

One deduplicated, evidence-backed issue list. Each issue names the violated rule, cites files, explains impact, proposes a fix, and states risk. Issues marked **Rejected** were dropped after source verification.

---

## Merge Summary

| Source | Raw issues | Notes |
|---|---|---|
| `audit.md` / `audit_grok.md` | 20 each | Identical content — counted once |
| `audit_codex.md` | 10 | 1 unique high-severity finding (DB transaction + remote API) |
| `audit_cursor.md` | 44 (25 BE + 19 FE) | Broadest scan; 1 false positive removed |
| `audit_sonnet.md` | 28 | Strong on transfer pipeline + performance findings |

**Final count:** 42 actionable issues (27 backend, 15 frontend) + 12 watch/low items + 4 rejected.

---

## Verified Compliant Areas

| Area | Status | Evidence |
|---|---|---|
| No `__invoke` single-action controllers | ✅ | Zero matches in `app/Http/Controllers` |
| No circular Service→Service constructor deps | ✅ | Dependency graph is one-way |
| Active jobs delegate to Services | ✅ | `DownloadPhotoJob`, `UploadPhotoJob` |
| No inline `->validate()` in controllers | ✅ | Codex scan |
| Controllers avoid direct Eloquent/DB queries | ✅ | Cursor scan |
| FormRequests used for most mutations/API reads | ✅ | 36 FormRequest classes |
| React: no `any` abuse; central `apiClient` | ✅ | Grep + cursor scan |
| `npm run typecheck` / `composer instructions:verify` | ✅ | Codex run at audit time |
| `CrawlActionBar` used on crawl surfaces | ✅ | Pages do not import `CrawlDropdown` directly |

---

## Rejected Findings (Not Included)

These appeared in one or more audits but were **invalid or overstated** after source check:

| Original claim | Verdict | Reason |
|---|---|---|
| `ActionButton` component missing (`audit_cursor` FE-12) | ❌ Rejected | `ActionButton` is exported from `resources/js/Components/ActionBar.tsx` and used in production. Only **docs** (`.cursor/rules/ui-buttons.mdc`) point to a non-existent `ActionButton.tsx` path — reframe as doc fix, not missing component. |
| `CatalogController` constructor-injects `FlickrOAuthService` wrongly (`audit_sonnet` #14) | ❌ Rejected | Dependency is used by all four catalog actions via `presentAccount()` — matches project constructor-DI rule. |
| Controllers must extend base `Controller` (`audit_cursor` BE-21) | ❌ Rejected | Stylistic preference; Laravel allows standalone controllers. Not an architecture violation. |
| `DashboardService::snapshot(FlickrRateLimitPresenter)` is leaky DI (`audit_sonnet` #2) | ⚠️ Downgraded to watch | Conflicts with project's method-level injection pattern praised elsewhere (`DashboardService`, `CrawlStatusQueryService`). Consistency nit, not a layer breach. |
| Repository interfaces required everywhere (`multiple`) | ⚠️ Watch only | Zero interfaces is consistent project-wide; YAGNI applies unless a second implementation is planned. Storage provider strategies (BE-01) are the real DIP gap. |
| Domain events missing (`multiple`) | ⚠️ Watch only | No listeners exist; Jobs cover async needs today. Add events when multiple subscribers are needed. |
| Deprecated jobs as active violations (`audit.md` notes) | ⚠️ Watch only | `@deprecated`, kept for in-flight queue payloads — track removal after queue drain, not urgent refactor. |

---

## Issue Index

### Backend — High Priority

| ID | Issue | Severity | Sources | Status |
|---|---|---|---|---|
| [BE-01](#be-01-storage-provider-match-dispatch-no-strategy) | Storage provider `match()` dispatch — no Strategy/Registry | High | All | ⬜ Open |
| [BE-02](#be-02-storage-download-logic-in-controller) | `Api/StorageBrowseController::download()` inline R2 streaming | High | All | ⬜ Open |
| [BE-03](#be-03-remote-api-calls-inside-db-transaction) | Storage sync performs provider API calls inside `DB::transaction()` | High | Codex | ⬜ Open |
| [BE-04](#be-04-upload-execution-dispatches-download-job) | `PhotoUploadExecutionService` dispatches `DownloadPhotoJob` (reuses upload `$batchId`) | High | Sonnet, Cursor | ⬜ Open |
| [BE-05](#be-05-filesystem-exists-on-contact-list) | `ContactDownloadCountsService` calls `Storage::exists()` per file on list page | High | Sonnet | ⬜ Open |
| [BE-06](#be-06-transfer-index-writes-on-read) | `TransferProgressQueryService::index()` reconciles batches during poll | High | Cursor | ⬜ Open |
| [BE-07](#be-07-query-services-coupled-to-http) | Query Services accept FormRequests and return `JsonResponse` | High | Cursor | ⬜ Open |
| [BE-08](#be-08-controllers-call-flickr-facade) | Controllers call `FlickrService` facade instead of app Services | High | Cursor, Sonnet | ⬜ Open |

### Backend — Medium Priority

| ID | Issue | Severity | Sources | Status |
|---|---|---|---|---|
| [BE-09](#be-09-oauth-connect-skips-formrequest) | OAuth `connect` / `reauthorize` bypass FormRequest validation | Medium | All | ⬜ Open |
| [BE-10](#be-10-photo-transfer-controller-orchestration) | Photo download/upload controllers contain dispatch branching | Medium | All | ⬜ Open |
| [BE-11](#be-11-contact-show-controller-orchestration) | `FlickrContactController::show()` composes four Services | Medium | All | ⬜ Open |
| [BE-12](#be-12-settings-controller-bypasses-service) | `SettingsController` injects `StorageAccountRepository` directly | Medium | Grok, Cursor | ⬜ Open |
| [BE-13](#be-13-formrequest-resolves-repository) | `QueuePhotoUploadRequest::resolvedStorageAccount()` calls repository via `app()` | Medium | Sonnet | ⬜ Open |
| [BE-14](#be-14-api-routes-layering-duplicates) | `routes/api.php` duplicates web download/upload; mixes web controllers | Medium | Grok | ⬜ Open |
| [BE-15](#be-15-repository-embeds-business-rules) | `StorageRemoteSyncStateRepository::appendReconcileSeenRemoteIds()` has business guards | Medium | Grok | ⬜ Open |
| [BE-16](#be-16-services-mutate-models-directly) | Services call `$model->update()` / `->save()` bypassing Repository write methods | Medium | Cursor | ⬜ Open |
| [BE-17](#be-17-storage-oauth-mutates-global-config) | `StorageOAuthService::provider()` writes to global `config()` | Medium | Sonnet | ⬜ Open |
| [BE-18](#be-18-storage-delete-driver-branch) | `StorageDeleteService` has `if ($driver === GooglePhotos)` post-delete branch | Medium | Sonnet | ⬜ Open |
| [BE-19](#be-19-contact-show-duplicate-unused-props) | Duplicate `contact` / `contact_detail`; `crawl_state` not wired in UI | Medium | Sonnet, Cursor | ⬜ Open |
| [BE-20](#be-20-contact-index-resanitizes-sort) | `FlickrContactController::index()` re-sanitizes sort/direction already in FormRequest | Medium | Sonnet | ⬜ Open |
| [BE-21](#be-21-status-string-literals) | Transfer/download status strings repeated as raw literals | Medium | Sonnet | ⬜ Open |
| [BE-22](#be-22-crawler-repository-pattern-drift) | Two repository base patterns + duplicate pagination boilerplate in Crawler repos | Medium | Grok | ⬜ Open |
| [BE-23](#be-23-api-storage-browse-controller-overloaded) | `Api/StorageBrowseController` mixes browse/sync/delete/download + error heuristics | Medium | Cursor | ⬜ Open |
| [BE-24](#be-24-runtime-config-missing-formrequests) | `RuntimeConfigController::destroy` / `reset` lack FormRequests | Medium | Cursor | ⬜ Open |
| [BE-25](#be-25-models-expose-presentation) | `StorageRemoteAlbum` / `StorageRemoteItem::toBrowseArray()` on Models | Medium | Grok | ⬜ Open |

### Backend — Low / Watch

| ID | Issue | Severity | Sources | Status |
|---|---|---|---|---|
| [BE-W1](#be-w1-repository-interfaces-yagni) | Concrete repository bindings only — DIP watch item | Low | All | ⬜ Watch |
| [BE-W2](#be-w2-no-domain-events) | No domain events from Services | Low | Cursor, Grok | ⬜ Watch |
| [BE-W3](#be-w3-photo-transfer-split-controllers) | `PhotoDownloadController` + `PhotoUploadController` could merge | Low | Grok, Cursor | ⬜ Watch |
| [BE-W4](#be-w4-presenter-dto-namespace-drift) | Presenters/DTOs/Sorters live under `Services/` inconsistently | Low | Grok, Sonnet, Cursor | ⬜ Open |
| [BE-W5](#be-w5-web-storage-browse-duplicate-actions) | Web `StorageBrowseController` has four near-identical provider actions | Low | Cursor | ⬜ Open |
| [BE-W6](#be-w6-duplicate-r2-metadata-logic) | R2 `headObject`/etag logic duplicated in upload/download Services | Low | Cursor | ⬜ Open |
| [BE-W7](#be-w7-flickr-oauth-session-in-controller) | OAuth session read/write split between controller and service | Low | Cursor | ⬜ Open |
| [BE-W8](#be-w8-crawlbulk-loop-in-controller) | `FlickrContactController::crawlBulk()` loops in controller | Low | Cursor | ⬜ Open |
| [BE-W9](#be-w9-job-failed-uses-app) | `DownloadPhotoJob::failed()` / `UploadPhotoJob::failed()` use `app()` vs method injection | Low | Sonnet | ⬜ Open |
| [BE-W10](#be-w10-flickroauth-constructor-di) | `FlickrOAuthService` constructor-injects dep used in one private method | Low | Grok | ⬜ Open |
| [BE-W11](#be-w11-diskfor-unused-parameter) | `StorageUploadService::diskFor()` accepts unused `$credentials` | Low | Sonnet | ⬜ Open |
| [BE-W12](#be-w12-oauth-session-incomplete-clear) | `clearOAuthSession()` omits `storage_oauth_return_url` | Low | Sonnet | ⬜ Open |
| [BE-W13](#be-w13-controller-di-style-inconsistent) | Mixed constructor DI, method DI, and `app()` resolution | Low | Codex, Cursor | ⬜ Open |

### Frontend — Medium Priority

| ID | Issue | Severity | Sources | Status |
|---|---|---|---|---|
| [FE-01](#fe-01-usestoragebrowse-god-hook) | `useStorageBrowse` (~375 lines) mixes accounts/browse/sync/delete | Medium | All | ⬜ Open |
| [FE-02](#fe-02-credential-panels-oversized) | Settings credential panels oversized; duplicate modal/form markup | Medium | All | ⬜ Open |
| [FE-03](#fe-03-conflicting-accountlabel) | Conflicting `accountLabel()` in `breadcrumbs.ts` vs `crawlOperations.ts` | Medium | Cursor | ⬜ Open |
| [FE-04](#fe-04-raw-buttons-bypass-design-system) | Raw `<button>` markup bypasses shared `Button` / `ActionButton` | Medium | Codex, Cursor | ⬜ Open |
| [FE-05](#fe-05-contact-show-crawl-state-unwired) | Contact Show does not pass `typeStates={crawl_state}` to `CrawlActionBar` | Medium | Cursor | ⬜ Open |
| [FE-06](#fe-06-crawl-summary-polling-duplicated) | Crawl summary polling duplicated (Flickr Index + Credentials panel) | Medium | Cursor | ⬜ Open |
| [FE-07](#fe-07-catalog-pages-near-duplicate) | Catalog Photosets/Galleries pages ~85% duplicate | Medium | Cursor | ⬜ Open |
| [FE-08](#fe-08-operations-duplicate-tables) | `Crawl/Operations.tsx` repeats table/progress column markup | Medium | Grok, Cursor | ⬜ Open |
| [FE-09](#fe-09-settings-types-not-centralized) | `FlickrAppSummary` / `StorageAppSummary` re-declared, not in `types.ts` | Medium | Grok, Cursor | ⬜ Open |
| [FE-10](#fe-10-useremotedatatable-no-errors) | `useRemoteDataTable` swallows errors — no error state | Medium | Cursor | ⬜ Open |
| [FE-11](#fe-11-loose-status-typing) | Crawl/transfer status fields typed as loose `string` | Medium | Cursor | ⬜ Open |
| [FE-12](#fe-12-contacts-index-god-page) | `Contacts/Index.tsx` (~325 lines) mixes too many concerns | Medium | Cursor | ⬜ Open |

### Frontend — Low / Watch

| ID | Issue | Severity | Sources | Status |
|---|---|---|---|---|
| [FE-W1](#fe-w1-polling-not-abstracted) | Polling/`AbortController` boilerplate repeated across pages | Low | Cursor, Sonnet | ⬜ Open |
| [FE-W2](#fe-w2-browse-hardcoded-settings-href) | `Browse.tsx` hardcodes `/settings?tab=storage` | Low | Sonnet | ⬜ Open |
| [FE-W3](#fe-w3-dashboard-polling-no-visibility-guard) | Dashboard polls when tab hidden | Low | Sonnet | ⬜ Open |
| [FE-W4](#fe-w4-usecrawloperations-dual-refresh) | `useCrawlOperations` couples `router.reload` + API polling | Low | Cursor | ⬜ Open |
| [FE-W5](#fe-w5-sync-recursion-eslint-suppress) | `syncFromProvider` recursion + `eslint-disable` for hook deps | Low | Codex, Sonnet | ⬜ Open |
| [FE-W6](#fe-w6-docs-actionbutton-wrong-path) | Docs reference `ActionButton.tsx` but export lives in `ActionBar.tsx` | Low | Cursor (reframed) | ⬜ Open |
| [FE-W7](#fe-w7-misc-fe-consistency) | PageProps index signature, Inertia prop access, pagination strategy docs, scattered `formatNumber` | Low | Cursor | ⬜ Watch |

---

## Recommended Priority Order

1. **BE-03** — DB locks during remote sync (reliability)
2. **BE-04** — Upload→download job coupling / batch ID reuse (correctness)
3. **BE-02** — Controller streaming logic (layering)
4. **BE-01** — Storage provider extensibility (OCP)
5. **BE-05** — List-page filesystem I/O (performance)
6. **BE-06** — Write side-effects on transfer poll (API semantics)
7. **BE-07**, **BE-08** — HTTP coupling + facade in controllers
8. **BE-09**–**BE-25** — Medium items, one PR per issue
9. **FE-01**–**FE-12** — Frontend decomposition and consistency
10. Low/watch items as capacity allows

---

## Issue Details

### BE-01: Storage provider `match()` dispatch — no Strategy/Registry

**Breaks:** OCP, DIP, DRY  
**Files:** `StorageBrowseService.php:50-55`, `StorageDeleteService.php:39-44`, `StorageUploadService.php:90+`, `StorageFlysystemFactory.php`  
**Explain:** Central services inject all four provider implementations and branch with `match ($driver)`. No shared PHP interface under `app/`. Adding a fifth provider requires editing multiple facade classes. Provider method signatures are inconsistent (e.g. Google Photos `browse()` extra `$accountId`).  
**Fix:** Introduce per-capability interfaces (`StorageBrowseDriver`, `StorageDeleteDriver`, `StorageUploadDriver`). Register `StorageDriver → implementation` map in a factory/ServiceProvider. Orchestration services resolve from registry only.  
**Risk:** High maintenance cost per new provider; regression risk across Google Photos, Drive, OneDrive, R2.

---

### BE-02: Storage download logic in controller

**Breaks:** Mandated flow, SRP  
**Files:** `Api/StorageBrowseController.php:154-208`, `StorageDownloadService.php`  
**Explain:** `download()` builds `StorageR2Config`, resolves disk via `app(StorageFlysystemFactory::class)`, checks existence, opens stream — all in the HTTP layer. `StorageDownloadService` exists but only supports `downloadToPath()` for transfers, not HTTP streaming.  
**Fix:** Add `StorageDownloadService::openStreamForAccount(StorageAccount, string $remotePath): StreamResult` (or dedicated stream service). Controller maps result to `StreamedResponse` only.  
**Risk:** Every new provider download will duplicate controller I/O.

---

### BE-03: Remote API calls inside DB transaction

**Breaks:** Transaction boundary best practice, reliability  
**Files:** `StorageBrowseSyncService.php:45-59`  
**Explain:** `sync()` wraps the sync loop in `DB::transaction()` and calls `$this->browse->browse()`, which performs provider HTTP/API calls. Locks are held while waiting on external services.  
**Fix:** Fetch provider pages outside transactions. Use short transactions only around local state persistence per batch.  
**Risk:** Lock contention; slow providers degrade DB responsiveness; harder retries.

---

### BE-04: Upload execution dispatches download job

**Breaks:** Service orchestration rules; batch integrity  
**Files:** `PhotoUploadExecutionService.php:40-52`  
**Explain:** When local file is not `completed`, upload execution dispatches `DownloadPhotoJob` with the **upload** `$batchId`. Creates cross-pipeline coupling: `PhotoUploadExecutionService → DownloadPhotoJob → PhotoDownloadExecutionService`. Batch counters may conflate upload and download items.  
**Fix:** Introduce `TransferOrchestrationService` (or coordinator) that owns "ensure downloaded, then upload." Upload execution service should only upload. Use separate batch IDs or explicit orchestration state.  
**Risk:** Unpredictable batch reconciliation in production.

---

### BE-05: Filesystem `exists()` on contact list

**Breaks:** SRP, performance  
**Files:** `ContactDownloadCountsService.php:68-70`  
**Explain:** For each stored file on the contacts list, calls `Storage::exists($file->local_path)`. Scales O(contacts × files) with filesystem `stat()` per request. Integrity checks belong in a background reconciler, not inline on read.  
**Fix:** Rely on `StoredFile.status` column. Add optional `missing` status via integrity job if needed.  
**Risk:** List page performance degrades with archive size.

---

### BE-06: Transfer index writes on read

**Breaks:** CQRS / SRP  
**Files:** `TransferProgressQueryService.php:75-80`  
**Explain:** When `isActive()`, listing batches calls `$this->batchReconciler->reconcile($batch)` then re-fetches. Polling a read endpoint triggers writes.  
**Fix:** Keep `index()` read-only. Reconcile in execution Services, job completion handlers, or dedicated `POST /transfers/reconcile`.  
**Risk:** Surprise DB writes under concurrent polling; performance cost.

---

### BE-07: Query Services coupled to HTTP

**Breaks:** SRP, DIP  
**Files:** `CatalogQueryService.php`, `CrawlStatusQueryService.php`, `TransferProgressQueryService.php`  
**Explain:** Services import FormRequests, return `JsonResponse`, and call `abort(404)`. Business query logic is tied to HTTP stack.  
**Fix:** Services return DTOs/arrays/`LengthAwarePaginator`. Controllers extract validated primitives from FormRequests and build JSON responses.  
**Risk:** Services unusable from Jobs/CLI; harder unit tests.

---

### BE-08: Controllers call Flickr facade

**Breaks:** Mandated flow  
**Files:** `FlickrAccountController.php:25-27`, `CrawlOperationsController.php:16-18`, `DashboardService.php:35`  
**Explain:** Controllers (and `DashboardService`) call `FlickrService::connections()->list()` directly instead of an app Service wrapper. `FlickrOAuthService::listAccounts()` already exists.  
**Fix:** Add `FlickrConnectionListService` (or use `FlickrOAuthService`) for account listing + presentation.  
**Risk:** Future caching/filtering in Service layer is bypassed.

---

### BE-09: OAuth connect skips FormRequest

**Breaks:** FormRequest mandate  
**Files:** `FlickrAuthController.php:18-20`, `StorageAuthController.php:20-58`  
**Explain:** `connect` / `reauthorize` accept raw `Request`. Sibling actions use FormRequests. Storage `return_url` sanitization lives in private controller helper.  
**Fix:** Add `BeginFlickrOAuthRequest`, `BeginStorageOAuthRequest`, `ReauthorizeStorageRequest`. Move `sanitizeReturnUrl()` into FormRequest.  
**Risk:** Inconsistent validation; harder to test OAuth entry points.

---

### BE-10: Photo transfer controller orchestration

**Breaks:** Thin controller  
**Files:** `PhotoDownloadController.php:18-63`, `PhotoUploadController.php:18-71`  
**Explain:** Controllers branch across single-photo, multi-contact, single-contact, and account-wide modes with inline counting and flash messages. Services already expose granular queue methods.  
**Fix:** `PhotoDownloadService::queueFromRequest(...)` and upload equivalent returning a result DTO. Controllers: validate → one Service call → redirect.  
**Risk:** New queue modes require duplicate controller edits.

---

### BE-11: Contact show controller orchestration

**Breaks:** Thin controller  
**Files:** `FlickrContactController.php:93-117`  
**Explain:** `show()` method-injects four Services and assembles Inertia payload. `index()` correctly uses presenter + query service.  
**Fix:** `ContactDetailService::forShow(Connection, string $nsid): array` (or extend presenter).  
**Risk:** Contact detail logic scatters as page grows.

---

### BE-12: Settings controller bypasses service

**Breaks:** Layered architecture  
**Files:** `SettingsController.php:27-28, 47-58`  
**Explain:** Injects `StorageAccountRepository` directly; builds `storage_drivers` inline. Flickr accounts use `FlickrOAuthService`; storage apps use `StorageAppProfileService`.  
**Fix:** `StorageAccountService::listForSettings()` or `SettingsPageService` for full payload. Move driver mapping to presenter/enum helper.  
**Risk:** Precedent for more controller→repository shortcuts.

---

### BE-13: FormRequest resolves repository

**Breaks:** FormRequest SRP  
**Files:** `Http/Requests/Transfer/QueuePhotoUploadRequest.php:36-46`  
**Explain:** `resolvedStorageAccount()` calls `app(StorageAccountRepository::class)` and `findByIdOrFail` / `findDefault`. FormRequest performs data access, not just validation.  
**Fix:** Pass raw `storage_account_id` to `PhotoUploadService`; resolve account in Service.  
**Risk:** Validation layer coupled to persistence; harder to mock.

---

### BE-14: API routes layering duplicates

**Breaks:** Consistency / layering  
**Files:** `routes/api.php:19-20`, `routes/web.php:48-49`  
**Explain:** Download/upload registered in both files. Frontend uses web paths (`flickrAccountPath(..., '/download')`), not `/api/...`. API routes return redirects, not JSON — dead duplicates. `api.php` also routes web-namespace controllers for JSON endpoints.  
**Fix:** Remove duplicate download/upload from `api.php` OR migrate frontend to API with JSON responses. Gradually add `Api\FlickrAccountController` / `Api\FlickrContactController` for JSON-only actions.  
**Risk:** Future API consumer gets 302 instead of JSON.

---

### BE-15: Repository embeds business rules

**Breaks:** Repository SRP  
**Files:** `StorageRemoteSyncStateRepository.php:55-70`  
**Explain:** `appendReconcileSeenRemoteIds()` guards `!$state->reconciling` and merges/dedupes IDs — workflow logic split between Service and Repository.  
**Fix:** Move guard + merge to `StorageBrowseSyncService`. Repository: `updateSeenRemoteIds(int, string, array): void`.  
**Risk:** Reconciliation bugs require reading wrong layer.

---

### BE-16: Services mutate models directly

**Breaks:** Mandated flow (write path)  
**Files:** `StorageAccountService.php`, `StorageBrowseSyncService.php`, `TransferBatchRepository.php`  
**Explain:** Services call `$account->update()`, `$state->save()`, `$batch->update()` on models returned from repositories instead of repository write methods.  
**Fix:** Add explicit repository mutation methods; Services hold business rules only.  
**Risk:** Repository layer effectively read-only; scattered write paths.

---

### BE-17: Storage OAuth mutates global config

**Breaks:** Side-effect isolation, testability  
**Files:** `StorageOAuthService.php:112-116`  
**Explain:** `provider()` writes credentials into global `config([...])` before Socialite call. Safe in PHP-FPM; risky under Octane/long-lived workers.  
**Fix:** Configure Socialite per-request without mutating global config (build provider with explicit credentials).  
**Risk:** Concurrency/cross-request credential bleed if runtime changes.

---

### BE-18: Storage delete driver branch

**Breaks:** OCP (subset of BE-01)  
**Files:** `StorageDeleteService.php:46-53`  
**Explain:** Post-delete cache purge branches on `GooglePhotos` vs other drivers inside central service.  
**Fix:** Move `afterDelete()` to per-driver strategy (part of BE-01 registry).  
**Risk:** Every new driver edits central delete service.

---

### BE-19: Contact show duplicate / unused props

**Breaks:** DRY, incomplete feature  
**Files:** `FlickrContactController.php:111-116`, `Contacts/Show.tsx:52-80`  
**Explain:** Sends both `contact` and `contact_detail` with identical `ContactPresenter::toDetailArray()` output. Page destructures only `contact`, `catalog_stats` — omits `crawl_state`. `CrawlActionBar` lacks `typeStates` unlike Contacts Index. Test expects `contact_detail.nsid`.  
**Fix:** Pick one contact key; update frontend + test. Wire `typeStates={crawl_state}` on Show page.  
**Risk:** Wasted SSR payload; inconsistent crawl UI on detail page.

---

### BE-20: Contact index re-sanitizes sort

**Breaks:** DRY  
**Files:** `FlickrContactController.php:54-58`, `ListFlickrContactsRequest.php`  
**Explain:** Controller re-implements sort/direction guards via `rawSort()` / `rawDirection()` though FormRequest already exposes safe `sort()` / `direction()`.  
**Fix:** Use `$request->sort()` and `$request->direction()` in filters payload.  
**Risk:** Sort column list changes in one place only.

---

### BE-21: Status string literals

**Breaks:** DRY, type safety  
**Files:** Repositories, `PhotoDownloadExecutionService`, `PhotoUploadExecutionService`, `TransferBatchReconciler`, etc.  
**Explain:** `'pending'`, `'completed'`, `'failed'`, `'downloading'`, `'processing'` repeated as raw strings. `PhotoTransferExecutionOutcome` enum exists but not used for DB status values.  
**Fix:** Backed enums `StoredFileStatus`, `TransferItemStatus`, `TransferBatchStatus`; use `->value` when persisting.  
**Risk:** Typos cause silent logic failures.

---

### BE-22: Crawler repository pattern drift

**Breaks:** Consistency, DRY  
**Files:** `Repositories/Crawler/*QueryRepository.php`, `CatalogQueryRepository.php:33-90`  
**Explain:** App repos extend `EloquentRepository`; Crawler query repos build queries ad hoc with repeated pagination/ownership filters. Undocumented split reads as drift.  
**Fix:** Document as intentional "Query Repository" pattern OR align with shared base + `paginateQuery()` / `filterByOwner()` helpers.  
**Risk:** Each new repo reinvents pagination boilerplate.

---

### BE-23: API StorageBrowse controller overloaded

**Breaks:** SRP  
**Files:** `Api/StorageBrowseController.php` (~235 lines)  
**Explain:** Combines browse, sync, delete, download (BE-02), auth error heuristics (`browseErrorResponse`), reauthorization responses.  
**Fix:** Extract action Services or orchestrator; move private helpers to Support classes.  
**Risk:** Unmaintainable as storage features grow.

---

### BE-24: Runtime config missing FormRequests

**Breaks:** FormRequest mandate  
**Files:** `RuntimeConfigController.php:22-33`  
**Explain:** `store()` uses `StoreRuntimeConfigRequest`; `destroy` / `reset` accept raw route `path` without validation FormRequest.  
**Fix:** `DestroyRuntimeConfigRequest` / `ResetRuntimeConfigRequest` with path decoding in `prepareForValidation()`.  
**Risk:** Ad hoc path handling; rules not co-located.

---

### BE-25: Models expose presentation

**Breaks:** Fat model / layering  
**Files:** `StorageRemoteItem.php:42-54`, `StorageRemoteAlbum`  
**Explain:** `toBrowseArray()` formats API shapes on Eloquent models. Elsewhere uses `app/Support/*Presenter`.  
**Fix:** `StorageBrowseItemPresenter::toArray(StorageRemoteItem)`.  
**Risk:** Response shape changes require model edits.

---

### BE-W1 through BE-W13

**BE-W1:** Concrete repo bindings — watch; add interfaces only where second implementation is planned (storage strategies first).  
**BE-W2:** No domain events — watch until multiple listeners needed.  
**BE-W3:** Merge `PhotoDownloadController` + `PhotoUploadController` into `PhotoTransferController` if desired for grouping.  
**BE-W4:** Move `ContactListPresenter`, `FlickrRateLimitPresenter`, `ContactListSorter`, `StorageBrowseResult` to `Support/` or `DTO/`.  
**BE-W5:** Replace four web `StorageBrowseController` actions with `storages/{provider}` + `StorageDriver::fromRouteSlug()`.  
**BE-W6:** Extract shared R2 metadata helper from upload/download Services.  
**BE-W7:** Move OAuth session storage into `FlickrOAuthService` / `StorageOAuthService`.  
**BE-W8:** `FlickrCrawlService::crawlManyContacts()` for bulk loop.  
**BE-W9:** Use method injection on `failed()` like `handle()`.  
**BE-W10:** Resolve `FlickrAppProfileService` inside `clientForProfile()` only.  
**BE-W11:** Remove unused `$credentials` from `diskFor()`.  
**BE-W12:** Add `storage_oauth_return_url` to `clearOAuthSession()` forget list.  
**BE-W13:** Standardize controller DI convention (constructor vs method vs `app()`).

---

### FE-01: useStorageBrowse god hook

**Breaks:** Hook SRP  
**Files:** `hooks/useStorageBrowse.ts` (~375 lines)  
**Explain:** Owns accounts, browse pagination, sync recursion, delete, container navigation, error state. Suppresses `react-hooks/exhaustive-deps` at line 325.  
**Fix:** Split into `useStorageAccounts`, `useStorageContent`, `useStorageSync`, `useStorageDelete`; thin composition hook for page.  
**Risk:** Race conditions hard to debug; eslint suppression masks dependency bugs.

---

### FE-02: Credential panels oversized

**Breaks:** SRP, DRY  
**Files:** `StorageCredentialsPanel.tsx` (543 lines), `FlickrCredentialsPanel.tsx` (367 lines), `GeneralConfigPanel.tsx` (310 lines)  
**Explain:** Mix list rendering, OAuth flows, two modals each, duplicate dialog shells.  
**Fix:** Shared `CredentialFormDialog`, provider cards, form field components.  
**Risk:** UI changes duplicate class names and behavior.

---

### FE-03: Conflicting accountLabel

**Breaks:** DRY  
**Files:** `lib/breadcrumbs.ts:5-7` (`username ?? nsid`), `lib/crawlOperations.ts:3-8` (`fullname || username || nsid`)  
**Fix:** Single `accountDisplayName()` in `lib/flickrAccount.ts`; replace all call sites.  
**Risk:** Same account shows different labels in breadcrumbs vs operations.

---

### FE-04: Raw buttons bypass design system

**Breaks:** `ui-buttons.mdc`, `react-inertia-frontend` skill  
**Files:** `Pages/Storage/Browse.tsx`, `Pages/Dashboard.tsx`, `Components/Settings/*`, etc.  
**Explain:** Raw Tailwind `<button>` instead of `Button` / `ActionButton` from `ActionBar.tsx`.  
**Fix:** Incremental migration to shared button variants.  
**Risk:** Visual and a11y inconsistency.

---

### FE-05: Contact show crawl_state unwired

**Breaks:** Feature completeness  
**Files:** `Contacts/Show.tsx:52, 77-80`  
**Explain:** Backend sends `crawl_state`; page does not destructure or pass to `CrawlActionBar`.  
**Fix:** `typeStates={crawl_state}` on Show page `CrawlActionBar`.  
**Risk:** Detail page crawl UI lacks per-type state.

---

### FE-06: Crawl summary polling duplicated

**Breaks:** DRY  
**Files:** `Pages/Flickr/Index.tsx:51-76`, `Components/Settings/FlickrCredentialsPanel.tsx:53-79`  
**Fix:** `useCrawlSummaries(accounts, { intervalMs: 10000 })`.  
**Risk:** Polling bugs fixed in one place only.

---

### FE-07: Catalog pages near-duplicate

**Breaks:** DRY  
**Files:** `Pages/Catalog/Photosets.tsx`, `Pages/Catalog/Galleries.tsx`  
**Fix:** Shared `CatalogCollectionPage` with config object.  
**Risk:** Feature drift between entity tables.

---

### FE-08: Operations duplicate tables

**Breaks:** DRY  
**Files:** `Pages/Crawl/Operations.tsx`  
**Explain:** Three near-identical `DataTable` blocks; download/upload sections duplicate `ProgressBar` columns.  
**Fix:** `FetchRunsTable`, `TransferProgressCell`, or parameterized `BatchTable<T>`.  
**Risk:** UI inconsistency between sections.

---

### FE-09: Settings types not centralized

**Breaks:** DRY  
**Files:** `Pages/Settings/Index.tsx`, `FlickrCredentialsPanel.tsx`, `StorageCredentialsPanel.tsx`  
**Fix:** Move `FlickrAppSummary`, `StorageAppSummary` to `types.ts`.  
**Risk:** Silent type drift.

---

### FE-10: useRemoteDataTable no errors

**Breaks:** UX resilience  
**Files:** `hooks/useRemoteDataTable.ts`  
**Explain:** `try/finally` without `catch`; failures leave stale data, `loading: false`, no error state.  
**Fix:** Return `{ error, reload }`; error banner on catalog pages.  
**Risk:** Silent catalog API failures.

---

### FE-11: Loose status typing

**Breaks:** Type safety  
**Files:** `types.ts` — `CrawlRun.status`, `TransferBatch.status` as `string`  
**Fix:** Union types for known status values.  
**Risk:** Typos in status comparisons undetected at compile time.

---

### FE-12: Contacts Index god page

**Breaks:** SRP  
**Files:** `Pages/Contacts/Index.tsx` (~325 lines)  
**Fix:** Extract `useContactsProgressPoll`, `contactsTableColumns()`, navigation helpers.  
**Risk:** Regressions on any list change.

---

### FE-W1 through FE-W7

**FE-W1:** Shared `usePollingQuery(fn, { intervalMs, enabled })` for Dashboard, Contacts, Operations.  
**FE-W2:** `lib/routes.ts` helper for settings tab links; use Inertia `Link`.  
**FE-W3:** Pause polling when `document.visibilityState === 'hidden'`.  
**FE-W4:** `useCrawlOperations` — use one refresh mechanism (API or Inertia reload, not both).  
**FE-W5:** Replace recursive `syncFromProvider` with iterative loop; remove eslint suppression.  
**FE-W6:** Fix `.cursor/rules/ui-buttons.mdc` to reference `ActionBar.tsx`, not missing `ActionButton.tsx`.  
**FE-W7:** Document pagination strategies; optional `PageProps` index signature removal; centralize `formatInteger` in `lib/format.ts`.

---

## AI Check / Report-Back After Implementation

For each fixed issue, before marking **Done**:

| Step | Action |
|---|---|
| 1 | Update Status: `⬜ Open` → `🔄 In Progress` → `✅ Done` or `❌ Won't Fix` |
| 2 | Run `composer test:docker`; `npm run typecheck` if FE touched; `composer instructions:verify` |
| 3 | For BE structural fixes (BE-01–BE-08, BE-10–BE-16): verify no new circular Service dependency |
| 4 | For FE fixes: verify Settings OAuth, Storage browse/sync/delete, Contacts, Operations in browser |
| 5 | Report issue IDs addressed + any deviation from proposed fix |

**PR scope:** One issue per PR unless tightly coupled (e.g. BE-01 + BE-18).

### Report-back template

```markdown
## Audit Report-Back — YYYY-MM-DD

| ID | Status | Verification |
|---|---|---|
| BE-02 | ✅ Done | Controller delegates to StorageDownloadService::openStreamForAccount |

### Quality gates
- [ ] composer test:docker
- [ ] npm run typecheck
- [ ] composer instructions:verify
```

---

## Post-Implementation

After targeted fixes, re-run spot checks on affected files and append a `## Re-Audit Summary` section with updated Status columns.
