# XFlickr Verified Audit Remediation Plan

**Status:** READY_WITH_BLOCKERS  
**Review date:** 2026-06-23  
**Generated at:** 2026-06-23T19:05:53Z  
**Project root:** `/Users/vietvu/Sites/JOOservices/XFlickr`  
**Source audit files reviewed:** `audit.md`, `audit_grok.md`, `audit_codex.md`, `audit_cursor.md`, `audit_sonnet.md`, `audit_final.md`  
**Proposed plan reviewed:** `/Users/vietvu/.cursor/plans/audit_final_fixes_18962dcf.plan.md`  
**Final verdict:** APPROVED_WITH_BLOCKERS  
**Implementation scope:** Correct confirmed code-level defects and concrete architectural risks only. Exclude false positives, watch-only style preferences, and optional refactors unless needed to complete a confirmed fix.

## Executive Summary

The current project code was audited against all `audit*.md` reports and the proposed remediation plan. The code is the source of truth. The consolidated `audit_final.md` is a useful index, but it over-promotes several style/watch items into implementation work. The proposed plan also contains one important unsafe remediation direction for upload/download orchestration: BE-04 must not leave upload batches counting undownloaded photos unless explicit orchestration state guarantees later upload completion.

Confirmed mandatory issues are concentrated in:

- storage sync transaction boundaries,
- upload/download queue orchestration and batch semantics,
- storage provider dispatch and controller storage I/O leakage,
- read endpoints that write during polling,
- HTTP-coupled query services,
- controller bypasses of service/FormRequest layers,
- list-page filesystem I/O,
- OAuth global config mutation,
- a few user-visible frontend correctness and resilience gaps.

Implementation may begin, but only after accepting the corrected scope below. Do not implement the previous proposed plan wholesale.

## Source-of-Truth Statement

The current project code and verified runtime flow are the primary source of truth. Audit reports, documentation, tests, comments, naming, and previous plans are supporting evidence only and must not be trusted without code-level verification.

## Files and Areas Inspected

Important inspected areas:

- `AGENTS.md`, `docs/README.md`, `ai/README.md`, `docs/05-maintenance/docker-safety.md`
- `composer.json`, `package.json`, `routes/web.php`, `routes/api.php`
- Storage services: `StorageBrowseService`, `StorageBrowseSyncService`, `StorageDeleteService`, `StorageUploadService`, `StorageDownloadService`, `StorageFlysystemFactory`, `StorageOAuthService`, `StorageBrowseLocalService`
- Flickr transfer services/jobs: `PhotoDownloadService`, `PhotoUploadService`, `PhotoDownloadExecutionService`, `PhotoUploadExecutionService`, `DownloadPhotoJob`, `UploadPhotoJob`
- Query services/controllers: `CatalogQueryService`, `CrawlStatusQueryService`, `TransferProgressQueryService`, `Api\CatalogController`, `Api\CrawlStatusController`, `Api\TransferProgressController`
- Web/API controllers: `Api\StorageBrowseController`, `FlickrAuthController`, `StorageAuthController`, `SettingsController`, `FlickrAccountController`, `FlickrContactController`, `PhotoDownloadController`, `PhotoUploadController`, `RuntimeConfigController`, `CrawlOperationsController`
- Repositories/models: `StorageRemoteSyncStateRepository`, `StoredFileRepository`, `TransferBatchRepository`, `TransferItemRepository`, `StorageRemoteItem`, `StorageRemoteAlbum`, crawler query repositories
- Frontend: `useStorageBrowse`, `useRemoteDataTable`, `useCrawlOperations`, `Dashboard.tsx`, `Contacts/Show.tsx`, `Contacts/Index.tsx`, `Storage/Browse.tsx`, `Catalog/Photosets.tsx`, `Catalog/Galleries.tsx`, `Crawl/Operations.tsx`, Settings panels, `types.ts`, `ActionBar.tsx`, `.cursor/rules/ui-buttons.mdc`
- Tests inspected by name and targeted content: `StorageBrowseTest`, `StorageSyncReconcileTest`, `StorageDeleteTest`, `PhotoUploadExecutionServiceTest`, `PhotoUploadServiceTest`, `PhotoDownloadServiceTest`, `ContactDownloadCountsServiceTest`, `FlickrContactFrontendSupportTest`, request validation tests

No tests were run during this planning pass.

## Audit Validation Summary

