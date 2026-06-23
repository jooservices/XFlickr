# XFlickr Codebase Audit

> **Status (2026-06-23):** Partially resolved. FormRequest layer (`app/Http/Requests/`) and Repository layer (`app/Repositories/`) have been implemented since this audit. Use [risks-legacy-and-gaps.md](risks-legacy-and-gaps.md) for current gaps.

## Purpose of Audit

Evaluate the current state of the XFlickr Laravel + Inertia/React application against:

- SOLID, DRY, KISS, YAGNI principles
- PHP design patterns (https://refactoring.guru/design-patterns/php)
- Laravel framework standards and conventions
- React/Node frontend standards
- The project's mandated backend request lifecycle: `Request -> Controller -> FormRequest -> Service -> (Service) -> Repository -> Model`
- The project's mandated rule that **Jobs must always delegate business logic to a Service**
- The project's mandated rule that **two Services must never depend on each other bidirectionally** (a shared workflow needs an orchestrator Service)
- The project's mandated rule on **Dependency Injection discipline**: constructor DI only when a dependency is used across most/all of a class's methods; otherwise resolve via `app($class)` inside the specific method

## Expectation

- Controllers are thin, grouped by business/feature area, and contain no query building, filtering, sorting, or pagination logic.
- All input validation lives in `FormRequest` classes, not inline in controllers.
- All persistence access goes through a Repository layer; Services never touch the Query Builder/Eloquent directly.
- Jobs are dumb wrappers that delegate to a Service; they hold no business rules.
- Services have a single, clear responsibility, do not create circular dependencies, and rely on constructor DI only when truly class-wide.
- Frontend code is composed of small, single-purpose components/hooks, with a centralized API access layer, and is consistent in styling/structure across analogous pages.
- Duplicate code blocks (backend or frontend) are centralized via the appropriate design pattern instead of being repeated.

---

## Audit Results

### Issues found

| # | Issue | Severity | Status |
|---|-------|----------|--------|
| [1](#issue-1-no-formrequest-layer--validation-lives-inside-controllers) | No `FormRequest` layer — validation lives inside controllers | High | **Resolved** |
| [2](#issue-2-no-repository-layer-services-and-controllers-query-eloquent-directly) | No Repository layer — Services and Controllers query Eloquent directly | High | **Resolved** |
| [3](#issue-3-business-and-query-logic-embedded-directly-in-controllers) | Business and query logic embedded directly in controllers | High | |
| [4](#issue-4-jobs-contain-business-logic-instead-of-delegating-to-a-service) | Jobs contain business logic instead of delegating to a Service | High | |
| [5](#issue-5-inconsistent-flow-for-equivalent-operations--duplicated-logic-between-job-and-service) | Inconsistent flow for equivalent operations + duplicated logic between Job and Service | Medium | |
| [6](#issue-6-eloquent-model-reaches-into-the-service-layer) | Eloquent Model reaches into the Service layer | Medium | |
| [7](#issue-7-no-static-analysis-tooling-for-be-type--architecture-safety) | No static analysis tooling for BE type/architecture safety | Low | **Resolved** |
| [8](#issue-8-no-centralized-api-client-on-the-frontend) | No centralized API client on the frontend | High | **Resolved** |
| [9](#issue-9-duplicated-filter-state-logic-across-catalog-pages) | Duplicated filter-state logic across Catalog pages | Medium | **Resolved** |
| [10](#issue-10-god-page-components-violating-srp) | God page components violating SRP | Medium | **Resolved** |
| [11](#issue-11-no-eslint-configuration--inconsistent-frontend-code-discipline) | No ESLint configuration — inconsistent frontend code discipline | Low | **Resolved** |

---

### Issue #1: No `FormRequest` layer — validation lives inside controllers

**Breaks rule:** Single Responsibility Principle (S in SOLID), and the project's mandated flow `Request -> Controller -> FormRequest -> Service`.

**Explain:** There is no `app/Http/Requests` directory anywhere in the codebase. Validation is either done with `$request->validate([...])` written inline inside the controller action (`FlickrAppProfileController::store`, `RuntimeConfigController::store`, `StorageAppProfileController::store`, `StorageAuthController::connectR2`), or done with hand-rolled manual checks that re-implement what validation should do (`FlickrAuthController::callback` checking `$oauthToken === ''`, `FlickrAccountController::crawl` manually casting/looping `$request->input('types', [...])` into enum values, `PhotoDownloadController::store` and `PhotoUploadController::store` manually inspecting `$request->input()` for several mutually exclusive shapes). The controller is now responsible for both "what HTTP input shape is acceptable" and "what to do once it's accepted" — two responsibilities the framework explicitly separates via `FormRequest`.

**What/why/how to fix:** Introduce one `FormRequest` per controller action that has meaningful input (e.g. `StoreFlickrAppProfileRequest`, `StoreRuntimeConfigRequest`, `StoreStorageAppProfileRequest`, `ConnectR2Request`, `CrawlFlickrAccountRequest`, `QueuePhotoDownloadRequest`, `QueuePhotoUploadRequest`). Move the existing validation rules verbatim into `rules()`, add an `authorize()` method (currently no controller checks authorization at all), and use `prepareForValidation()`/`passedValidation()` hooks to normalize array-vs-string inputs (e.g. the `types` array coercion in `FlickrAccountController::crawl`) instead of doing it ad hoc in the controller body.

**Risk:** Without this, every new endpoint keeps re-inventing validation inline, drifting in subtle ways (some endpoints trim input, some don't; some return `ValidationException`, others a flash `error` redirect for the same class of problem). It also makes controllers untestable for "is this input shape rejected" in isolation from the side effects of the action.

---

### Issue #2: No Repository layer — Services and Controllers query Eloquent directly

**Breaks rule:** Dependency Inversion Principle (D in SOLID), and the project's mandated flow `Service -> Repository -> Model`.

**Explain:** There is no `Repository` of any kind in the project (`find . -iname "*Repositor*"` returns nothing under `app/`). Every Service (`PhotoDownloadService`, `PhotoUploadService`, `ContactCrawlStateService`, `StorageAccountService`, etc.) and even several Controllers build `Model::query()->where(...)` chains directly. The persistence mechanism (Eloquent, specific column names, specific joins) is therefore a hard dependency of the business logic layer, rather than something the business logic depends on through an abstraction.

**What/why/how to fix:** Introduce repository interfaces (e.g. `PhotoRepositoryInterface`, `StoredFileRepositoryInterface`, `TransferBatchRepositoryInterface`) bound in a `RepositoryServiceProvider`, with Eloquent-backed implementations. Move the raw `Model::query()->where(...)` chains currently living in Services (`PhotoDownloadService::queueDownloads/queuePhotoDownload`, `PhotoUploadService::queueUploads/queuePhotoUpload`, the `DB::table(...)` joins in `PhotoDownloadService::groupPendingPhotos`) and in Controllers (see Issue #3) behind repository methods such as `PhotoRepository::pendingForOwner()`, `PhotoRepository::byFlickrId()`, `StoredFileRepository::completedFor()`. Services then depend on the repository interface, not on Eloquent.

**Risk:** Without a repository boundary, every behavior change to "how a photo's pending status is determined" must be hunted down across multiple Services/Jobs that each query the same tables slightly differently (this already happened — see Issue #5, where the same filter logic is duplicated with slightly different code). It also blocks introducing caching, read replicas, or swapping the underlying crawler package's models without touching every Service.

---

### Issue #3: Business and query logic embedded directly in controllers

**Breaks rule:** Single Responsibility Principle (S in SOLID); Laravel's "thin controller" standard; DRY.

**Explain:** Several controllers build, filter, sort, and paginate Eloquent queries directly inside the action method instead of delegating to a Service:

- `App\Http\Controllers\FlickrContactController::index/progress/suggest` build `Contact::query()->whereIn(...)` subqueries against `ConnectionContact`, apply `like` search, and paginate — all inline.
- `App\Http\Controllers\Api\CatalogController::photos/photosets/galleries/favorites` each repeat an almost identical 10-line block: read `page`/`per_page`/`sort`/`direction` from the request, build a query, apply `QuerySorter`, paginate, build a `meta` array.
- `App\Http\Controllers\Api\CrawlStatusController::summary/runs/logs` and `App\Http\Controllers\Api\TransferProgressController::index` repeat the same pattern again, including resolving `QuerySorter` via the `app()` helper directly inside `runs()` instead of through DI (inconsistent with how it's injected in the constructor elsewhere in the same class).

This is both an SRP violation (the controller now owns query semantics, which is business/domain logic) and a DRY violation: the page/per_page/sort/direction/meta-building boilerplate appears nearly verbatim at least 6 times across two files.

**What/why/how to fix:** Create one Service per business area to own this logic, consistent with the project's own example (`MovieService` doing `list`/`detail`). E.g. a `CatalogQueryService` (or split per area if genuinely distinct: `PhotoCatalogService`, `PhotosetCatalogService`, `GalleryCatalogService`, `FavoriteCatalogService`) that accepts a small query DTO (page, perPage, sort, direction, filters) and returns a paginator + resolved meta. The repeated meta-building (`current_page`, `last_page`, `per_page`, `total`, `sort`, `direction`) is a good candidate for a small reusable `PaginationMetaFactory` value object so it's written once. `FlickrContactController`'s inline contact search/sort/pagination should move into the already-existing `ContactListSorter`/`ContactListPresenter` services (which already exist for presentation — extend them, don't bypass them).

**Risk:** Any change to pagination defaults, sort-direction validation, or search behavior must be replicated across every action that copy-pasted the pattern, and it already shows signs of drift (e.g. `Api\CatalogController` defaults `sort` to `'id'`/`direction` to `'desc'`, while `FlickrContactController` defaults `sort` to `'username'`/`direction` to `'asc'`, for what is conceptually the same kind of listing operation).

---

### Issue #4: Jobs contain business logic instead of delegating to a Service

**Breaks rule:** Explicit project rule — "Jobs: Always call service to execute business logic." Also breaks SRP and testability (queued code paths are hard to unit test directly).

**Explain:** `App\Jobs\DownloadPhotoJob::handle()` and `App\Jobs\UploadPhotoJob::handle()` contain the entire business workflow inline: acquiring a cache lock, looking up `Connection`/`Photo`/`StoredFile`/`StorageUpload` models, calling `Http::sink(...)->get(...)` to stream a download to disk, computing `sha256`, moving the `.part` file into place, updating status columns, and reconciling the parent `TransferBatch`. None of this is delegated to a Service object — the Job *is* the business logic. `FlickrPhotoSizeResolver` and `StorageUploadService` are used only as narrow helpers (resolve a download URL, perform the actual upload stream), while the orchestration — locking, state transitions, error handling, retries — lives in the Job itself.

**What/why/how to fix:** Extract a `PhotoDownloadExecutionService` (and symmetrically `PhotoUploadExecutionService`) that owns the actual download/upload workflow — lock acquisition, status transitions, file move, batch item update — and have `DownloadPhotoJob::handle()`/`UploadPhotoJob::handle()` shrink down to: resolve dependencies, call the service, done. This also makes the workflow independently testable without dispatching a real queued job, and makes it reusable if the same download/upload needs to be triggered synchronously elsewhere later (currently it cannot be, since it's locked inside `handle()`).

**Risk:** Today, the only way to exercise this critical, money/quota-impacting logic (Flickr API rate limits, Cloudflare R2 storage costs) is through the queue worker, which makes it harder to unit test edge cases (partial download cleanup, lock contention, retry/backoff behavior) and increases the chance that a future contributor adds another business rule directly into the Job rather than a Service, compounding the violation.

---

### Issue #5: Inconsistent flow for equivalent operations + duplicated logic between Job and Service

**Breaks rule:** DRY; consistency standard explicitly requested in the audit scope ("consider if any not-consistent code applied").

**Explain:** `PhotoDownloadController::store` and `PhotoUploadController::store` handle the conceptually identical "queue work for a single contact" case differently:

- Download path: `PhotoDownloadController` calls `$this->photoDownloadService->queueDownloads($connection, $contactNsid)` — i.e. it goes through the Service.
- Upload path: `PhotoUploadController` instead calls `UploadContactsPhotosJob::dispatch($connection->connection_key, $contactNsid, $storageAccount->id)` directly, bypassing `PhotoUploadService` entirely.

`UploadContactsPhotosJob::handle()` then re-implements, almost line-for-line, the same "filter photos that don't yet have a completed upload for this storage account, create a `TransferBatch` + `TransferItem` rows, dispatch `UploadPhotoJob` per photo" logic that already exists in `PhotoUploadService::queuePendingPhotos`. The two implementations are independent copies of the same rule and can silently diverge (e.g. if the "what counts as already uploaded" check changes in one place, the other is now wrong).

**What/why/how to fix:** Make `PhotoUploadController` call `PhotoUploadService` for the contact case too (`PhotoUploadService::queueUploads($connection, $storageAccount, $contactNsid)` already supports an optional `$ownerNsid` parameter — it is simply not being used here). Delete `UploadContactsPhotosJob`'s duplicated body and either delete the Job entirely or have it become a thin wrapper that calls the Service, exactly like `DownloadContactsPhotosJob` already correctly does (note: `DownloadContactsPhotosJob` is marked `@deprecated ... Kept for in-flight queue jobs` and correctly delegates to `PhotoDownloadService` — this is the right pattern; `UploadContactsPhotosJob` should follow it instead of duplicating logic).

**Risk:** A bug fix or business-rule change to "when is a photo eligible for upload" applied to `PhotoUploadService` will not automatically apply to the contact-upload code path, producing silently incorrect behavior (e.g. double-uploads or skipped uploads) that is hard to notice because both paths look superficially correct in isolation.

---

### Issue #6: Eloquent Model reaches into the Service layer

**Breaks rule:** Dependency Inversion Principle (D in SOLID) — dependency direction should flow Controller → Service → Repository → Model, never Model → Service.

**Explain:** `App\Models\StorageAccount::toPublicArray()` calls `app(StorageAccountScopeService::class)->authorizationMeta($this)` from inside the model. The persistence layer (the Model) now has a runtime dependency on the service container and on a Service that itself encodes business rules about OAuth scope sufficiency. It also means `toPublicArray()` cannot be unit tested without booting the full Laravel container, and any test that creates a `StorageAccount` and calls `toPublicArray()` implicitly exercises `StorageAccountScopeService` whether the test intends to or not.

**What/why/how to fix:** Move `toPublicArray()`'s responsibility out of the Model into a presenter/Service, consistent with how the rest of the codebase already does this correctly elsewhere (`ConnectionPresenter::toArray()`, `ContactPresenter::toDetailArray()`, `App\Support\Catalog\PhotoCatalogPresenter`). Add a `StorageAccountPresenter::toPublicArray(StorageAccount $account): array` that calls `StorageAccountScopeService` from the presenter, not the model, and update the one call site (`SettingsController::index`) to use it.

**Risk:** Low immediate functional risk, but it's a precedent — once one Model is allowed to resolve Services from the container, it becomes easy to justify doing it elsewhere, eroding the boundary the rest of the codebase otherwise respects (every other presenter in `app/Support` is correctly a plain class taking the model as an argument, not the other way around).

---

### Issue #7: No static analysis tooling for BE type/architecture safety

**Status: Resolved (2026-06-23).** Added PHPStan/Larastan (level 1 + baseline), Deptrac (`Model` layer isolation), `composer lint` scripts, and CI enforcement via `.github/workflows/ci.yml`.

**Breaks rule:** Not a SOLID/DRY violation per se, but undermines the audit's "extendable easily, strictly follow SOLID" expectation by having no automated guardrail.

**Explain:** `composer.json` includes Laravel Pint (style formatting) but no PHPStan/Larastan. There is no `phpstan.neon` anywhere in the repo. Style is enforced; type-correctness, dead code, and architectural boundaries (e.g. "Models must not depend on Services", which Issue #6 already violates) are not enforced by any tool — they rely entirely on manual review.

**What/why/how to fix:** Add `phpstan/phpstan` + `larastan/larastan` at a reasonable starting level, and optionally a custom architecture rule (e.g. via `deptrac`) that fails CI if `app/Models/**` imports anything from `app/Services/**`, which would have caught Issue #6 automatically.

**Risk:** Low/structural — this is a process gap, not a current bug, but it explains why issues like #6 exist undetected.

---

### Issue #8: No centralized API client on the frontend

**Status: Resolved (2026-06-23).** Added `resources/js/lib/apiClient.ts` (`apiGet`, `apiPost`, `ApiError`) and `resources/js/lib/storageApi.ts`; migrated all frontend fetch call sites.

**Breaks rule:** Single Responsibility Principle / DRY — equivalent on the frontend to Issue #2 on the backend (no abstraction over the data-access mechanism).

**Explain:** Raw `fetch()` calls are scattered across at least 8 different files with no shared wrapper: `hooks/useRemoteDataTable.ts`, `Components/ContactSwitcher.tsx`, `Components/ContactSearchInput.tsx`, `Components/Settings/FlickrCredentialsPanel.tsx`, `Pages/Dashboard.tsx`, `Pages/Flickr/Index.tsx`, `Pages/Contacts/Index.tsx`, `Pages/Crawl/Operations.tsx`, `Pages/Storage/Browse.tsx`. Each one independently builds `URLSearchParams`, calls `fetch`, and does its own (inconsistent) error handling — some `try/finally` without checking `response.ok`, some inline. There is no shared concept of "this request failed, here is how the UI is told," no shared base path, and no shared response-shape assertion.

**What/why/how to fix:** Introduce a thin `lib/apiClient.ts` (e.g. `apiGet<T>(path, params)`/`apiPost<T>(path, body)`) that centralizes: base URL handling, JSON parsing, non-2xx response handling (throwing a typed `ApiError`), and optionally request cancellation via `AbortController`. Migrate the call sites above to use it. This mirrors the Repository pattern recommended for the backend (Issue #2) — one seam for "how data is fetched," many consumers.

**Risk:** Currently, a global concern like "redirect to login on 401" or "show a toast on network failure" cannot be added in one place — it requires touching 8 files, and it is easy for a 9th new page to repeat the same ad hoc pattern (which is exactly what has been happening).

---

### Issue #9: Duplicated filter-state logic across Catalog pages

**Status: Resolved (2026-06-23).** `useOwnerNsidFilter`, `CatalogOwnerNsidFilter`, and `useCatalogOwnerNsidTable` centralize filter state + table wiring across all four catalog pages.

**Breaks rule:** DRY, KISS.

**Explain:** `Pages/Catalog/Photos.tsx`, `Photosets.tsx`, `Galleries.tsx`, and `Favorites.tsx` each contain an identical ~15-line block: `useState` for `ownerNsid`/`appliedOwnerNsid`, a `useEffect` calling `readOwnerNsidFromUrl()` on mount, a `useMemo` deriving the `filters` object passed to `useRemoteDataTable`, and an identical filter `<form>` with the same `onSubmit` handler shape. Only the query-param name (`owner_nsid` vs `subject_nsid` for `Favorites.tsx`) and the table columns differ.

**What/why/how to fix:** Extract a `useOwnerNsidFilter(paramName?: string)` hook that returns `{ ownerNsid, setOwnerNsid, filters, applyFilters }`, and/or extract the filter `<form>` itself into a small `OwnerNsidFilterForm` component. This is a good case for the same idea as the "Template Method" pattern translated to hooks: the four pages already share a near-identical skeleton (`useRemoteDataTable` + filter form + `DataTable`) — the only genuinely page-specific part is the column definitions.

**Risk:** Any fix to the filter behavior (e.g. debouncing, trimming, syncing back to the URL) must be applied four times; it already shows early signs of drift since `Favorites.tsx` uses a different query param than the other three.

---

### Issue #10: God page components violating SRP

**Status: Resolved (2026-06-23).** `Storage/Browse.tsx` logic moved to `useStorageBrowse` + `lib/format.ts` (~400 lines → ~350 JSX-only). `Crawl/Operations.tsx` split into `StatusBadge`, `PageSection`, `lib/crawlOperations.ts`, and `useCrawlOperations` (~505 → ~340 lines).

**Breaks rule:** Single Responsibility Principle; React standard of small, composable, single-purpose components.

**Explain:** `Pages/Storage/Browse.tsx` (658 lines) and `Pages/Crawl/Operations.tsx` (505 lines) each mix multiple unrelated concerns in one file:

- `Storage/Browse.tsx` combines: data fetching, a polling/sync state machine (`syncInFlight`, `syncDepthRef`, recursive sync calls), client-side sorting via two separate `useTableSort` instances, byte/date formatting helpers (`formatBytes`, `formatSyncedAt`), and the page's own JSX, all in a single component.
- `Crawl/Operations.tsx` defines small presentational subcomponents inline (`StatusBadge`, `Section`) and several pure helper functions (`accountLabel`, `downloadGroupLabel`, `downloadStoragePath`, `fetchRunSortValue`) in the same file as the page component, instead of placing them in `resources/js/Components` (where every other reusable presentational piece in this codebase already lives, e.g. `StatCard`, `ProgressBar`, `Breadcrumbs`).

**What/why/how to fix:** Move `StatusBadge` and `Section` into `resources/js/Components` (they are generic enough to be reused — `StatusBadge` in particular looks reusable wherever a `CrawlRun`/`TransferBatch` status is shown elsewhere in the app). Extract the sync/poll state machine in `Storage/Browse.tsx` into a dedicated hook (e.g. `useStorageSync(...)`), and the formatting helpers into `lib/format.ts` alongside the existing `lib/*.ts` utility modules.

**Risk:** Files of this size are difficult to safely modify (a change to the sync polling logic risks an unrelated regression in sorting, since both live in the same closure and share component state). It also makes these specific pieces of logic (sync state machine, status badge styling) impossible to reuse or unit test independently of the full page.

---

### Issue #11: No ESLint configuration — inconsistent frontend code discipline

**Status: Resolved (2026-06-23).** Added `eslint.config.js` (flat config), `npm run lint` / `lint:fix` / `check`, and CI frontend-lint job.

**Breaks rule:** Not a principle violation directly, but it is the root cause enabling Issues #9/#10 to go unnoticed, and a React-standard project is expected to have lint enforcement.

**Explain:** There is no `.eslintrc*` or `eslint.config.*` anywhere in the repository, and `package.json`'s only quality script is `"typecheck": "tsc --noEmit"`. The absence of import-ordering/unused-import rules is directly visible in `Pages/Storage/Browse.tsx`, where `import Thumbnail from '@/Components/Thumbnail';` is declared on line 75, after several function declarations, instead of being grouped with the other imports at the top of the file — something a default ESLint `import/order` (or even just Prettier with import sorting) would have caught automatically.

**What/why/how to fix:** Add `eslint` with `@typescript-eslint`, `eslint-plugin-react-hooks`, and `eslint-plugin-import` (with `import/order` and `import/first` enabled), wired into a `lint` script and ideally a pre-commit hook/CI step.

**Risk:** Low immediate functional risk, but it is the same category of gap as Issue #7 on the backend — without an automated gate, style and structural drift (like Issues #9 and #10) accumulates silently across the frontend.

---

## Note on Patterns Already Done Well

To keep this audit calibrated, a few things are already implemented in a way consistent with the requested standards and should be preserved, not "fixed":

- `app/Support/Flickr/ConnectionPresenter`, `ContactPresenter`, and `app/Support/Catalog/*Presenter` correctly isolate presentation/shaping logic away from Models and Controllers (Single Responsibility + a clean Presenter pattern) — this is the pattern Issue #6 should be brought in line with.
- The Service dependency graph in `app/Services/**` does **not** currently contain any bidirectional Service-to-Service dependency (checked across every constructor) — the one rule explicitly called out in the audit scope as a hard constraint is currently respected and should stay that way as new Services are added (watch in particular `StorageBrowseService` → `StorageBrowseSyncService`, which are already adjacent and one-directional today — keep it that way).
- `resources/js/Components/DataTable.tsx` and `hooks/useRemoteDataTable.ts` are a good generic, reusable abstraction (Strategy-style column `render` functions) and are exactly the kind of shared component the duplicated Catalog pages (Issue #9) should be leaning on more, not less.
- `Console/Commands/VerifyGooglePhotosConnectionCommand` correctly delegates to `GooglePhotosConnectionVerifier` rather than embedding logic in the command — this is the same pattern Jobs should follow per Issue #4.
