# XFlickr Codex Architecture Audit

## Purpose Of Audit

Audit the current XFlickr codebase against the requested backend and frontend architecture expectations:

- SOLID, DRY, KISS, and YAGNI/YANGI principles.
- Laravel standards and the mandated backend flow: Request -> Controller -> FormRequest validation -> Service -> optional service orchestration -> Repository -> Model.
- Jobs must delegate business execution to Services.
- React/Inertia standards, shared UI patterns, typed props, and maintainable component structure.
- Design-pattern usage where it reduces duplicated branching, centralizes logic, and keeps extension safe.
- Service dependency direction must remain one-way. If two services are both needed in a workflow, use a third orchestrator service.
- Avoid broad constructor/method dependency injection when a dependency is only needed by one isolated method; resolve method-local dependencies locally or move the method to the service that owns the workflow.

## Expectation

The project should keep controllers thin, put request validation in FormRequest classes, keep business decisions in focused services, keep persistence access behind repositories, and keep React screens composed from shared components and focused hooks. Provider-specific behavior should be easy to extend without editing central switch/match blocks in many places.

## Audit Status

| Check | Status | Notes |
|---|---:|---|
| Required project docs read | ✅ Pass | Read `AGENTS.md`, `docs/README.md`, `ai/README.md`, and `docs/05-maintenance/docker-safety.md`. |
| Backend layering inspected | ✅ Pass | Controllers, FormRequests, Services, Repositories, Jobs, routes inspected. |
| Frontend structure inspected | ✅ Pass | Pages, components, hooks, shared UI utilities inspected. |
| Dev database safety | ✅ Pass | No local dev Docker DB command was run. |
| Inline controller validation scan | ✅ Pass | No `->validate()` usage found in controllers/services/jobs/repositories. |
| Invokable controller scan | ✅ Pass | No `__invoke` controller actions found. |
| Service dependency cycle spot check | ✅ Pass with caution | No direct two-way service dependency found, but provider dispatch services are tightly coupled. |
| TypeScript check | ✅ Pass | `npm run typecheck` passed. |
| AI instruction sync | ✅ Pass | `composer instructions:verify` passed. |
| Full test suite | ⚠️ Not run | Audit-only task; no behavior code changed. Use `composer test:docker` after fixes. |

## Audit Results

### Issue List