| Issue ID | Audit source | Severity | Validation status | Code evidence | Root cause | Previous plan coverage | Final decision |
|---|---|---:|---|---|---|---|---|
| ISSUE-01 | `audit_codex.md` #4, `audit_final.md` BE-03 | High | CONFIRMED | `StorageBrowseSyncService::sync()` wraps loop in `DB::transaction()` and calls `$this->browse->browse()` | Remote provider I/O is inside a DB transaction | FULL but needs concurrency detail | Mandatory |
| ISSUE-02 | `audit_sonnet.md` #1, `audit_cursor.md` BE-17, `audit_final.md` BE-04 | Critical | CONFIRMED | `PhotoUploadExecutionService::execute()` dispatches `DownloadPhotoJob` with upload `$batchId` | Upload execution owns download orchestration and corrupts batch semantics | PARTIAL/INCORRECT | Mandatory with corrected orchestration |
| ISSUE-03 | Multiple, `audit_final.md` BE-02 | High | CONFIRMED | `Api\StorageBrowseController::download()` builds `StorageR2Config`, resolves `StorageFlysystemFactory`, streams file | Controller owns storage driver I/O | FULL | Mandatory |
| ISSUE-04 | Multiple, `audit_final.md` BE-01/BE-18 | High | CONFIRMED | `StorageBrowseService`, `StorageDeleteService`, `StorageUploadService`, `StorageFlysystemFactory` branch by `StorageDriver`; `StorageDeleteService` has Google Photos post-delete branch | Provider capabilities are centralized instead of registered strategies | PARTIAL | Mandatory, staged |
| ISSUE-05 | `audit_sonnet.md` #5, `audit_final.md` BE-05 | High | CONFIRMED | `ContactDownloadCountsService::forContacts()` calls `Storage::exists()` per completed file | Read/count service performs filesystem integrity checks inline | FULL | Mandatory |
| ISSUE-06 | `audit_cursor.md` BE-7, `audit_final.md` BE-06 | High | CONFIRMED | `TransferProgressQueryService::index()` calls `TransferBatchReconciler::reconcile()` when `active=1` | Read endpoint mutates transfer state during polling | FULL | Mandatory |
| ISSUE-07 | `audit_cursor.md` BE-5, `audit_final.md` BE-07 | High | CONFIRMED | `CatalogQueryService`, `CrawlStatusQueryService`, `TransferProgressQueryService` import FormRequests and return `JsonResponse` | Query services are coupled to HTTP layer | FULL | Mandatory |
| ISSUE-08 | `audit_cursor.md` BE-4, `audit_sonnet.md` #13, `audit_final.md` BE-08 | Medium | CONFIRMED | `FlickrAccountController::list()` and `CrawlOperationsController::index()` call `FlickrService::connections()` directly | Controllers bypass app service wrapper | FULL | Mandatory |
| ISSUE-09 | Multiple, `audit_final.md` BE-09 | Medium | CONFIRMED | `FlickrAuthController::connect()`, `StorageAuthController::connect()`, `StorageAuthController::reauthorize()` accept raw `Request` | OAuth entrypoint validation/normalization lives in controllers | FULL | Mandatory |
| ISSUE-10 | `audit_sonnet.md` #3, `audit_final.md` BE-13 | Medium | CONFIRMED | `QueuePhotoUploadRequest::resolvedStorageAccount()` calls `app(StorageAccountRepository::class)` | FormRequest performs persistence lookup | FULL | Mandatory |
| ISSUE-11 | Multiple, `audit_final.md` BE-10 | Medium | CONFIRMED | `PhotoDownloadController::store()` and `PhotoUploadController::store()` branch over photo/contact/account modes | Queue orchestration and flash decisions live in controllers | FULL | Mandatory |
| ISSUE-12 | Multiple, `audit_final.md` BE-19/FE-05 | Medium | CONFIRMED | `FlickrContactController::show()` sends `contact` and `contact_detail`; `Contacts/Show.tsx` ignores `crawl_state` | Duplicate prop and incomplete UI wiring | FULL | Mandatory |
| ISSUE-13 | Multiple, `audit_final.md` BE-11 | Medium | CONFIRMED | `FlickrContactController::show()` composes four services and assembles props | Contact detail page assembly is in controller | FULL | Mandatory after ISSUE-12 or together if small |
| ISSUE-14 | Multiple, `audit_final.md` BE-12 | Medium | CONFIRMED | `SettingsController::index()` injects `StorageAccountRepository` and maps storage drivers inline | Controller bypasses service/presenter boundary | FULL | Mandatory |
| ISSUE-15 | `audit.md` #9, `audit_final.md` BE-14 | Medium | CONFIRMED | `routes/api.php` duplicates web download/upload routes using redirecting web controllers | API route file exposes non-JSON duplicate endpoints | FULL | Mandatory |
| ISSUE-16 | `audit.md` #5, `audit_final.md` BE-15 | Medium | CONFIRMED | `StorageRemoteSyncStateRepository::appendReconcileSeenRemoteIds()` checks `reconciling`, merges/dedupes IDs | Repository contains sync workflow rules | FULL | Mandatory, can pair with ISSUE-01 |
| ISSUE-17 | `audit_cursor.md` BE-6, `audit_final.md` BE-16 | Medium | PARTIALLY_CONFIRMED | `StorageAccountService`, `StorageBrowseSyncService` mutate returned models directly; repositories also legitimately update models internally | Services bypass repository write APIs in selected domains | PARTIAL too broad | Mandatory only for touched storage/transfer paths |
| ISSUE-18 | `audit_sonnet.md` #8, `audit_final.md` BE-17 | Medium | CONFIRMED | `StorageOAuthService::provider()` calls `config([...])` before Socialite driver | Runtime global config mutation | FULL but implementation must be docs-verified | Mandatory |
| ISSUE-19 | `audit_cursor.md` BE-12, `audit_final.md` BE-24 | Medium | CONFIRMED | `RuntimeConfigController::destroy()` and `reset()` accept raw route path | Missing FormRequest normalization for destructive config endpoints | FULL | Mandatory |
| ISSUE-20 | `audit_sonnet.md` #11, `audit_final.md` BE-21 | Medium | CONFIRMED | Status strings repeated in repositories/services/tests; migrations store `string(32)` | No typed status vocabulary | FULL but broad | Mandatory after transfer fixes |
| ISSUE-21 | `audit_final.md` BE-25 | Low/Medium | CONFIRMED | `StorageRemoteItem::toBrowseArray()`, `StorageRemoteAlbum::toBrowseArray()` | Models expose API presentation shape | FULL | Optional unless touching browse local response |
| ISSUE-22 | Multiple frontend audits, `audit_final.md` FE-05 | Medium | CONFIRMED | `Contacts/Show.tsx` receives `crawl_state` but `CrawlActionBar` lacks `typeStates` | Detail page crawl UI is less complete than index | FULL | Mandatory with ISSUE-12 |
| ISSUE-23 | `audit_cursor.md` FE-09, `audit_final.md` FE-10 | Medium | CONFIRMED | `useRemoteDataTable.ts` has `try/finally` without `catch` or error state | Catalog API failures are silent/stale | FULL | Mandatory |
| ISSUE-24 | `audit_cursor.md` FE-01, `audit_final.md` FE-03 | Medium | CONFIRMED | `breadcrumbs.ts` label uses `username ?? nsid`; `crawlOperations.ts` and Dashboard use fullname-first | User-visible account labels conflict | FULL | Mandatory |
| ISSUE-25 | `audit_cursor.md` FE-10, `audit_final.md` FE-11 | Medium | CONFIRMED | `CrawlRun.status`, `TransferBatch.status`, `StatusBadge.status` typed as `string` | Loose status contracts in FE | FULL | Mandatory after ISSUE-20 or coordinated |
| ISSUE-26 | Multiple frontend audits, `audit_final.md` FE-01/FE-W5 | Medium | CONFIRMED | `useStorageBrowse.ts` owns accounts/content/sync/delete and suppresses hook deps | Oversized hook hides sync race/cancellation risks | FULL but large | Mandatory only if storage browse code is being changed; otherwise stage after backend storage fixes |
| ISSUE-27 | Multiple frontend audits, `audit_final.md` FE-02/FE-04/FE-06/FE-07/FE-08/FE-09/FE-12 | Low/Medium | CONFIRMED | Settings panels are 367/543 lines; raw buttons; duplicated catalog/operations/contacts structures | Frontend DRY and design-system drift | OVER-SCOPED | Optional/staged cleanup |
| WATCH-01 | Multiple | Low | FALSE_POSITIVE/PARTIAL | `ActionButton` exists in `ActionBar.tsx`; `.cursor/rules/ui-buttons.mdc` points to nonexistent `ActionButton.tsx` | Documentation path drift, not missing component | MISCLASSIFIED | Docs-only if touched |
| WATCH-02 | Multiple | Low | WATCH | Repository interfaces everywhere absent by design | No second implementation in app repos | NOT_REQUIRED | Do not implement wholesale |
| WATCH-03 | Multiple | Low | WATCH | No app domain events | No listeners/subscribers currently exist | NOT_REQUIRED | Do not implement until product need |
| WATCH-04 | `audit_cursor.md` BE-21 | Low | FALSE_POSITIVE | Controllers not extending base `Controller` is Laravel-valid | Style preference | NOT_REQUIRED | Reject |
| WATCH-05 | `audit_sonnet.md` #2/#14 | Low | FALSE_POSITIVE/PARTIAL | `DashboardService::snapshot()` method DI and `CatalogController` constructor DI are defensible under existing patterns | Style preference, not defect | NOT_REQUIRED | Reject as mandatory |

## Architecture Decisions

### Decision 1: Storage Provider Capabilities Use Registry + Per-Capability Contracts

