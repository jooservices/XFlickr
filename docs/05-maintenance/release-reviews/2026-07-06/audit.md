# XFlickr Codebase Audit (Grok)

## Purpose of Audit

Evaluate the current state of the XFlickr backend (Laravel 12) and frontend (React 19 + Inertia 3) against:

- SOLID, DRY, KISS, YAGNI principles
- PHP design patterns ([refactoring.guru/design-patterns/php](https://refactoring.guru/design-patterns/php))
- Laravel standards, in particular the mandated flow:
  `Controller → FormRequest → Service → Repository → Model`
- React / Node.js standards
- Project-specific rules:
  - Controllers grouped by business feature; no `__invoke` single-action controllers
  - One Service per business concern (or tightly related concerns); domain events fired from the Service layer
  - Services must not have circular dependencies (A → B is fine; B → A is forbidden — introduce orchestrator Service C when both directions are needed)
  - Constructor DI only when a dependency is used across most methods; otherwise `app(Class::class)` inside the method that needs it
  - Consistency across the codebase; design patterns used to reduce duplication, complexity, and keep code extendable

This audit is **read-only** — no source files were modified.

## Expectation

A concrete, evidence-backed issue list (with file references), each explaining which principle/rule is broken, why it matters, how to fix it, and the risk of leaving it unfixed — so issues can be triaged and scheduled individually.

---

## Format Check

| Section | Required | Status |
|---|---|---|
| Purpose of audit | Yes | ✅ Present |
| Expectation | Yes | ✅ Present |
| Issue index with Status column | Yes | ✅ Present |
| Detail per issue (Break / Explain / Fix / Risk) | Yes | ✅ Present |
| Compliant areas documented | Yes | ✅ Present |
| AI check / report-back after implementation | Yes | ✅ Present |
| Issues verified against source (not docs alone) | Yes | ✅ Verified |

---

## Audit Results — Issue Index

| # | Issue | Severity | Status / Check |
|---|---|---|---|
| [1](#issue-1) | Storage provider browse/delete/token services lack shared interfaces — `match()` dispatch violates OCP/DIP (Strategy pattern missing) | High | ⬜ Open |
| [2](#issue-2) | `Api/StorageBrowseController::download()` duplicates R2 stream logic inline instead of delegating to a Service | High | ⬜ Open |
| [3](#issue-3) | OAuth `connect` / `reauthorize` actions bypass FormRequest validation (Flickr + Storage) | Medium | ⬜ Open |
| [4](#issue-4) | Two incompatible Repository base patterns (`EloquentRepository` vs ad-hoc Crawler query repositories) | Medium | ⬜ Open |
| [5](#issue-5) | Business decision logic embedded in `StorageRemoteSyncStateRepository` | Medium | ⬜ Open |
| [6](#issue-6) | All Repository bindings are concrete classes — no interfaces (DIP; YAGNI watch item) | Low | ⬜ Open / Watch |
| [7](#issue-7) | `FlickrOAuthService` constructor-injects a dependency used in only one private method | Low | ⬜ Open |
| [8](#issue-8) | Photo transfer feature split across two single-method controllers | Low | ⬜ Open |
| [9](#issue-9) | `routes/api.php` layering inconsistencies — web-namespace controllers, duplicate download/upload routes | Medium | ⬜ Open |
| [10](#issue-10) | Duplicate pagination/filtering boilerplate across Crawler query repositories | Low | ⬜ Open |
| [11](#issue-11) | Oversized React credential panels mix data, OAuth flow, and modal UI | Medium | ⬜ Open |
| [12](#issue-12) | Duplicated modal/dialog markup between Flickr and Storage credential panels | Low | ⬜ Open |
| [13](#issue-13) | Local TS interfaces re-declared instead of imported from `types.ts` | Low | ⬜ Open |
| [14](#issue-14) | `useStorageBrowse` hook mixes four unrelated concerns | Low | ⬜ Open |
| [15](#issue-15) | `Crawl/Operations.tsx` renders three near-identical tables inline | Low | ⬜ Open |
| [16](#issue-16) | `PhotoDownloadController` / `PhotoUploadController` contain dispatch orchestration logic (branching, loops) | Medium | ⬜ Open |
| [17](#issue-17) | `FlickrContactController::show()` orchestrates four Services in the controller | Medium | ⬜ Open |
| [18](#issue-18) | `SettingsController` injects `StorageAccountRepository` directly, bypassing Service layer | Medium | ⬜ Open |
| [19](#issue-19) | Presenter classes split inconsistently between `app/Services/` and `app/Support/` | Low | ⬜ Open |
| [20](#issue-20) | `StorageRemoteAlbum` / `StorageRemoteItem` expose `toBrowseArray()` presentation on Models | Low | ⬜ Open |

**Compliant / verified-clean areas:**

| Area | Status / Check |
|---|---|
| No `__invoke` single-action controllers | ✅ Verified (`grep` — zero matches) |
| No circular Service→Service constructor dependencies | ✅ Verified (full graph traced — DAG) |
| Active Jobs delegate business logic to Services | ✅ Verified (`DownloadPhotoJob`, `UploadPhotoJob`) |
| Services do not use raw Eloquent queries | ✅ Verified (`grep` — zero `::query()` in Services) |
| Models are lean (relationships, casts, boot hooks) | ✅ Verified |
| FormRequest validation used consistently (except Issue #3) | ✅ Verified |
| React: no `console.log` / `debugger` / `any` abuse | ✅ Verified |
| `DashboardService` / `CrawlStatusQueryService` use method-level DI for presenters | ✅ Good pattern |

---

## Detail

### Issue #1
**Storage provider services have no shared interface — `match()` dispatch breaks Open/Closed + Dependency Inversion (missing Strategy pattern)**

`<Break rule>`: Open/Closed Principle and Dependency Inversion Principle (SOLID); project guidance to use design patterns to reduce duplicate code.

`<Explain>`: `StorageBrowseService` (`app/Services/Storage/StorageBrowseService.php:50-55`) and `StorageDeleteService` (`app/Services/Storage/StorageDeleteService.php:39-44`) both hard-code `match ($driver) { ... }` across four concrete provider classes. No PHP `interface` exists anywhere under `app/` (confirmed: zero `interface` declarations). Provider `browse()` signatures are inconsistent — e.g. `GooglePhotosBrowseService::browse()` takes an extra `$accountId` argument that other providers do not — tolerable only because nothing enforces a contract.

Adding a fifth provider requires editing multiple facade classes (browse, delete, and potentially token services). `StorageBrowseService` depends on four concrete implementations rather than one abstraction, making isolated unit tests harder.

`<What/why/how to fix>`: Introduce `StorageBrowserInterface` and `StorageDeleterInterface` with normalized signatures. Have each provider implement them. Register a `StorageDriver → implementation` map in a factory or ServiceProvider. Facade services depend only on the factory/registry, not on four concrete classes.

`<Risk>`: Low risk to fix (behavior-preserving refactor), but touches every provider class and its tests — ship as its own PR. Leaving it unfixed increases cost of every future provider addition.

---

### Issue #2
**`Api/StorageBrowseController::download()` duplicates R2 stream logic inline instead of delegating to a Service**

`<Break rule>`: Mandated flow `Controller → FormRequest → Service → Repository → Model` — `app/Http/Controllers/Api/StorageBrowseController.php:154-208`.

`<Explain>`: Every other action in this controller delegates to a Service. `download()` is the exception: lines 178-204 build `StorageR2Config`, resolve a Flysystem disk via `app(StorageFlysystemFactory::class)`, check existence, and open a read stream — all infrastructure concerns in the HTTP layer. A `StorageDownloadService` already exists (`app/Services/Storage/StorageDownloadService.php`) with R2 object-key and Flysystem logic, but it only supports `downloadToPath()` for the transfer pipeline — not HTTP streaming. The controller therefore re-implements overlapping R2 I/O instead of extending or calling a stream-oriented Service method.

`<What/why/how to fix>`: Add `StorageDownloadService::openStreamForAccount(StorageAccount $account, string $remotePath): StreamResult` (or a dedicated `StorageStreamService`) that owns credential parsing, disk resolution, existence check, and stream open. Controller translates the result into `StreamedResponse` only.

`<Risk>`: Works today for R2 only. Adding Drive/OneDrive/Google Photos downloads without fixing this will accumulate more provider-specific I/O in the controller.

---

### Issue #3
**OAuth `connect` / `reauthorize` actions bypass FormRequest validation (Flickr + Storage)**

`<Break rule>`: Mandated flow requires FormRequest-level validation before reaching a Service.

`<Explain>`:
- `StorageAuthController::connect()` and `::reauthorize()` take bare `Illuminate\Http\Request` (`app/Http/Controllers/StorageAuthController.php:20-58`). Sibling actions (`callback`, `disconnect`, `setDefault`, `connectR2`) use dedicated FormRequests. Validation/sanitization for `provider`, `account_id`, and `return_url` lives in a private `sanitizeReturnUrl()` helper (lines 131-142).
- `FlickrAuthController::connect()` also takes bare `Request` (`app/Http/Controllers/FlickrAuthController.php:18-20`), reading `app_profile` from query without a FormRequest, while `callback`, `disconnect`, and `activate` use FormRequests.

Functionally safe (open-redirect guard exists for Storage), but inconsistent and not declaratively testable.

`<What/why/how to fix>`: Add `BeginStorageOAuthRequest` and `BeginFlickrOAuthRequest` validating route/query inputs. Move `sanitizeReturnUrl()` into the Storage FormRequest as an accessor, matching `FlickrOAuthCallbackRequest` / `StorageOAuthCallbackRequest` patterns.

`<Risk>`: Low — consistency/maintainability fix, not a known security hole.

---

### Issue #4
**Two incompatible Repository base patterns**

`<Break rule>`: Consistency requirement; Liskov/interface-consistency for the Repository layer.

`<Explain>`: Root repositories (`StoredFileRepository`, `StorageAccountRepository`, etc.) extend `Jooservices\LaravelRepository\Repositories\EloquentRepository` with `HasCrud`/`HasFilter` traits. `app/Repositories/Crawler/*QueryRepository` classes do not extend that base — they build queries directly. `CatalogQueryRepository` injects `QuerySorter`; siblings do so inconsistently. No documentation in `docs/00-architecture` explains this split — it reads as drift, not intent.

`<What/why/how to fix>`: Either (a) align Crawler query repositories with the shared base where appropriate, or (b) document them as a deliberate read-only "Query Repository" pattern with a shared base/helper. Bundle pagination helper extraction (Issue #10) with whichever path is chosen.

`<Risk>`: Low immediate risk; compounds every time a new repository is added.

---

### Issue #5
**Business decision logic embedded in `StorageRemoteSyncStateRepository`**

`<Break rule>`: Single Responsibility — Repositories own data access, not business outcomes.

`<Explain>`: `appendReconcileSeenRemoteIds()` at `app/Repositories/StorageRemoteSyncStateRepository.php:55-70`:
- Line 62 (`if ($state === null || ! $state->reconciling) return;`) is a business rule — "only record seen IDs during active reconciliation."
- Lines 66-67 (merge/dedup) is data transformation a Service should own and pass as a finished value.

Reconciliation workflow logic is split between the calling Service and this Repository method.

`<What/why/how to fix>`: Move the `reconciling` guard and merge/dedup into the orchestrating Service (e.g. `StorageBrowseSyncService`). Shrink the Repository to `updateSeenRemoteIds(int $accountId, string $parentRemoteId, array $mergedIds): void`.

`<Risk>`: Low to fix; until fixed, debugging reconciliation requires reading the wrong layer.

---

### Issue #6
**All Repository bindings are concrete classes — no interfaces (DIP; YAGNI watch item)**

`<Break rule>`: Dependency Inversion Principle.

`<Explain>`: `RepositoryServiceProvider` (`app/Providers/RepositoryServiceProvider.php:30-50`) registers every repository as a singleton bound to its concrete class. Services type-hint concrete Repository classes. Consistent throughout, but not inverted.

`<What/why/how to fix>`: **Do not** retrofit interfaces wholesale (YAGNI for a single-app, single-DB system). Introduce interfaces only where a second implementation is genuinely planned — Storage provider abstractions (Issue #1) are the most plausible candidate.

`<Risk>`: None currently. Watch item only.

---

### Issue #7
**`FlickrOAuthService` constructor-injects a dependency used in only one private method**

`<Break rule>`: Project DI rule — constructor DI when used across most methods; `app($class)` inside method otherwise.

`<Explain>`: `FlickrOAuthService` (`app/Services/Flickr/FlickrOAuthService.php:19-21`) constructor-injects `FlickrAppProfileService`, but it is only read inside private `clientForProfile()` (called by `begin()` / `complete()`).

`<What/why/how to fix>`: Resolve `app(FlickrAppProfileService::class)` inside `clientForProfile()`. Note: `DashboardService::snapshot(FlickrRateLimitPresenter $rateLimit)` already demonstrates the correct method-level injection pattern.

`<Risk>`: Cosmetic/consistency only.

---

### Issue #8
**Photo transfer feature split across two single-method controllers**

`<Break rule>`: Controllers grouped by business/feature area, not split per action.

`<Explain>`: `PhotoDownloadController` (only `store()`) and `PhotoUploadController` (only `store()`) belong to the same Transfer feature but live in separate classes. Per project example (`MovieController` handles all movie actions), these read more naturally as `PhotoTransferController::download()` / `::upload()`.

`<What/why/how to fix>`: Merge into `PhotoTransferController`. Keep `PhotoDownloadService` and `PhotoUploadService` separate — download/upload are distinct business flows.

`<Risk>`: Organizational only; no behavior risk.

---

### Issue #9
**`routes/api.php` layering inconsistencies — web-namespace controllers, duplicate download/upload routes**

`<Break rule>`: Consistency / layering — API routes should resolve to `App\Http\Controllers\Api\*` where JSON is returned.

`<Explain>`:
- `routes/api.php` lines 19-20 register `PhotoDownloadController` and `PhotoUploadController` from the root `Controllers` namespace, duplicating `routes/web.php` lines 48-49.
- Frontend uses **web** paths for these actions: `flickrAccountPath(..., '/download')` in `CrawlActionBar.tsx:62` and `Contacts/Index.tsx:173` — **not** `/api/flickr/accounts/...`. The `api.php` download/upload routes are therefore dead duplicates returning `RedirectResponse` to a route file meant for JSON consumers.
- Same file also routes `FlickrAccountController` and `FlickrContactController` (web namespace) for JSON endpoints like `/contacts/progress` and `/crawl/summary`, while `CatalogController`, `DashboardController`, etc. correctly use `Api\*` counterparts.

`<What/why/how to fix>`:
1. Remove duplicate download/upload routes from `api.php` (keep `web.php` only), **or** migrate frontend to API routes with JSON responses if that is the desired direction.
2. Gradually introduce `Api\FlickrAccountController`, `Api\FlickrContactController` for JSON-only actions, leaving Inertia render actions on web controllers — matching the existing Catalog/Dashboard split.

`<Risk>`: Low today (duplicates are harmless dead weight). Medium if a future API consumer hits `/api/.../download` expecting JSON and receives a 302 redirect.

---

### Issue #10
**Duplicate pagination/filtering boilerplate across Crawler query repositories**

`<Break rule>`: DRY.

`<Explain>`: `CatalogQueryRepository` (`app/Repositories/Crawler/CatalogQueryRepository.php:33-90`) has four near-identical paginate methods repeating ownership-filter + `->paginate(...)` shape. `ApiLogQueryRepository`, `CrawlRunQueryRepository`, and `ContactQueryRepository` repeat similar patterns.

`<What/why/how to fix>`: Extract shared helpers (`paginateQuery()`, `filterByOwner()`) into a Crawler query-repository base (ties into Issue #4).

`<Risk>`: None — internal cleanup only.

---

### Issue #11
**Oversized React credential panels mix data, OAuth flow, and modal UI**

`<Break rule>`: React separation-of-concerns / component SRP.

`<Explain>`: `StorageCredentialsPanel.tsx` (543 lines) and `FlickrCredentialsPanel.tsx` (367 lines) each combine two `useForm` instances, OAuth connect/disconnect flow, and two full modal dialogs inline.

`<What/why/how to fix>`: Split into presentational list, `*OAuthForm`, and shared dialog (Issue #12).

`<Risk>`: None if done as pure extraction.

---

### Issue #12
**Duplicated modal/dialog markup between Flickr and Storage credential panels**

`<Break rule>`: DRY.

`<Explain>`: Both credential panels implement structurally identical dialog wrappers (label + secret fields + submit/cancel + error display).

`<What/why/how to fix>`: Extract shared `<CredentialFormDialog />` used by both panels.

`<Risk>`: None — pure extraction.

---

### Issue #13
**Local TS interfaces re-declared instead of imported from `types.ts`**

`<Break rule>`: DRY / single source of truth for shared types.

`<Explain>`: `FlickrAppSummary` declared in `Settings/Index.tsx:18` and `FlickrCredentialsPanel.tsx:11`. `StorageAppSummary` declared in `Settings/Index.tsx:47` and `StorageCredentialsPanel.tsx:11`. Neither imported from `resources/js/types.ts`, which already centralizes other shared shapes.

`<What/why/how to fix>`: Move both interfaces to `types.ts` and import everywhere.

`<Risk>`: Silent type drift if one copy is updated without the other.

---

### Issue #14
**`useStorageBrowse` hook mixes four unrelated concerns**

`<Break rule>`: React hook SRP / KISS.

`<Explain>`: `useStorageBrowse.ts` (375 lines) combines account loading, browse pagination, sync state, and delete state in one hook.

`<What/why/how to fix>`: Split into `useStorageAccounts()`, `useStorageSync()`, `useStorageDelete()`, composed in the page.

`<Risk>`: None to behavior; maintainability only.

---

### Issue #15
**`Crawl/Operations.tsx` renders three near-identical tables inline**

`<Break rule>`: DRY / component SRP.

`<Explain>`: `Operations.tsx` (339 lines) renders fetch-runs, download-batches, and upload-batches as three separate `DataTable` instances with similar column/render logic written three times.

`<What/why/how to fix>`: Extract `FetchRunsTable`, `DownloadBatchesTable`, `UploadBatchesTable` — or one parameterized `BatchTable<T>` matching `useCatalogOwnerNsidTable` elsewhere.

`<Risk>`: None — cosmetic maintainability.

---

### Issue #16
**`PhotoDownloadController` / `PhotoUploadController` contain dispatch orchestration logic**

`<Break rule>`: Controllers must be thin — delegate immediately to Services; no business branching.

`<Explain>`:
- `PhotoDownloadController::store()` (`app/Http/Controllers/PhotoDownloadController.php:18-63`) branches across single-photo, multi-contact (`foreach`), single-contact, and account-wide download modes with inline counting and flash-message decisions.
- `PhotoUploadController::store()` (`app/Http/Controllers/PhotoUploadController.php:18-71`) mirrors the same pattern for uploads.

The Services already expose `queuePhotoDownload`, `queueDownloads`, `queuePhotoUpload`, `queueUploads` — the controller owns dispatch-mode selection and iteration that belongs in the Service (or a `PhotoTransferOrchestratorService`).

`<What/why/how to fix>`: Add `PhotoDownloadService::queueFromRequest(QueuePhotoDownloadRequest, Connection): QueueResult` (and upload equivalent) encapsulating all branching/iteration. Controller becomes: validate → call one Service method → map result to redirect/flash.

`<Risk>`: Medium maintainability — every new queue mode requires controller edits instead of Service edits. Related to Issue #8 (controller merge).

---

### Issue #17
**`FlickrContactController::show()` orchestrates four Services in the controller**

`<Break rule>`: Thin controller; Single Responsibility — page assembly belongs in one Service.

`<Explain>`: `show()` (`app/Http/Controllers/FlickrContactController.php:93-117`) method-injects `ContactCatalogCountsService`, `ContactCrawlStateService`, `ContactCatalogDetailStatsService`, and `ContactListQueryService`, then coordinates linkage check, contact lookup, default counts fallback, and Inertia payload assembly. Compare with `index()` which correctly delegates to `ContactListPresenter` + `ContactListQueryService`.

`<What/why/how to fix>`: Introduce `ContactDetailService::forShow(Connection, string $contactNsid): ContactShowPayload` (or extend `ContactListPresenter`) returning the assembled array. Controller: FormRequest/route params → one Service call → `Inertia::render()`.

`<Risk>`: Low functional risk; increases coupling between controller and four Services as contact detail grows.

---

### Issue #18
**`SettingsController` injects `StorageAccountRepository` directly, bypassing Service layer**

`<Break rule>`: Mandated flow — Controllers must not reach Repositories; use `StorageAccountService` (or a `SettingsPageService`).

`<Explain>`: `SettingsController::index()` (`app/Http/Controllers/SettingsController.php:27-28, 47-49`) injects `StorageAccountRepository` and calls `listOrderedForSettings()` directly, while Flickr accounts correctly go through `FlickrOAuthService` and storage apps through `StorageAppProfileService`. Also builds `storage_drivers` presentation array inline in the controller (lines 52-58).

`<What/why/how to fix>`: Route storage account listing through `StorageAccountService::listForSettings()` (or a dedicated `SettingsPageService` assembling the full Inertia payload). Move `storage_drivers` mapping to a presenter or enum helper.

`<Risk>`: Low today; sets a precedent for more controller→repository shortcuts.

---

### Issue #19
**Presenter classes split inconsistently between `app/Services/` and `app/Support/`**

`<Break rule>`: Consistency / layering — presentation formatting is not business logic.

`<Explain>`:
- In `app/Support/`: `ConnectionPresenter`, `ContactPresenter`, `PhotoCatalogPresenter`, `StorageAccountPresenter`, etc.
- In `app/Services/`: `ContactListPresenter`, `FlickrRateLimitPresenter`.

Both are stateless formatting helpers. Location is arbitrary with no documented rule.

`<What/why/how to fix>`: Standardize on `app/Support/{Domain}/` for all Presenters. Move `ContactListPresenter` and `FlickrRateLimitPresenter` to `app/Support/Flickr/`. Update imports.

`<Risk>`: None — organizational; improves discoverability for new contributors.

---

### Issue #20
**`StorageRemoteAlbum` / `StorageRemoteItem` expose `toBrowseArray()` presentation on Models**

`<Break rule>`: Fat model / layering — Models should be data shapes, not API presenters.

`<Explain>`: `StorageRemoteItem::toBrowseArray()` (`app/Models/StorageRemoteItem.php:42-54`) and the equivalent on `StorageRemoteAlbum` format API/browse response shapes inside Eloquent models. Elsewhere the project uses dedicated Presenter classes under `app/Support/`.

`<What/why/how to fix>`: Move to `StorageBrowseItemPresenter::toArray(StorageRemoteItem $item)` (and album equivalent). Models retain relationships/casts only.

`<Risk>`: Low — minor inconsistency; becomes relevant if browse response shapes diverge per provider.

---

## Notes on Methodology

- All findings verified by reading cited source files directly — not inferred solely from `AGENTS.md`, skills, or other AI-generated docs.
- **Rejected finding (recorded):** Constructor DI in `PhotoDownloadController` / `PhotoUploadController` is **correct** — each has one action using the injected Service throughout; per project rule, constructor DI is appropriate here.
- **Deprecated jobs:** `DownloadContactsPhotosJob` and `UploadContactsPhotosJob` inject Repositories in addition to Services, but are marked `@deprecated` and kept for in-flight queue jobs only — not flagged as active violations.
- **Domain Events:** No `app/Events/` classes exist; Services dispatch Jobs (`PhotoDownloadService`, `PhotoUploadService`) but do not fire Laravel domain Events. This is acceptable for current async needs but differs from the stated "events in Service layer" guideline — consider Events only when multiple listeners/subscribers are needed (YAGNI).
- **Service dependency graph:** 48 Service classes, 40 Service→Service edges, zero cycles. `PhotoUploadExecutionService → StorageUploadService` is the only cross-domain constructor edge (one-way, expected).
- Issue #9 duplicate API routes confirmed against frontend: download/upload use `flickrAccountPath` (web), not `flickrApiAccountPath`.

---

## Required Follow-up: AI Check / Report-Back After Implementation

For **every** issue marked `⬜ Open` that gets implemented, the implementing agent/developer **MUST**, before marking `✅ Done`:

| Step | Action | Status / Check |
|---|---|---|
| 1 | Update this table: `⬜ Open` → `🔄 In Progress` → `✅ Done` (or `❌ Won't Fix` with reason) | ⬜ Template ready |
| 2 | Run quality gates: `composer test:docker`; `npm run typecheck` if FE touched; `composer instructions:verify`. Record pass/fail in PR description. | ⬜ Pending implementation |
| 3 | For Issues #1, #2, #4, #5, #6, #16, #17, #18 (BE structural): re-verify no new circular Service↔Service dependency was introduced. | ⬜ Pending implementation |
| 4 | For Issues #11-#15 (FE): confirm in-browser that affected pages render and OAuth/browse/sync/delete flows work end-to-end. | ⬜ Pending implementation |
| 5 | Report back which issue numbers were addressed and any deviation from the proposed fix, so this audit can be reconciled. | ⬜ Pending implementation |

**PR scope rule:** Do not batch unrelated issues into one PR — each issue above is scoped to be independently shippable.

**Post-implementation audit reconciliation:** After all targeted issues are resolved, re-run this audit checklist against the updated codebase and append a `## Re-Audit Summary` section with updated Status / Check columns.