- [Issue #1: OAuth connect actions bypass FormRequest validation and keep request parsing in controllers](#issue-1-oauth-connect-actions-bypass-formrequest-validation-and-keep-request-parsing-in-controllers)
- [Issue #2: Storage API download contains storage business logic inside the controller](#issue-2-storage-api-download-contains-storage-business-logic-inside-the-controller)
- [Issue #3: Storage provider orchestration is implemented with repeated central match/branch logic](#issue-3-storage-provider-orchestration-is-implemented-with-repeated-central-matchbranch-logic)
- [Issue #4: Storage sync performs remote API calls inside a database transaction](#issue-4-storage-sync-performs-remote-api-calls-inside-a-database-transaction)
- [Issue #5: Deprecated bulk jobs still contain repository lookup and branch decisions](#issue-5-deprecated-bulk-jobs-still-contain-repository-lookup-and-branch-decisions)
- [Issue #6: Repository layer does not use abstractions/interfaces, weakening DIP](#issue-6-repository-layer-does-not-use-abstractionsinterfaces-weakening-dip)
- [Issue #7: Some controllers use method injection heavily instead of clear class-level dependencies or service ownership](#issue-7-some-controllers-use-method-injection-heavily-instead-of-clear-class-level-dependencies-or-service-ownership)
- [Issue #8: Settings and storage React components are too large and duplicate modal/form/action UI](#issue-8-settings-and-storage-react-components-are-too-large-and-duplicate-modalformaction-ui)
- [Issue #9: Raw buttons bypass shared Button/ActionButton patterns in user-facing pages](#issue-9-raw-buttons-bypass-shared-buttonactionbutton-patterns-in-user-facing-pages)
- [Issue #10: Storage browse hook owns too many async workflows and suppresses hook dependency validation](#issue-10-storage-browse-hook-owns-too-many-async-workflows-and-suppresses-hook-dependency-validation)

---

## Issue Details

### Issue #1: OAuth connect actions bypass FormRequest validation and keep request parsing in controllers

**Status:** ❌ Fail  
**Severity:** P1  
**Files:** `app/Http/Controllers/FlickrAuthController.php:18`, `app/Http/Controllers/StorageAuthController.php:20`, `app/Http/Controllers/StorageAuthController.php:44`

**Breaks:** SRP, Laravel FormRequest standard, requested Request -> Controller -> FormRequest -> Service flow.

`FlickrAuthController::connect()` accepts raw `Illuminate\Http\Request` and reads `app_profile` directly. `StorageAuthController::connect()` and `reauthorize()` also accept raw `Request`, parse route/query data, cast `account_id`, and sanitize `return_url` in the controller.

**Why this is an issue:** Controllers are doing input normalization and validation work that should be in FormRequests. This creates inconsistent request handling because callback/disconnect actions use FormRequests while connect/reauthorize actions do not.

**How to fix:**

- Add dedicated FormRequests such as `ConnectFlickrOAuthRequest`, `ConnectStorageOAuthRequest`, and `ReauthorizeStorageRequest`.
- Move `app_profile`, `provider`, `account_id`, and `return_url` validation/normalization into those requests.
- Keep controller actions as service calls plus redirect response mapping.

**Risk if not fixed:** New query parameters or provider rules may be added without validation, and OAuth behavior will remain harder to test consistently.

### Issue #2: Storage API download contains storage business logic inside the controller

**Status:** ❌ Fail  
**Severity:** P1  
**Files:** `app/Http/Controllers/Api/StorageBrowseController.php:154`, `app/Http/Controllers/Api/StorageBrowseController.php:178`, `app/Http/Controllers/Api/StorageBrowseController.php:181`, `app/Services/Storage/StorageDownloadService.php:13`

**Breaks:** SRP, thin controller rule, Service ownership, KISS.

`StorageBrowseController::download()` parses provider support, builds R2 config, resolves object keys, creates a disk through `app(StorageFlysystemFactory::class)`, checks file existence, reads streams, derives filename/mime type, and streams the response.

**Why this is an issue:** A `StorageDownloadService` already exists, but this controller bypasses it. The controller now owns storage-driver details and stream handling, which makes it harder to add downloads for Google Drive or OneDrive without growing controller complexity.

**How to fix:**

- Move the R2 download logic into `StorageDownloadService`.
- Return a small DTO/value object from the service containing stream, filename, mime type, and status metadata.
- Keep the controller responsible only for FormRequest, service call, and HTTP response translation.

**Risk if not fixed:** Download behavior will diverge by endpoint, provider additions will require controller edits, and stream/resource error handling will remain difficult to unit test.

### Issue #3: Storage provider orchestration is implemented with repeated central match/branch logic

**Status:** ❌ Fail  
**Severity:** P1  
**Files:** `app/Services/Storage/StorageBrowseService.php:14`, `app/Services/Storage/StorageBrowseService.php:50`, `app/Services/Storage/StorageDeleteService.php:13`, `app/Services/Storage/StorageDeleteService.php:39`, `app/Services/Storage/StorageUploadService.php:32`, `app/Services/Storage/StorageUploadService.php:90`, `app/Services/Storage/StorageUploadService.php:129`

**Breaks:** Open/Closed Principle, DRY, Strategy pattern opportunity.

Storage browse/delete/upload logic depends on central classes that inject every provider-specific service and use `match` or `if` branches for provider behavior.

**Why this is an issue:** Adding a new storage provider requires editing several central services. That is not open for extension; it is open for repeated modification. The pattern also spreads provider-specific rules across multiple files.

**How to fix:**

- Introduce provider strategy contracts, for example `StorageBrowseDriver`, `StorageDeleteDriver`, and `StorageUploadDriver`.
- Register strategies by `StorageDriver` value in the container.
- Let orchestration services resolve the correct strategy from a registry/factory.
- Keep cross-provider shared cache reconciliation in orchestration services, but move provider-specific API behavior into strategies.

**Risk if not fixed:** Provider features will grow by adding more branches in central services, increasing regression risk across existing Google Photos, Google Drive, OneDrive, and R2 behavior.

### Issue #4: Storage sync performs remote API calls inside a database transaction

**Status:** ❌ Fail  
**Severity:** P1  
**Files:** `app/Services/Storage/StorageBrowseSyncService.php:45`, `app/Services/Storage/StorageBrowseSyncService.php:59`, `app/Services/Storage/StorageBrowseService.php:50`

**Breaks:** KISS, transaction boundary best practice, reliability.

`StorageBrowseSyncService::sync()` wraps the whole sync loop in `DB::transaction()` and then calls `$this->browse->browse()`, which performs provider API calls.

**Why this is an issue:** Network calls inside database transactions keep locks open while waiting on external services. If an API call stalls or rate-limits, local database rows can stay locked longer than necessary.

**How to fix:**

- Fetch provider data outside the transaction.
- Use short transactions only around local state updates for each batch.
- Consider a Sync Batch DTO to separate remote payload collection from local persistence.

**Risk if not fixed:** Sync can cause lock contention, partial retries become harder to reason about, and slow providers can degrade local database responsiveness.

### Issue #5: Deprecated bulk jobs still contain repository lookup and branch decisions

**Status:** ⚠️ Partial fail  
**Severity:** P2  
**Files:** `app/Jobs/DownloadContactsPhotosJob.php:30`, `app/Jobs/DownloadContactsPhotosJob.php:34`, `app/Jobs/UploadContactsPhotosJob.php:32`, `app/Jobs/UploadContactsPhotosJob.php:39`

**Breaks:** Job -> Service-only rule, SRP.

The deprecated jobs do call services, but they also resolve repositories, load connections/accounts, choose defaults, and branch on missing storage accounts.

**Why this is an issue:** The project rule says jobs should call one service method and contain no business rules beyond delegation/error reporting. These jobs are marked deprecated for in-flight jobs, so this is lower severity, but still inconsistent.

**How to fix:**

- Add compatibility service methods that accept scalar queued payloads.
- Let jobs call one service method, for example `PhotoUploadService::queueContactUploadsFromJobPayload(...)`.
- Keep repository lookup and fallback behavior inside services.

**Risk if not fixed:** Future queue compatibility changes may copy this pattern and reintroduce job-level business logic.

### Issue #6: Repository layer does not use abstractions/interfaces, weakening DIP

**Status:** ⚠️ Partial fail  
**Severity:** P2  
**Files:** `app/Providers/RepositoryServiceProvider.php:30`, `app/Services/DashboardService.php:20`, `app/Services/Flickr/PhotoDownloadService.php:18`, `app/Services/Storage/StorageBrowseLocalService.php:17`

**Breaks:** Dependency Inversion Principle.

Repositories are registered as concrete singletons, and services type-hint concrete repository classes.

**Why this is an issue:** This works in Laravel and is simple, but the project standards say services should depend on repository abstractions. Concrete repositories make alternate implementations, fake repositories, and contract tests harder.

**How to fix:**

- Add repository contracts only for repositories used by business-critical services or where fakes would reduce test friction.
- Bind contracts to concrete repositories in `RepositoryServiceProvider`.
- Avoid creating interfaces for every small repository at once; migrate by risk area to avoid over-engineering.

**Risk if not fixed:** Tests stay coupled to concrete persistence behavior, and service changes remain harder to isolate.

### Issue #7: Some controllers use method injection heavily instead of clear class-level dependencies or service ownership

**Status:** ⚠️ Partial fail  
**Severity:** P2  
**Files:** `app/Http/Controllers/StorageAuthController.php:20`, `app/Http/Controllers/StorageAuthController.php:82`, `app/Http/Controllers/StorageAuthController.php:108`, `app/Http/Controllers/FlickrAuthController.php:18`, `app/Http/Controllers/FlickrAuthController.php:57`, `app/Http/Controllers/DashboardController.php:14`

**Breaks:** Consistency, requested dependency-use rule.

Several controllers use method injection for services across multiple actions. This is valid Laravel, but it conflicts with the requested rule: if a dependency is used across the class, use class-level dependency; if used only for one method, do not globally declare it.

**Why this is an issue:** Current style mixes constructor DI, method DI, and `app()` resolution. For example, `CatalogController` uses constructor DI while `FlickrAuthController` and `StorageAuthController` repeatedly method-inject related services.

**How to fix:**

- Choose one controller dependency convention and document it.
- For dependencies used in most actions of a controller, prefer constructor promotion.
- For one-off dependencies, either resolve method-locally or move that action into a service that owns the workflow.

**Risk if not fixed:** New controllers will follow inconsistent patterns, making code harder to scan and refactor.

### Issue #8: Settings and storage React components are too large and duplicate modal/form/action UI

**Status:** ❌ Fail  
**Severity:** P2  
**Files:** `resources/js/Components/Settings/StorageCredentialsPanel.tsx:36`, `resources/js/Components/Settings/StorageCredentialsPanel.tsx:308`, `resources/js/Components/Settings/FlickrCredentialsPanel.tsx:25`, `resources/js/Components/Settings/FlickrCredentialsPanel.tsx:206`, `resources/js/Components/Settings/GeneralConfigPanel.tsx:45`, `resources/js/Pages/Storage/Browse.tsx:26`

**Breaks:** SRP, DRY, React component composition standards.

Large components mix list rendering, dialogs, forms, validation error rendering, provider actions, and navigation behavior. `StorageCredentialsPanel` is 543 lines, `Storage/Browse` is 400 lines, `FlickrCredentialsPanel` is 367 lines, and `GeneralConfigPanel` is 310 lines.

**Why this is an issue:** The components are type-safe, but they are harder to change safely because many responsibilities are in one render function. Modal shells, form rows, button rows, error blocks, and provider account cards are duplicated.

**How to fix:**

- Extract shared modal shell, form field, error banner, and dialog action-row components.
- Split provider cards and credential dialogs into focused components.
- Keep page components as composition roots, not workflow containers.

**Risk if not fixed:** UI changes will keep duplicating class names and behavior, increasing inconsistent styling and regression risk.

### Issue #9: Raw buttons bypass shared Button/ActionButton patterns in user-facing pages

**Status:** ❌ Fail  
**Severity:** P2  
**Files:** `resources/js/Pages/Storage/Browse.tsx:153`, `resources/js/Pages/Storage/Browse.tsx:206`, `resources/js/Pages/Storage/Browse.tsx:296`, `resources/js/Pages/Storage/Browse.tsx:386`, `resources/js/Components/Settings/GeneralConfigPanel.tsx:145`, `resources/js/Components/Settings/GeneralConfigPanel.tsx:208`, `resources/js/Components/Settings/StorageCredentialsPanel.tsx:117`, `resources/js/Components/Settings/StorageCredentialsPanel.tsx:382`

**Breaks:** React shared component standards, DRY.

The project has `Button`, `ActionButton`, and `buttonVariants`, but many user-facing actions still use raw `<button>` markup with repeated Tailwind classes.

**Why this is an issue:** Shared buttons centralize states, sizes, variants, and accessibility defaults. Raw buttons make the UI harder to keep consistent.

**How to fix:**

- Replace user-facing action buttons with `Button` or `ActionButton`.
- Keep raw `<button>` only inside low-level primitives such as `Button`, `DataTable` sortable headers, or custom accessible composite controls.
- Add variants if current shared components do not cover small icon/text action cases.

**Risk if not fixed:** Visual consistency and disabled/loading behavior will drift across pages.

### Issue #10: Storage browse hook owns too many async workflows and suppresses hook dependency validation

**Status:** ⚠️ Partial fail  
**Severity:** P2  
**Files:** `resources/js/hooks/useStorageBrowse.ts:26`, `resources/js/hooks/useStorageBrowse.ts:67`, `resources/js/hooks/useStorageBrowse.ts:150`, `resources/js/hooks/useStorageBrowse.ts:226`, `resources/js/hooks/useStorageBrowse.ts:325`

**Breaks:** SRP, KISS, React hooks standards.

`useStorageBrowse()` owns account loading, local browsing, provider sync recursion, deletion confirmation, reauthorization state mutation, container navigation, and error handling. It also suppresses `react-hooks/exhaustive-deps`.

**Why this is an issue:** A suppressed hook dependency warning may be intentional, but it is a signal that the hook has too many changing concerns. The recursive sync path and in-flight refs are difficult to reason about when combined with browse/delete/container state.

**How to fix:**

- Split into focused hooks: `useStorageAccounts`, `useStorageContent`, `useStorageSync`, and `useStorageDelete`.
- Move confirmation text and provider-specific UI wording out of the state hook.
- Remove the eslint suppression by stabilizing callbacks or narrowing effect responsibilities.

**Risk if not fixed:** Race conditions around account/container changes and background sync will be harder to catch, especially as provider behavior expands.

---

## Consistency Findings

### Backend Passed Areas

- Most HTTP inputs use FormRequests under `app/Http/Requests`.
- No inline controller validation was found.
- No invokable controllers were found.
- Controllers are generally grouped by business feature rather than micro-actions.
- Repositories centralize most Eloquent and query-builder access.
- No direct two-way service dependency cycle was found in the audited service graph.

### Backend Needs Consistency

- OAuth connect actions should match the rest of the FormRequest flow.
- Storage provider behavior should move toward Strategy/Factory/Registry patterns instead of repeated central `match` blocks.
- Controller dependency style should be standardized.
- Deprecated jobs should be made fully service-only or removed after queued compatibility is no longer needed.

### Frontend Passed Areas

- `npm run typecheck` passes.
- Pages use typed props extending `PageProps`.
- Major pages use `PageHeading`, `Breadcrumbs`, and `DataTable` consistently.
- Shared UI primitives exist for buttons, cards, provider cards, data tables, pagination, status badges, and crawl actions.

### Frontend Needs Consistency

- Large Settings and Storage components should be split.
- Raw action buttons should move to shared `Button`/`ActionButton` patterns.
- Storage browse async behavior should be decomposed into smaller hooks.

---

## Design Pattern Recommendations

| Area | Recommended Pattern | Why |
|---|---|---|
| Storage provider operations | Strategy + Registry/Factory | Add providers without editing multiple central services. |
| Storage API download | Service + DTO | Keep controller thin and make stream behavior testable. |
| OAuth connect/reauthorize | FormRequest + Service | Centralize validation and keep controllers consistent. |
| Storage sync persistence | Unit of Work / short transaction boundary | Avoid remote API calls inside DB transactions. |
| Settings dialogs | Compound components or small presentational components | Reduce duplicated modal/form/button code. |
| Storage browse hook | Facade hook over smaller hooks | Keep page ergonomics while preserving SRP internally. |

---

## Required AI Check After Implementing Fixes

After implementing any issues from this audit, the implementing AI must report back with:

- ✅ Which audit issue numbers were changed.
- ✅ Files changed for each issue.
- ✅ Whether the Request -> Controller -> FormRequest -> Service -> Repository -> Model flow was preserved.
- ✅ Whether any new service-to-service dependency was added, and confirmation that no two-way dependency was introduced.
- ✅ Whether provider extension now uses a strategy/factory/registry or why a simpler approach is justified.
- ✅ Whether jobs still delegate to one service method only.
- ✅ Whether frontend changes use shared `Button`, `ActionButton`, `DataTable`, `PageHeading`, and existing typed props patterns.
- ✅ Test/check output.

Minimum required checks after implementation:

```bash
npm run typecheck
composer instructions:verify
composer test:docker
```

If frontend code changed, also run:

```bash
npm run lint
```

If backend architecture changed, also run relevant backend lint/static checks where practical:

```bash
composer lint:pint
composer lint:phpstan
composer lint:deptrac
```

Never run tests through the local dev Docker stack. Use only the isolated test stack commands documented in `AGENTS.md`.

---

## Final Audit Summary

The project is already much closer to the requested architecture than a typical mixed Laravel/Inertia app: FormRequests are common, repositories exist, controllers are mostly feature-grouped, and React pages use shared components. The largest gaps are not missing layers; they are boundary leaks and extension pressure:

- Storage download logic is in an API controller despite an existing download service.
- Storage provider behavior needs a Strategy/Factory direction before more providers or capabilities are added.
- Storage sync holds DB transactions across remote provider calls.
- Settings/storage React UI needs decomposition and shared action controls.

Priority order should be: Issue #4, Issue #2, Issue #3, Issue #1, then frontend decomposition issues.