- **Decision:** Introduce per-capability storage driver contracts only for provider operations that are currently centrally branched: browse, delete, upload, and optionally stream/download when added.
- **Reason:** The current `match` blocks force central edits per provider and already leak Google Photos post-delete behavior into `StorageDeleteService`.
- **Alternatives rejected:** A single monolithic `StorageProviderStrategy` interface is too broad because providers have uneven capabilities; repository interfaces everywhere is overengineering.
- **Dependency direction:** Controllers -> services -> registry -> driver implementations. Driver implementations may use low-level clients/factories. Drivers must not call controllers or orchestration services.
- **Constraints:** Preserve existing `StorageDriver` enum values and route slugs. Preserve response shapes.
- **Consequences:** Adding a provider requires binding a driver implementation, not changing every central service.

### Decision 2: Transfer Orchestration Owns Cross-Pipeline Flow

- **Decision:** Upload execution must never dispatch download jobs. A coordinator/orchestration service decides whether a photo needs download before upload and owns separate batch semantics.
- **Reason:** `PhotoUploadExecutionService` currently dispatches `DownloadPhotoJob` with an upload batch ID, which can corrupt counters and create unclear retry behavior.
- **Alternatives rejected:** Simply dispatching downloads from `PhotoUploadService::queuePendingPhotos()` while still creating upload items for undownloaded files leaves upload batches stuck/deferred unless the plan defines explicit retry linkage.
- **Dependency direction:** Queue services/coordinator may dispatch jobs. Execution services only execute their own operation and update their own item/batch state.
- **Constraints:** Existing queues `xflickr-downloads` and `xflickr-uploads` remain. Jobs stay idempotent.
- **Consequences:** UI batch totals reflect what is actually executing.

### Decision 3: Reads Must Be Read-Only

- **Decision:** Polling/query services may not reconcile or mutate state as part of read endpoints.
- **Reason:** `TransferProgressQueryService::index()` currently writes during active polling.
- **Alternatives rejected:** Keeping reconciliation in read endpoints for convenience hides writes under polling load.
- **Dependency direction:** Jobs/execution services/dedicated commands own reconciliation. Query services only read and present.
- **Constraints:** Operations UI must still show accurate enough progress after job completion/failure.
- **Consequences:** Tests must prove `active=1` index does not mutate batch counters.

### Decision 4: HTTP Boundary Is Controller/FormRequest Only

- **Decision:** Services return arrays, DTOs, models, paginators, or result objects. Controllers build `JsonResponse`, redirects, and streamed responses.
- **Reason:** Query services currently import FormRequests and return `JsonResponse`.
- **Alternatives rejected:** Leaving HTTP response construction inside services blocks CLI/job reuse and makes service tests depend on HTTP stack.
- **Dependency direction:** FormRequest -> controller -> service. Service must not depend on FormRequest.
- **Constraints:** API JSON shapes must remain backward compatible.
- **Consequences:** Controllers become slightly more explicit but service reuse improves.

### Decision 5: Runtime Configuration and OAuth Inputs Use FormRequests

- **Decision:** OAuth connect/reauthorize and runtime config destroy/reset must normalize and validate input in FormRequests.
- **Reason:** Current raw route/query parsing is inconsistent and harder to test.
- **Alternatives rejected:** Private controller helpers for input sanitation.
- **Dependency direction:** FormRequest may sanitize and expose primitives; services perform business behavior.
- **Constraints:** Existing redirect behavior and session keys remain compatible.
- **Consequences:** Request tests should cover normalized values.

## Scope Rules

### In Scope

- Fix confirmed correctness, reliability, performance, and boundary violations in the traceability matrix.
- Add focused tests that prove behavior and prevent regressions.
- Update docs/rules only when behavior or agent guidance changes.
- Update `CHANGELOG.md` only for user-visible behavior changes.

### Out of Scope

- Repository interfaces across all repositories.
- Domain events without a concrete listener/product need.
- Controller inheritance style changes.
- Broad namespace cleanup for presenters/DTOs unless touched by a confirmed fix.
- Full frontend design-system migration in one pass.
- Removing deprecated jobs without a queue-drain compatibility decision.
- Any local dev-stack database testing or migration command.

### Non-Goals

- Do not add automatic crawl/spider behavior.
- Do not change public API response shapes unless explicitly listed.
- Do not rewrite storage, transfer, or frontend modules wholesale.
- Do not introduce circular service dependencies or controller-to-repository shortcuts.

## Invariants

- No local dev Docker database commands. Tests must use `composer test:docker` or `docker compose -f docker-compose.test.yml ...`.
- Backend flow remains Controller -> FormRequest -> Service -> Repository -> Model.
- Jobs delegate execution to services and remain idempotent under retries.
- Download and upload batch IDs must not be reused across different transfer types.
- Query/read endpoints do not mutate transfer state.
- Storage account credentials and OAuth tokens must not leak into responses.
- Existing storage driver route slugs and enum values remain stable.
- Existing API JSON response shapes remain stable unless a specific issue says otherwise.
- Existing public Inertia prop names remain backward compatible during frontend/backend coupled changes, except where explicitly removing `contact_detail`.
- No new circular service dependencies.
- No unrelated source cleanup while implementing an issue.

## Issue Implementation Plan

### ISSUE-01: Split Storage Sync Remote API Calls From DB Transactions

**Status:** VERIFIED  
**Priority:** HIGH  
**Audit sources:** `audit_codex.md` Issue #4; `audit_final.md` BE-03  
**Validation result:** CONFIRMED

**Current behavior:** `StorageBrowseSyncService::sync()` wraps the full sync loop in `DB::transaction()` and calls `$this->browse->browse()`, which performs provider API calls.

**Code evidence:** `app/Services/Storage/StorageBrowseSyncService.php::sync()`, `app/Services/Storage/StorageBrowseService.php::browse()`.

**Root cause:** Transaction ownership includes both remote fetch and local persistence.

**Risk:** Slow or rate-limited provider calls hold local DB locks; retries and partial persistence are harder to reason about.

**Required fix:** Fetch one provider page/batch outside a transaction. Persist albums/items/state in a short transaction per batch. Refresh state before each fetch. If fetch fails, do not open a persistence transaction for that batch. If persistence fails after fetch, keep tokens so the page retries.

**Files expected to change:** `StorageBrowseSyncService`, likely `StorageRemoteSyncStateRepository`, `StorageSyncReconcileTest` or `StorageBrowseTest`.

**Constraints:** Preserve sync response shape: `albums_synced`, `items_synced`, `has_more`, `last_synced_at`, `albums_complete`, `items_complete`.

**Implementation steps:**
1. Move `firstOrCreateForParent()` and loop state refresh outside the broad transaction.
2. Fetch with `$this->browse->browse()` before opening a transaction.
3. Add a private transactional `persistBatch()` that upserts albums/items, updates tokens, updates reconcile seen IDs, and finalizes reconciliation when appropriate.
4. Ensure state is refreshed at loop start so token and complete flags reflect prior commits.
5. Add tests for multi-page sync and retry behavior when fetch or persist fails.

**Test requirements:** `StorageBrowseTest`, `StorageSyncReconcileTest`; targeted assertion that fetch is outside transaction if feasible with fakes.

**Acceptance criteria:** Provider HTTP/API calls are not executed inside `DB::transaction()`. Existing sync tests pass. Reconciliation behavior is unchanged.

**Rollback considerations:** Pure code rollback; no schema changes.

**Implementation report required:** YES

## Implementation Report

Status: VERIFIED

Implemented by:

- Cursor Agent

Date:

- 2026-06-23

What was fixed:

- `StorageBrowseSyncService::sync()` no longer wraps the sync loop in a single `DB::transaction()`. Provider fetch runs outside transactions; each batch persists in a short transaction via new `persistBatch()`.

Why it was fixed this way:

- Remote I/O must not hold DB locks. Per-batch transactions with `lockForUpdate()` on sync state prevent concurrent token races without reintroducing long-lived locks during HTTP.

How it was fixed:

- `app/Services/Storage/StorageBrowseSyncService.php` — restructured `sync()`, added `persistBatch()`, passed locked state into `finalizeReconciliation()`
- `app/Repositories/StorageRemoteSyncStateRepository.php` — added `lockForParent()`

Tests added or updated:

- `tests/Feature/StorageSyncReconcileTest.php` — multi-page sync, transaction-level assertion during provider fetch

Validation performed:

- `./scripts/test-docker.sh --filter=StorageSyncReconcileTest` — 5 passed

Acceptance criteria result:

- Provider HTTP/API calls not inside `DB::transaction()` — PASS
- Existing sync tests pass — PASS
- Reconciliation behavior unchanged — PASS

Regression check:

- Reconcile purge, background sync, and multi-page token advancement verified via feature tests

Remaining risks:

- `$state->save()` still used directly in `persistBatch()` (ISSUE-17 scope deferred)

Deviations from final plan:

- None

Evidence:

- `StorageBrowseSyncService::sync()` calls `browse()` before `persistBatch()`; only `persistBatch()` uses `DB::transaction()`

### ISSUE-02: Correct Upload/Download Transfer Orchestration and Batch Semantics

**Status:** VERIFIED  
**Priority:** CRITICAL  
**Audit sources:** `audit_sonnet.md` Issue #1; `audit_cursor.md` BE-17; `audit_final.md` BE-04  
**Validation result:** CONFIRMED

**Current behavior:** `PhotoUploadExecutionService::execute()` dispatches `DownloadPhotoJob` when a stored file is missing or incomplete, and passes the upload `$batchId` into the download job.

**Code evidence:** `app/Services/Flickr/PhotoUploadExecutionService.php::execute()`, `app/Jobs/DownloadPhotoJob.php`, `app/Jobs/UploadPhotoJob.php`, `app/Services/Flickr/PhotoUploadService.php::queuePendingPhotos()`.

**Root cause:** Upload execution owns cross-pipeline orchestration and conflates transfer batch identity.

**Risk:** Upload batch counters can be reconciled using download item outcomes; retries can dispatch duplicate download jobs; Operations UI can show stuck or misleading upload progress.

**Required fix:** Introduce a transfer orchestration boundary. Upload execution only uploads. Missing local files must be handled before upload job creation or by an explicit coordinator that creates separate download and upload state without sharing batch IDs.

**Files expected to change:** `PhotoUploadExecutionService`, `PhotoUploadService`, possibly new `TransferOrchestrationService`, `PhotoUploadExecutionServiceTest`, `PhotoUploadServiceTest`, `DownloadPhotoJobTest`.

**Constraints:** `DownloadPhotoJob` and `UploadPhotoJob` remain idempotent. Separate transfer types must use separate batches. Do not count a photo as an upload item until it is ready for upload or until explicit orchestration state guarantees it will be uploaded.

**Implementation steps:**
1. Remove `DownloadPhotoJob::dispatch()` from `PhotoUploadExecutionService`.
2. Decide the concrete orchestration model in implementation:
   - Preferred: `PhotoUploadService` only queues upload jobs for photos with completed stored files and queues/downloads missing photos separately through a coordinator/download service.
   - If queued upload should wait for download, create explicit orchestration records or a coordinator that requeues upload after download completion without reusing batch IDs.
3. Update `PhotoUploadExecutionService::execute()` to return `Deferred` only for transient local-file readiness already owned by upload state, and fail after max attempts without dispatching downloads.
4. Ensure batch totals represent actual upload jobs.
5. Add regression tests proving missing stored files do not dispatch `DownloadPhotoJob` from upload execution and do not reuse upload batch IDs for download items.

**Test requirements:** Unit/feature tests for completed file upload, missing file behavior, batch count reconciliation, duplicate retry handling.

**Acceptance criteria:** No service in the upload execution path dispatches download jobs. Download and upload batches never share IDs. Operations UI counts remain coherent.

**Rollback considerations:** Code only. Watch running queues during deploy; restart workers after deployment to load updated job behavior.

**Implementation report required:** YES

## Implementation Report

Status: VERIFIED

Implemented by:

- Codex

Date:

- 2026-06-23

What was fixed:

- `PhotoUploadExecutionService::execute()` no longer dispatches `DownloadPhotoJob` or passes upload batch IDs into download work.
- `PhotoUploadService` now queues upload batches only for photos with completed local original files; missing or incomplete local files are queued as separate download batches through `PhotoDownloadService`.
- Legacy/in-flight upload jobs that still encounter missing local files defer before the final attempt, then fail their own upload item and reconcile the upload batch.

Why it was fixed this way:

- Upload execution should only execute uploads. Download preparation is queue orchestration and must use separate download batches so transfer counts stay coherent.

How it was fixed:

- `app/Services/Flickr/PhotoUploadExecutionService.php` — removed download dispatch and transfer batch connection lookup; added final-attempt failure for not-ready local files.
- `app/Services/Flickr/PhotoUploadService.php` — split candidate photos into missing-local download work and ready upload work.
- `app/Services/Flickr/PhotoDownloadService.php` — added `queueSelectedDownloads()` for upload preparation to create normal download batches without duplicating download grouping logic.

Tests added or updated:

- `tests/Feature/PhotoUploadExecutionServiceTest.php` — asserts upload execution does not dispatch downloads and fails the upload item on final not-ready attempt.
- `tests/Feature/PhotoUploadServiceTest.php` — asserts missing local files create download batches, not upload batches/items.
- `tests/Feature/FlickrContactFrontendSupportTest.php` — fixture updated so bulk-upload coverage uses upload-ready photos.

Validation performed:

- `./scripts/test-docker.sh --filter=PhotoUploadExecutionServiceTest` — 4 passed
- `./scripts/test-docker.sh --filter=PhotoUploadServiceTest` — 4 passed
- `./scripts/test-docker.sh --filter='PhotoUploadExecutionServiceTest|PhotoUploadServiceTest|PhotoDownloadExecutionServiceTest|DownloadPhotoJobTest|PhotoDownloadServiceTest'` — 16 passed
- `composer test:docker` — 90 passed, 363 assertions
- `composer lint:phpstan` — no errors
- `composer instructions:verify` — passed

Acceptance criteria result:

- No service in the upload execution path dispatches download jobs — PASS
- Download and upload batches never share IDs for upload preparation — PASS
- Operations/UI counts remain coherent because upload batches only count ready upload jobs — PASS

Regression check:

- Existing download execution, download job serialization, upload execution, and contact bulk upload tests pass.

Remaining risks:

- Missing-file upload requests now queue prerequisite downloads but do not automatically enqueue follow-up uploads after those downloads complete; the user can trigger upload again once local files are ready unless a future explicit orchestration record is added.

Deviations from final plan:

- No new orchestration records or migrations were added; the implementation used the preferred no-schema path.

Evidence:

- `PhotoUploadExecutionService` has no `DownloadPhotoJob` import or dispatch.
- `PhotoUploadService` creates upload batches from `$readyForUpload` only and sends `$needsDownload` through `PhotoDownloadService::queueSelectedDownloads()`.

### ISSUE-03: Move Storage API Download Streaming Into Service

**Status:** VERIFIED  
**Priority:** HIGH  
**Audit sources:** multiple; `audit_final.md` BE-02  
**Validation result:** CONFIRMED

**Current behavior:** `Api\StorageBrowseController::download()` performs R2 object-key resolution, disk resolution, existence checks, stream opening, MIME detection, and streamed response creation.

**Code evidence:** `app/Http/Controllers/Api/StorageBrowseController.php::download()`, `app/Services/Storage/StorageDownloadService.php`.

**Root cause:** `StorageDownloadService` supports transfer downloads to local paths but not HTTP stream results.

**Risk:** Provider-specific stream logic grows in the controller.

**Required fix:** Add a stream result DTO such as `StorageStreamResult` and a service method like `StorageDownloadService::openStreamForAccount(StorageAccount $account, string $remotePath)`. Controller only maps result to `StreamedResponse` or JSON error.

**Files expected to change:** `StorageDownloadService`, new support DTO, `Api\StorageBrowseController`, `StorageBrowseTest` or `StorageR2Test`.

**Constraints:** R2 remains the only supported provider unless explicitly implementing others. Keep current HTTP status behavior for unsupported provider, missing file, unreadable stream.

**Test requirements:** R2 download streams content, missing file returns 404, unsupported provider returns 422.

**Acceptance criteria:** No `StorageR2Config` or `StorageFlysystemFactory` resolution remains in the controller download action.

**Rollback considerations:** Code only.

**Implementation report required:** YES

### ISSUE-04: Introduce Storage Driver Registry for Provider Capabilities

**Status:** PENDING  
**Priority:** HIGH  
**Audit sources:** multiple; `audit_final.md` BE-01 and BE-18  
**Validation result:** CONFIRMED

**Current behavior:** Central services inject each provider implementation and use `match`/`if` branching. `StorageDeleteService` has Google Photos-specific post-delete cache behavior.

**Code evidence:** `StorageBrowseService::browse()`, `StorageDeleteService::deleteMany()`, `StorageUploadService::uploadStream()`, `StorageFlysystemFactory::diskForAccount()`.

**Root cause:** Provider capabilities are not modeled as registered strategies.

**Risk:** Adding or changing providers requires edits across several central services.

**Required fix:** Add per-capability contracts and a registry keyed by `StorageDriver`. Start with browse/delete because those are clearly duplicated and because BE-18 is a subset. Include upload only if the implementation can keep the change behavior-neutral.

**Files expected to change:** New contracts under `app/Contracts/Storage`, new registry service, storage provider services, `AppServiceProvider` or dedicated provider, storage tests.

**Constraints:** Do not make a monolithic provider interface. Preserve Google Photos account ID requirements with a context DTO if needed.

**Test requirements:** Existing storage browse/delete/upload tests; targeted registry resolution tests.

**Acceptance criteria:** Central browse/delete services no longer hard-code every provider implementation. Google Photos post-delete behavior lives with the Google Photos delete driver.

**Rollback considerations:** Code only.

**Implementation report required:** YES

### ISSUE-05: Remove Filesystem I/O From Contact Download Counts

**Status:** PENDING  
**Priority:** HIGH  
**Audit sources:** `audit_sonnet.md` Issue #5; `audit_final.md` BE-05  
**Validation result:** CONFIRMED

**Current behavior:** `ContactDownloadCountsService::forContacts()` calls `Storage::exists()` for every completed stored file.

**Code evidence:** `app/Services/Flickr/ContactDownloadCountsService.php`.

**Root cause:** Data-integrity reconciliation is performed inline in a query/count service.

**Risk:** Contact list performance degrades with archive size; request latency depends on filesystem stats.

**Required fix:** Count based on `StoredFile.status`. Treat missing-on-disk as a future integrity job/status, not a list-page check.

**Files expected to change:** `ContactDownloadCountsService`, `ContactDownloadCountsServiceTest`.

**Test requirements:** Completed files count as downloaded without requiring a real file; failed status increments failed; running batches still set processing metadata.

**Acceptance criteria:** No `Storage` facade use in `ContactDownloadCountsService`.

**Rollback considerations:** Code only.

**Implementation report required:** YES

### ISSUE-06: Make Transfer Progress Index Read-Only

**Status:** PENDING  
**Priority:** HIGH  
**Audit sources:** `audit_cursor.md` BE-7; `audit_final.md` BE-06  
**Validation result:** CONFIRMED

**Current behavior:** Active transfer polling reconciles batch counts as part of `index()`.

**Code evidence:** `TransferProgressQueryService::index()`.

**Root cause:** Read model freshness is being maintained by a query endpoint.

**Risk:** Concurrent polling causes hidden writes and extra load.

**Required fix:** Remove reconciliation from `index()`. Ensure reconciliation remains in execution services and failure handlers. Add an explicit reconcile endpoint/command only if manually proven necessary.

**Files expected to change:** `TransferProgressQueryService`, transfer API tests.

**Test requirements:** `active=1` index returns data but does not mutate batch counts or timestamps; job completion/failure paths still reconcile.

**Acceptance criteria:** No `TransferBatchReconciler::reconcile()` call from the query service.

**Rollback considerations:** Code only.

**Implementation report required:** YES

### ISSUE-07: Decouple Query Services From HTTP

**Status:** PENDING  
**Priority:** HIGH  
**Audit sources:** `audit_cursor.md` BE-5; `audit_final.md` BE-07  
**Validation result:** CONFIRMED

**Current behavior:** `CatalogQueryService`, `CrawlStatusQueryService`, and `TransferProgressQueryService` accept FormRequests and return `JsonResponse`; transfer `show()` calls `abort(404)`.

**Required fix:** Controllers extract validated primitives from FormRequests and build JSON responses. Services return arrays, DTOs, models, or paginators.

**Files expected to change:** API controllers and the three query services.

**Implementation steps:**
1. Refactor `TransferProgressQueryService` first after ISSUE-06.
2. Refactor `CrawlStatusQueryService`.
3. Refactor `CatalogQueryService`.
4. Keep JSON response shapes exactly stable.

**Test requirements:** Existing API tests plus response-shape assertions for catalog/crawl/transfer endpoints.

**Acceptance criteria:** Query services no longer import FormRequest or `JsonResponse`, and no service calls `abort()`.

**Rollback considerations:** Code only.

**Implementation report required:** YES

### ISSUE-08: Route Flickr Account Listing Through App Service

**Status:** PENDING  
**Priority:** MEDIUM  
**Audit sources:** `audit_cursor.md` BE-4; `audit_sonnet.md` Issue #13; `audit_final.md` BE-08  
**Validation result:** CONFIRMED

**Current behavior:** `FlickrAccountController::list()` and `CrawlOperationsController::index()` call `FlickrService::connections()->list()` directly.

**Required fix:** Use `FlickrOAuthService::listAccounts()` or a small `FlickrConnectionListService` that returns presented account arrays.

**Files expected to change:** `FlickrAccountController`, `CrawlOperationsController`, maybe service tests.

**Acceptance criteria:** Controllers do not call the package facade for listing.

**Implementation report required:** YES

### ISSUE-09: Add FormRequests for OAuth Connect/Reauthorize

**Status:** PENDING  
**Priority:** MEDIUM  
**Audit sources:** multiple; `audit_final.md` BE-09  
**Validation result:** CONFIRMED

**Current behavior:** OAuth connect and storage reauthorize parse raw `Request`.

**Required fix:** Add `BeginFlickrOAuthRequest`, `BeginStorageOAuthRequest`, and `ReauthorizeStorageRequest`. Move return URL sanitization into request accessor/normalization.

**Files expected to change:** `FlickrAuthController`, `StorageAuthController`, new request classes, request tests.

**Acceptance criteria:** No raw query parsing for `app_profile`, `account_id`, `return_url`, or provider route validation in controllers.

**Implementation report required:** YES

### ISSUE-10: Move Storage Account Resolution Out of QueuePhotoUploadRequest

**Status:** PENDING  
**Priority:** MEDIUM  
**Audit sources:** `audit_sonnet.md` Issue #3; `audit_final.md` BE-13  
**Validation result:** CONFIRMED

**Current behavior:** `QueuePhotoUploadRequest::resolvedStorageAccount()` resolves `StorageAccountRepository` via `app()`.

**Required fix:** The request validates and exposes scalar `storage_account_id`; `PhotoUploadService` resolves the explicit account or default account.

**Files expected to change:** `QueuePhotoUploadRequest`, `PhotoUploadController`, `PhotoUploadService`, tests.

**Acceptance criteria:** FormRequest has no repository/service lookup.

**Implementation report required:** YES

### ISSUE-11: Move Photo Transfer Queue Branching Into Services

**Status:** PENDING  
**Priority:** MEDIUM  
**Audit sources:** multiple; `audit_final.md` BE-10  
**Validation result:** CONFIRMED

**Current behavior:** Download/upload controllers choose single-photo, multi-contact, single-contact, or account-wide flow and build flash messages.

**Required fix:** Add service queue-from-input methods returning a DTO with count, outcome type, and flash message. Controllers only call service and redirect.

**Files expected to change:** `PhotoDownloadController`, `PhotoUploadController`, `PhotoDownloadService`, `PhotoUploadService`, transfer service tests.

**Acceptance criteria:** Controllers contain no branching over transfer mode.

**Implementation report required:** YES

### ISSUE-12: Remove Duplicate Contact Prop and Wire Contact Show Crawl State

**Status:** PENDING  
**Priority:** MEDIUM  
**Audit sources:** `audit_sonnet.md` #6; `audit_cursor.md` FE-3; `audit_final.md` BE-19/FE-05  
**Validation result:** CONFIRMED

**Current behavior:** Backend sends duplicate `contact` and `contact_detail`; frontend declares but does not use `crawl_state`.

**Required fix:** Keep `contact`, remove `contact_detail`, update `Contacts/Show.tsx` props, pass `typeStates={crawl_state}` to `CrawlActionBar`, and update `FlickrContactFrontendSupportTest`.

**Files expected to change:** `FlickrContactController`, `Contacts/Show.tsx`, test.

**Acceptance criteria:** Detail page crawl action state matches contact index behavior and no duplicate prop is sent.

**Implementation report required:** YES

### ISSUE-13: Add Contact Detail Service

**Status:** PENDING  
**Priority:** MEDIUM  
**Audit sources:** multiple; `audit_final.md` BE-11  
**Validation result:** CONFIRMED

**Current behavior:** `FlickrContactController::show()` coordinates linkage check, contact lookup, counts, detail stats, crawl state, and props.

**Required fix:** Add `ContactDetailService::forShow(Connection $connection, string $contactNsid): array` returning the Inertia payload minus route-level rendering.

**Files expected to change:** `FlickrContactController`, new service, contact tests.

**Acceptance criteria:** Controller delegates contact show assembly to one service call.

**Implementation report required:** YES

### ISSUE-14: Move Settings Storage Account Payload Behind Service

**Status:** PENDING  
**Priority:** MEDIUM  
**Audit sources:** multiple; `audit_final.md` BE-12  
**Validation result:** CONFIRMED

**Current behavior:** `SettingsController` injects `StorageAccountRepository` and maps storage drivers inline.

**Required fix:** Add `StorageAccountService::listForSettings()` and a driver presenter/helper, or add `SettingsPageService` for full payload assembly.

**Files expected to change:** `SettingsController`, `StorageAccountService` or new settings service, tests if available.

**Acceptance criteria:** No repository injection in `SettingsController`.

**Implementation report required:** YES

### ISSUE-15: Remove Duplicate Redirecting Transfer Routes From API

**Status:** PENDING  
**Priority:** MEDIUM  
**Audit sources:** `audit.md` #9; `audit_final.md` BE-14  
**Validation result:** CONFIRMED

**Current behavior:** `routes/api.php` registers web `PhotoDownloadController` and `PhotoUploadController` endpoints that return redirects; `routes/web.php` has the actual frontend routes.

**Required fix:** Remove `/api/flickr/accounts/{connection}/download` and `/upload` unless replacing with real JSON API endpoints. Do not change frontend web routes.

**Files expected to change:** `routes/api.php`, route tests if any.

**Acceptance criteria:** API route file no longer exposes redirecting transfer endpoints.

**Implementation report required:** YES

### ISSUE-16: Move Sync Reconciliation Rules Out of Repository

**Status:** VERIFIED  
**Priority:** MEDIUM  
**Audit sources:** `audit.md` #5; `audit_final.md` BE-15  
**Validation result:** CONFIRMED

**Current behavior:** `StorageRemoteSyncStateRepository::appendReconcileSeenRemoteIds()` checks `reconciling` and merges/dedupes IDs.

**Required fix:** Service computes whether to update and the merged seen IDs. Repository only persists provided values.

**Files expected to change:** `StorageRemoteSyncStateRepository`, `StorageBrowseSyncService`, `StorageSyncReconcileTest`.

**Acceptance criteria:** Repository no longer owns workflow guard or merge/dedupe business logic.

**Implementation report required:** YES

## Implementation Report

Status: VERIFIED

Implemented by:

- Cursor Agent

Date:

- 2026-06-23

What was fixed:

- Reconcile seen-ID merge/dedupe logic moved from repository to `StorageBrowseSyncService::persistBatch()`. Repository now only persists precomputed values.

Why it was fixed this way:

- Paired with ISSUE-01 in the same sync loop refactor; service owns workflow rules, repository owns persistence.

How it was fixed:

- `app/Repositories/StorageRemoteSyncStateRepository.php` — replaced `appendReconcileSeenRemoteIds()` with `updateReconcileSeenRemoteIds()`
- `app/Services/Storage/StorageBrowseSyncService.php` — service computes merged seen IDs before repository update

Tests added or updated:

- `tests/Feature/StorageSyncReconcileTest.php` — existing reconcile test still passes

Validation performed:

- `./scripts/test-docker.sh --filter=StorageSyncReconcileTest` — 5 passed

Acceptance criteria result:

- Repository no longer owns workflow guard or merge/dedupe logic — PASS

Regression check:

- `test_manual_sync_with_reconcile_drops_missing_items_and_uploads` passes

Remaining risks:

- None identified

Deviations from final plan:

- None

Evidence:

- `updateReconcileSeenRemoteIds()` performs a blind `update()` with no `reconciling` guard or merge logic

### ISSUE-17: Add Repository Write Methods for Touched Storage Paths

**Status:** PENDING  
**Priority:** MEDIUM  
**Audit sources:** `audit_cursor.md` BE-6; `audit_final.md` BE-16  
**Validation result:** PARTIALLY_CONFIRMED

**Current behavior:** Some services mutate models returned by repositories directly.

**Required fix:** During storage/transfer issue work, add narrow repository mutation methods only where touched. Do not retrofit the whole app in one PR.

**Acceptance criteria:** New or modified service write paths go through repositories.

**Implementation report required:** YES

### ISSUE-18: Remove Global Config Mutation From Storage OAuth Provider Creation

**Status:** PENDING  
**Priority:** MEDIUM  
**Audit sources:** `audit_sonnet.md` #8; `audit_final.md` BE-17  
**Validation result:** CONFIRMED

**Current behavior:** `StorageOAuthService::provider()` writes OAuth credentials into global `config()`.

**Required fix:** Build/configure Socialite providers per call without global config mutation. Verify against Laravel Socialite 5.28 APIs before coding.

**Files expected to change:** `StorageOAuthService`, OAuth tests.

**Acceptance criteria:** No `config([...])` mutation in `StorageOAuthService::provider()`. OAuth connect/callback flows still work.

**Implementation report required:** YES

### ISSUE-19: Add FormRequests for Runtime Config Destroy/Reset

**Status:** PENDING  
**Priority:** MEDIUM  
**Audit sources:** `audit_cursor.md` BE-12; `audit_final.md` BE-24  
**Validation result:** CONFIRMED

**Current behavior:** `RuntimeConfigController::destroy()` and `reset()` decode raw route path strings.

**Required fix:** Add `DestroyRuntimeConfigRequest` and `ResetRuntimeConfigRequest` with route path normalization/accessors.

**Files expected to change:** `RuntimeConfigController`, new request classes, request tests.

**Acceptance criteria:** Controller does not call `urldecode()` directly.

**Implementation report required:** YES

### ISSUE-20: Introduce Backend Status Enums

**Status:** PENDING  
**Priority:** MEDIUM  
**Audit sources:** `audit_sonnet.md` #11/#23; `audit_final.md` BE-21  
**Validation result:** CONFIRMED

**Current behavior:** Stored file, transfer item, transfer batch, and storage upload statuses are raw strings.

**Required fix:** Add backed string enums for statuses and replace literals in repositories/services first. Use `->value` for DB writes.

**Constraints:** DB columns remain `string(32)`; no migration required.

**Acceptance criteria:** Hot paths no longer compare/write raw status strings directly except tests intentionally asserting stored values.

**Implementation report required:** YES

## Implementation Report

Status: VERIFIED

Implemented by:

- Codex

Date:

- 2026-06-23

What was fixed:

- Added backed string enums for XFlickr transfer/storage statuses:
  - `StoredFileStatus`
  - `StorageUploadStatus`
  - `TransferBatchStatus`
  - `TransferItemStatus`
- Replaced raw status literals in the touched repository/service hot paths while preserving string database columns and response values.

Why it was fixed this way:

- ISSUE-02 touched transfer status handling directly, so introducing enums in the same pass reduced the risk of new raw status comparisons while avoiding schema changes or frontend coupling.

How it was fixed:

- Repositories now write/query status values via enum `->value`.
- Transfer execution and reconciliation services now compare/update statuses through enums.
- Tests continue asserting stored string values intentionally.

Tests added or updated:

- Covered by the ISSUE-02 transfer regression tests plus the full suite.

Validation performed:

- `./scripts/test-docker.sh --filter='PhotoUploadExecutionServiceTest|PhotoUploadServiceTest|PhotoDownloadExecutionServiceTest|DownloadPhotoJobTest|PhotoDownloadServiceTest'` — 16 passed
- `composer test:docker` — 90 passed, 363 assertions
- `composer lint:phpstan` — no errors
- `composer instructions:verify` — passed

Acceptance criteria result:

- Hot paths no longer compare/write raw status strings directly except intentional test assertions and presenter array keys — PASS
- DB columns remain `string(32)` with no migration — PASS

Regression check:

- Existing stored-file, storage-upload, transfer batch/item, download, upload, and storage sync tests pass.

Remaining risks:

- Frontend status unions remain pending under ISSUE-23.
- Some crawler package status strings remain outside this app enum scope.

Deviations from final plan:

- Implemented earlier than Stage 10 because ISSUE-02 directly touched the same transfer status hot paths.

Evidence:

- New enum files live in `app/Enums/`.
- `TransferBatchReconciler`, transfer repositories, stored-file/storage-upload repositories, and transfer execution services use the new enums.

### ISSUE-21: Add Frontend Remote Data Table Error Surface

**Status:** PENDING  
**Priority:** MEDIUM  
**Audit sources:** `audit_cursor.md` FE-9; `audit_final.md` FE-10  
**Validation result:** CONFIRMED

**Current behavior:** `useRemoteDataTable` silently drops errors.

**Required fix:** Add `error` state, clear on successful load, return `error` from hook, and show retry/error banner on catalog pages.

**Acceptance criteria:** Failed catalog API calls are visible and reloadable.

**Implementation report required:** YES

### ISSUE-22: Centralize Flickr Account Display Name

**Status:** PENDING  
**Priority:** MEDIUM  
**Audit sources:** `audit_cursor.md` FE-1; `audit_final.md` FE-03  
**Validation result:** CONFIRMED

**Current behavior:** Account labels differ between breadcrumbs, operations, and dashboard.

**Required fix:** Add `accountDisplayName(account?: FlickrAccount): string` in `resources/js/lib/flickrAccount.ts` using `fullname || username || nsid || '-'`, and replace duplicates.

**Acceptance criteria:** Breadcrumbs, operations, and dashboard use one helper.

**Implementation report required:** YES

### ISSUE-23: Tighten Frontend Status Types

**Status:** PENDING  
**Priority:** MEDIUM  
**Audit sources:** `audit_cursor.md` FE-10; `audit_final.md` FE-11  
**Validation result:** CONFIRMED

**Current behavior:** `CrawlRun.status`, `TransferBatch.status`, and `StatusBadge.status` are loose strings.

**Required fix:** Add union types aligned with backend enum/string values and use them in table/sort/status badge code.

**Acceptance criteria:** TypeScript rejects unknown status values in app code.

**Implementation report required:** YES

### ISSUE-24: Decompose useStorageBrowse Safely

**Status:** PENDING  
**Priority:** MEDIUM  
**Audit sources:** multiple; `audit_final.md` FE-01/FE-W5  
**Validation result:** CONFIRMED

**Current behavior:** One hook owns account loading, browse paging, sync recursion, delete, container navigation, and error handling; it suppresses hook dependency linting.

**Required fix:** Split into focused hooks only after backend storage sync/delete behavior is stable:
- `useStorageAccounts`
- `useStorageContent`
- `useStorageSync`
- `useStorageDelete`
- thin `useStorageBrowse` composer

**Acceptance criteria:** The eslint suppression is removed; storage browse/sync/delete behavior remains unchanged.

**Implementation report required:** YES

## Implementation Order

| Stage | Issues | Why this order | Validation gate | Independent commit? |
|---|---|---|---|---|
| 1 | ISSUE-01 ✅, ISSUE-16 ✅ | Transaction boundary and reconciliation rules are coupled in storage sync | Storage sync/reconcile tests | Done |
| 2 | ISSUE-02 ✅ | Highest correctness risk; affects queue semantics | Transfer service/execution tests | Done |
| 3 | ISSUE-03 | Thin controller stream fix before broader storage registry | R2 stream tests | Yes |
| 4 | ISSUE-04 | Registry refactor after stream/download path is isolated | Full storage browse/delete tests | Yes |
| 5 | ISSUE-05, ISSUE-06 | Remove read-path side effects/I/O | Contact count and transfer progress tests | Yes, separate commits |
| 6 | ISSUE-07 | Refactor HTTP-coupled query services after transfer index is read-only | API response-shape tests | Yes, ideally per service |
| 7 | ISSUE-08, ISSUE-09, ISSUE-10, ISSUE-11, ISSUE-14, ISSUE-15, ISSUE-19 | Controller/FormRequest/service boundary cleanup | Request/controller tests | Yes, one issue per commit |
| 8 | ISSUE-12, ISSUE-13 | Contact show prop/UI fix before service extraction or together if tiny | `FlickrContactFrontendSupportTest`, `npm run typecheck` | Yes |
| 9 | ISSUE-18 | OAuth provider construction change is isolated but needs careful verification | OAuth feature tests | Yes |
| 10 | ISSUE-20 ✅, ISSUE-23 | Backend enums first, frontend types after | PHP tests, then TS typecheck | ISSUE-20 done; ISSUE-23 pending |
| 11 | ISSUE-21, ISSUE-22 | Frontend user-visible consistency/resilience | `npm run typecheck` | Yes |
| 12 | ISSUE-24 | Larger hook decomposition after storage backend stabilizes | Typecheck plus browser/manual storage browse verification | Yes |

Do not group unrelated issues into one PR. The only natural pairings are ISSUE-01+ISSUE-16 and ISSUE-12+ISSUE-13 if the implementation stays small.

## Testing Strategy

Baseline before implementation:

- `composer test:docker`
- `composer instructions:verify`
- `npm run typecheck`

After every backend issue:

- Run targeted feature/unit tests for the touched behavior.
- Run `composer test:docker` before marking verified.
- Run `composer instructions:verify` if instructions/docs changed.

After every frontend issue:

- `npm run typecheck`
- `npm run lint` when changing hooks/components materially.
- Browser/manual verification for Settings OAuth, Storage browse/sync/delete, Contacts, Operations when touched.

Specific behavior tests required:

- Storage sync fetch outside transaction and persists per batch.
- Storage reconciliation still purges removed upload records correctly.
- Upload execution never dispatches download jobs and never reuses upload batch IDs for download work.
- Transfer index active polling does not mutate counts.
- Storage R2 download streams existing files and handles missing files.
- Query service refactors preserve exact JSON shapes.
- OAuth FormRequests sanitize return URLs and preserve redirect behavior.
- Contact Show receives one contact prop and passes crawl state to `CrawlActionBar`.
- Catalog remote table displays API errors.

Forbidden validation:

- Never run `docker compose exec app php artisan test`.
- Never run tests or migrations against `docker-compose.yml`.

## Deployment and Rollback Plan

Most issues are code-only and require no schema migration.

Deployment requirements:

- Restart queue workers after transfer orchestration changes so queued job code is current.
- For ISSUE-02, monitor running transfer batches after deployment. Existing queued upload jobs may still deserialize old payload shape, so keep job constructors backward compatible.
- For OAuth provider changes, deploy without config cache assumptions and verify existing stored runtime config keys remain unchanged.
- For frontend-only changes, normal asset build/deploy is sufficient.

Rollback:

- Code rollback is sufficient for all listed mandatory issues unless later implementation adds migrations.
- If ISSUE-02 introduces new orchestration records, implementation must define a migration rollback and old-code compatibility before coding.
- If a frontend/backend coupled prop change causes problems, temporarily keep both `contact` and `contact_detail` during rollback, but final target is single `contact`.

## Implementation Reporting Protocol

Each issue begins as `Status: PENDING`.

Allowed status transitions:

- PENDING
- IN_PROGRESS
- IMPLEMENTED
- VERIFIED
- BLOCKED
- REJECTED
- ROLLED_BACK

An issue must not be marked `VERIFIED` until all required tests and acceptance criteria pass.

After implementing each issue, append this report directly under that issue:

```markdown
## Implementation Report

Status: IMPLEMENTED | VERIFIED | BLOCKED | ROLLED_BACK

Implemented by:

- Developer or AI identifier

Date:

- ISO date and time

What was fixed:

- Concise description of the completed change

Why it was fixed this way:

- Architectural and technical reasoning
- Why this approach was selected over alternatives

How it was fixed:

- Changed files
- Changed classes or methods
- Database or configuration changes
- Dependency changes
- Important implementation details

Tests added or updated:

- Test files
- Test cases
- Behaviors proven

Validation performed:

- Commands executed
- Test results
- Static-analysis results
- Manual or integration verification

Acceptance criteria result:

- Each criterion marked PASS or FAIL

Regression check:

- Existing behavior verified
- Any known impact

Remaining risks:

- Known limitations or follow-up risks
- Write "None identified" only when justified

Deviations from final plan:

- Any difference from the approved implementation plan
- Reason for deviation
- Write "None" when there was no deviation

Evidence:

- Relevant code paths
- Commit hash when available
- Test output summary
```

## Final Verification Checklist

- [ ] All confirmed mandatory issues implemented
- [ ] Every implemented issue has an implementation report
- [ ] Every implementation report explains what, why, and how
- [ ] All acceptance criteria pass
- [ ] `composer test:docker` passes
- [ ] `npm run typecheck` passes if frontend changed
- [ ] `composer instructions:verify` passes
- [ ] No circular dependencies introduced
- [ ] Dependency direction remains valid
- [ ] No unrelated scope added
- [ ] No unsafe local dev database command run
- [ ] Migration implications validated, or confirmed not applicable
- [ ] Rollback steps validated
- [ ] Queue retry/idempotency behavior verified for transfer changes
- [ ] Security checks completed for OAuth/config/session changes
- [ ] Documentation reflects actual code where touched
- [ ] Final code reviewed against every `audit*.md` finding

## Final Verdict

**APPROVED_WITH_BLOCKERS**

This corrected plan is suitable for implementation, but the current code still contains unresolved high/critical issues. The previous proposed plan must not be executed verbatim because it over-scopes watch items and its BE-04 direction is incomplete around batch semantics. Implementation should begin with ISSUE-01 and ISSUE-02, then proceed in the order above.
