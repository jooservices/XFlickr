# XFlickr Code Audit Report
**Auditor**: Claude Sonnet 4.6 (Thinking)
**Date**: 2026-06-23
**Scope**: Full project — Backend (Laravel 12 / PHP) + Frontend (React 19 / TypeScript / Inertia 3)

---

## Purpose

Conduct a full audit of the XFlickr project codebase against:
- SOLID, DRY, KISS, YAGNI principles
- PHP design patterns (refactoring.guru)
- Laravel architectural standards (Controller → FormRequest → Service → Repository → Model)
- React/TypeScript standards and design patterns
- Consistency, extensibility, and maintainability

---

## Expectation

Every issue is identified by a unique number, explained clearly, classified by the violated rule, and accompanied by:
- What the problem is
- Why it matters
- How to fix it
- Risk level if left unresolved

---

## Audit Result Summary

| # | Issue | Severity | Status |
|---|-------|----------|--------|
| [#1](#issue-1) | `PhotoUploadExecutionService` dispatches `DownloadPhotoJob` — circular service orchestration | 🔴 Critical | ❌ Open |
| [#2](#issue-2) | `DashboardService::snapshot()` accepts `FlickrRateLimitPresenter` as parameter — leaky DI | 🟠 High | ❌ Open |
| [#3](#issue-3) | `QueuePhotoUploadRequest::resolvedStorageAccount()` calls `app()` inside FormRequest — wrong layer | 🟠 High | ❌ Open |
| [#4](#issue-4) | `Api\StorageBrowseController::download()` contains inline file-streaming / R2 business logic | 🟠 High | ❌ Open |
| [#5](#issue-5) | `ContactDownloadCountsService` calls `Storage::exists()` — Filesystem access in a query service | 🟠 High | ❌ Open |
| [#6](#issue-6) | `FlickrContactController::show()` has a duplicate data key (`contact` / `contact_detail`) | 🟡 Medium | ❌ Open |
| [#7](#issue-7) | `FlickrContactController::index()` duplicates sort/direction sanitisation already done in FormRequest | 🟡 Medium | ❌ Open |
| [#8](#issue-8) | `StorageOAuthService::provider()` mutates global `config()` at runtime — side-effect in service | 🟡 Medium | ❌ Open |
| [#9](#issue-9) | `StorageUploadService::diskFor()` ignores its `$credentials` parameter | 🟡 Medium | ❌ Open |
| [#10](#issue-10) | `PhotoDownloadController` and `PhotoUploadController` duplicate the same branching logic | 🟡 Medium | ❌ Open |
| [#11](#issue-11) | String literals for status values (`'pending'`, `'completed'`, `'failed'`, `'downloading'`) used across many files | 🟡 Medium | ❌ Open |
| [#12](#issue-12) | `DownloadPhotoJob::failed()` and `UploadPhotoJob::failed()` use raw `app()` inside a job | 🟡 Medium | ❌ Open |
| [#13](#issue-13) | `FlickrAccountController::list()` bypasses the Service layer and calls the Crawler Facade directly | 🟡 Medium | ❌ Open |
| [#14](#issue-14) | `CatalogController` is injected with `FlickrOAuthService` but the controller's single responsibility is rendering catalog pages | 🟡 Medium | ❌ Open |
| [#15](#issue-15) | `StorageDeleteService` has a conditional `if ($driver === StorageDriver::GooglePhotos)` — OCP violation | 🟡 Medium | ❌ Open |
| [#16](#issue-16) | `StorageBrowseSyncService` depends on `StorageBrowseService` (which has all drivers) — hard dependency on a fat service | 🟡 Medium | ❌ Open |
| [#17](#issue-17) | `useStorageBrowse` hook is a 376-line god-hook with multiple responsibilities | 🟡 Medium | ❌ Open |
| [#18](#issue-18) | `Browse.tsx` hardcodes `/settings?tab=storage` href string instead of using a route helper | 🟡 Medium | ❌ Open |
| [#19](#issue-19) | `Dashboard.tsx` uses a `useEffect` + `setInterval` polling pattern without exponential back-off or debounce guard | 🟡 Medium | ❌ Open |
| [#20](#issue-20) | `ContactListPresenter` is placed under `Services/Flickr/` but it is a Presenter, not a Service | 🟡 Medium | ❌ Open |
| [#21](#issue-21) | `ContactListSorter` is placed under `Services/Flickr/` but it is a Query-builder helper, not a Service | 🟡 Medium | ❌ Open |
| [#22](#issue-22) | `StorageBrowseResult` is placed under `Services/Storage/` but it is a DTO | 🟡 Medium | ❌ Open |
| [#23](#issue-23) | `PhotoDownloadExecutionService` uses a magic string `'completed'` inline instead of `StoredFile` status enum/constant | 🟡 Medium | ❌ Open |
| [#24](#issue-24) | `DownloadContactsPhotosJob` is deprecated but still in the codebase and not removed | 🟢 Low | ❌ Open |
| [#25](#issue-25) | `FlickrContactController::show()` calls `$contactList->findContact()` which throws on missing instead of returning null | 🟢 Low | ❌ Open |
| [#26](#issue-26) | `StorageOAuthService::clearOAuthSession()` forgets `storage_oauth_return_url` selectively | 🟢 Low | ❌ Open |
| [#27](#issue-27) | FE: `useStorageBrowse` `syncFromProvider` uses recursion (`await syncFromProvider()`) for pagination loops | 🟢 Low | ❌ Open |
| [#28](#issue-28) | `StorageUploadService::remoteMetadata()` has dead branches for `GoogleDrive`, `GooglePhotos`, `OneDrive` | 🟢 Low | ❌ Open |

---

## Detailed Issues

---

### Issue #1
**`PhotoUploadExecutionService` dispatches `DownloadPhotoJob` — two-way service dependency / circular orchestration**

**Breaks**: S (Single Responsibility), and the explicit rule *"Service A may depend on Service B; Service B must NOT depend back on Service A."*

**Explain**:

`PhotoUploadExecutionService` (in `Services/Flickr/`) is the execution layer for *upload* jobs. When it finds a photo's local file is not yet downloaded, it dispatches `DownloadPhotoJob` (lines 47–53):

```php
// PhotoUploadExecutionService.php – line 47
DownloadPhotoJob::dispatch(
    $flickrPhotoId,
    $ownerNsid,
    $connectionKey,
    $batchId,
);
```

`DownloadPhotoJob` calls `PhotoDownloadExecutionService`. This means:

```
PhotoUploadExecutionService → DownloadPhotoJob → PhotoDownloadExecutionService
```

But `PhotoDownloadService` (the queueing layer) also calls `DownloadPhotoJob`, and the upload job and download job are tightly coupled — the upload execution service *silently hijacks* a download batch by re-using `$batchId` from the upload batch. This makes batch reconciliation unpredictable (upload batch count vs download items).

Additionally: `PhotoUploadExecutionService` reaches into `TransferBatchRepository::connectionKeyForId()` to build an ad-hoc connection key — another responsibility leak.

**What / Why / How to fix**:

Introduce an orchestrator class — e.g. `TransferOrchestrationService` — whose sole job is: "if local file missing, queue download; otherwise queue upload." Neither `PhotoDownloadExecutionService` nor `PhotoUploadExecutionService` should know about each other.

```php
// NEW: Services/Transfer/TransferOrchestrationService.php
final class TransferOrchestrationService {
    public function ensureDownloadedThenUpload(
        string $flickrPhotoId, string $ownerNsid, string $connectionKey,
        int $storageAccountId, ?int $uploadBatchId
    ): void {
        if (! $this->storedFiles->hasCompletedOriginal($flickrPhotoId)) {
            DownloadPhotoJob::dispatch($flickrPhotoId, $ownerNsid, $connectionKey, null);
            UploadPhotoJob::dispatch($flickrPhotoId, $storageAccountId, $uploadBatchId, $ownerNsid)
                ->delay(now()->addSeconds(60));
            return;
        }
        // proceed with upload
    }
}
```

The upload execution service should only upload; it must not dispatch download jobs.

**Risk**: 🔴 High — Currently, uploading an un-downloaded photo re-uses the upload `$batchId` for a download job, corrupting batch counters. Hard to trace in production.

---

### Issue #2
**`DashboardService::snapshot()` accepts `FlickrRateLimitPresenter` as method parameter instead of constructor injection**

**Breaks**: D (Dependency Inversion), KISS, consistency

**Explain**:

```php
// DashboardService.php – line 32
public function snapshot(FlickrRateLimitPresenter $rateLimit): array
```

`FlickrRateLimitPresenter` is passed through the controller method, forwarded into the service method. The controller becomes the "carrier" of a dependency the service needs. Everywhere else in the project, dependencies are injected via constructor. This pattern is inconsistent and prevents mocking the presenter in isolation.

Contrast with how the controller calls it:
```php
// DashboardController.php
public function index(DashboardService $dashboard, FlickrRateLimitPresenter $rateLimit): Response
{
    return Inertia::render('Dashboard', [
        'snapshot' => $dashboard->snapshot($rateLimit),
    ]);
}
```

**How to fix**: Inject `FlickrRateLimitPresenter` via constructor in `DashboardService`. The controller should only call `$dashboard->snapshot()`.

**Risk**: 🟠 Medium — inconsistency makes it easy to accidentally pass the wrong type; prevents clean unit testing.

---

### Issue #3
**`QueuePhotoUploadRequest::resolvedStorageAccount()` calls `app(StorageAccountRepository::class)` inside a FormRequest**

**Breaks**: S (Single Responsibility), L (Liskov — FormRequests should only validate), KISS

**Explain**:

```php
// QueuePhotoUploadRequest.php – line 39
$accounts = app(StorageAccountRepository::class);
```

FormRequests are responsible for *validation only*. Resolving a model from a repository inside a FormRequest couples it to business logic. The service layer should be the one resolving the storage account.

Per the project's own user rule: *"If it's really used for whole class: YES [use DI], but if only use for specific method → use `app($class)` inside method."* — This usage is acceptable per the project rule for method-level DI, however the **wrong layer** is the bigger issue. A FormRequest should not be fetching models.

**How to fix**: Remove `resolvedStorageAccount()` from the FormRequest. Move storage account resolution to `PhotoUploadService`, which already has `$storageAccountId` from the request. The controller should pass the raw ID, and the service resolves/validates the account.

**Risk**: 🟠 Medium — FormRequests bypass authorization/policy for DB access; side effects if the repository query fails during validation.

---

### Issue #4
**`Api\StorageBrowseController::download()` contains inline R2 file-streaming / business logic (SRP violation)**

**Breaks**: S (Single Responsibility), D (Dependency Inversion), "Controller → Service → Repository" rule

**Explain**:

The `download()` method (lines 154–208) in `Api\StorageBrowseController`:
- Instantiates `StorageR2Config` directly
- Calls `app(StorageFlysystemFactory::class)` directly inside the controller
- Does file existence checks, reads a stream, builds headers, and streams a response

All of this is business logic. The controller does all of it without delegating to a service:

```php
$config = StorageR2Config::from($credentials);
$objectKey = $config->objectKey($remotePath);
$disk = app(StorageFlysystemFactory::class)->diskForAccount($account); // ← direct app() in controller
// ... file checks, streaming ...
```

**How to fix**: Create `StorageDownloadService::streamFile(StorageAccount $account, string $remotePath): StreamedResponse` (or return a stream DTO). The controller calls it and returns the response.

**Risk**: 🟠 High — untestable controller logic; future drivers (e.g., GoogleDrive downloads) will duplicate the pattern.

---

### Issue #5
**`ContactDownloadCountsService` calls `Storage::exists()` — filesystem I/O in a counting/query service**

**Breaks**: S (Single Responsibility), YAGNI (violates the idea that a query service only queries data), performance

**Explain**:

```php
// ContactDownloadCountsService.php – lines 68-70
if ($file->local_path === null || ! Storage::exists($file->local_path)) {
    $failed++;
}
```

This service is invoked for *every contact* on the contacts list page. For a page with 25 contacts, each potentially having hundreds of stored files, this triggers N filesystem `stat()` calls per request. It's a performance landmine disguised as a query.

Worse, the `Storage::exists()` call is an infrastructure concern (filesystem layer), not a counting concern. The `StoredFile` model already has a `status` column. A file that is `completed` but missing from disk represents a *data integrity issue* that should be detected by a separate reconciliation job — not detected inline on every page load.

**How to fix**:
1. Remove the `Storage::exists()` call from `ContactDownloadCountsService`.
2. Rely solely on the `status` column. Consider adding a `StoredFile::status = 'missing'` state tracked by a background integrity-check command.

**Risk**: 🟠 High — N filesystem I/O calls on list page load; scales O(contacts × files_per_contact) with no caching.

---

### Issue #6
**`FlickrContactController::show()` sends duplicate data keys `contact` and `contact_detail`**

**Breaks**: DRY, YAGNI

**Explain**:

```php
// FlickrContactController.php – lines 111-116
return Inertia::render('Contacts/Show', [
    'account' => ConnectionPresenter::toArray($connection),
    'contact' => ContactPresenter::toDetailArray($contact),       // ← same data
    'contact_detail' => ContactPresenter::toDetailArray($contact), // ← same data, same method
    'catalog_stats' => ...,
    'crawl_state' => ...,
]);
```

Both `contact` and `contact_detail` call `ContactPresenter::toDetailArray($contact)` with the same argument. This doubles the serialized payload sent to the frontend for no benefit.

**How to fix**: Remove one key. Decide on a single canonical name (`contact` is the simpler one) and update the frontend `Show.tsx` page to use only that key.

**Risk**: 🟡 Low-Medium — doubled prop payload; if one key is updated and the other is not, a silent regression occurs.

---

### Issue #7
**`FlickrContactController::index()` re-sanitises sort/direction values that `ListFlickrContactsRequest` already validated**

**Breaks**: DRY, SRP

**Explain**:

```php
// FlickrContactController.php – lines 55-58
'filters' => [
    'search' => $request->search(),
    'sort' => in_array($request->rawSort(), ContactListSorter::SORTABLE_COLUMNS, true)
                  ? $request->rawSort()
                  : 'username',           // ← sanitisation in controller
    'direction' => strtolower($request->rawDirection()) === 'desc' ? 'desc' : 'asc', // ← sanitisation in controller
],
```

The FormRequest already has `sort()` and `direction()` methods that return safe values. The controller re-implements the same guard logic instead of trusting the FormRequest's output.

**How to fix**: Use `$request->sort()` and `$request->direction()` directly (the FormRequest already guarantees the safe values).

**Risk**: 🟡 Low — code duplication; if `ContactListSorter::SORTABLE_COLUMNS` changes, only one of the two places might be updated.

---

### Issue #8
**`StorageOAuthService::provider()` mutates the global `config()` at runtime — side-effect in a service**

**Breaks**: S (Single Responsibility), no side-effects principle, testability

**Explain**:

```php
// StorageOAuthService.php – lines 112-116
config([
    "services.{$providerKey}.client_id" => $appConfig['client_id'] ?? '',
    "services.{$providerKey}.client_secret" => $appConfig['client_secret'] ?? '',
    "services.{$providerKey}.redirect" => $redirect,
]);
```

Writing to the global `config()` store from within a service method is a hidden side-effect. Any other code running in the same request that reads `services.google.client_id` will see the mutated value. In a long-running Octane or Swoole environment this becomes a concurrency data-corruption bug.

**How to fix**: Use `Socialite::buildProvider()` or the `with()` fluent API to configure per-request credentials without touching the global config:

```php
return (new \Laravel\Socialite\Two\GoogleProvider(
    request(), $clientId, $clientSecret, $redirect
))->scopes($driver->defaultScopes())->with([...]);
```

**Risk**: 🟠 Medium — safe in PHP-FPM (new process per request), but a ticking timebomb if the app ever moves to Octane.

---

### Issue #9
**`StorageUploadService::diskFor()` has an unused `$credentials` parameter**

**Breaks**: YAGNI, KISS, Interface Segregation

**Explain**:

```php
// StorageUploadService.php – lines 78-81
private function diskFor(StorageAccount $account, array $credentials): Filesystem
{
    return $this->flysystem->diskForAccount($account); // $credentials is never used
}
```

The method signature accepts `array $credentials` but it is never used. The method is a thin wrapper that passes only `$account` to the factory. This is dead parameter — it misleads readers into thinking credentials are used independently of the account.

**How to fix**: Remove `$credentials` from `diskFor()`. The `StorageFlysystemFactory::diskForAccount()` already receives the full account and can extract credentials itself.

**Risk**: 🟡 Low — dead code is confusing but not dangerous.

---

### Issue #10
**`PhotoDownloadController::store()` and `PhotoUploadController::store()` duplicate the identical branching pattern**

**Breaks**: DRY, S (Single Responsibility)

**Explain**:

Both controllers implement the same three-branch dispatch logic:
1. If `singlePhotoId()` → call `queuePhotoDownload()`/`queuePhotoUpload()`
2. If `contactNsids()` is not empty → loop and call `queueDownloads()`/`queueUploads()`
3. If `singleContactNsid()` → call `queueDownloads()`/`queueUploads()`
4. Else → call with no filter (account-wide)

This pattern is identical in both controllers. The branching logic is business logic that belongs in the service, not the controller.

**How to fix**: Add a unified dispatch method to each service:

```php
// PhotoDownloadService::queueFromRequest(Connection, ?string $photoId, array $nsids, ?string $singleNsid): int
```

The controller becomes a thin pass-through without branching.

**Risk**: 🟡 Medium — any new queueing mode (e.g., queue by album) must be added to both controllers separately.

---

### Issue #11
**Status string literals (`'pending'`, `'completed'`, `'failed'`, `'downloading'`, `'processing'`) scattered across multiple files**

**Breaks**: DRY, OCP, maintainability

**Explain**:

The `StoredFile`, `TransferItem`, and `StorageUpload` models all use status strings that are repeated as raw literals in repositories, services, and even the presentation layer:

- `StoredFileRepository`: `'completed'`, `'downloading'`, `'pending'`, `'failed'`
- `PhotoDownloadExecutionService`: `'completed'`, `'processing'`, `'pending'`, `'failed'`
- `PhotoUploadExecutionService`: `'completed'`, `'processing'`, `'failed'`
- `ContactDownloadCountsService`: `'failed'`, `'completed'`
- `TransferBatchReconciler`: `'completed'`, `'failed'`, `'running'`

There is already an `Modules\Transfer\Enums\PhotoTransferExecutionOutcome` enum in the codebase — but it is only used for job return values, not for the actual status strings written to the database.

**How to fix**: Create PHP 8.1 backed string enums:

```php
enum StoredFileStatus: string {
    case Pending = 'pending';
    case Downloading = 'downloading';
    case Completed = 'completed';
    case Failed = 'failed';
}

enum TransferItemStatus: string {
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
```

Use them in repositories, services, and models via `->value` when writing to DB.

**Risk**: 🟡 Medium — typo in a status string causes silent failures; with enums, the compiler catches it.

---

### Issue #12
**`DownloadPhotoJob::failed()` and `UploadPhotoJob::failed()` use raw `app()` inside job failure handlers**

**Breaks**: Consistency (project rules say `app()` is acceptable per-method — this is borderline acceptable per rule, but inconsistent with how `handle()` works)

**Explain**:

```php
// DownloadPhotoJob.php – lines 53-58
public function failed(Throwable $exception): void
{
    app(PhotoDownloadExecutionService::class)->handleFailure(...);
}
```

The `handle()` method uses proper Laravel method injection: `public function handle(PhotoDownloadExecutionService $service)`. But `failed()` uses `app()`. Laravel actually supports method injection on `failed()` just like `handle()` — the container resolves typed parameters automatically.

**How to fix**:

```php
public function failed(Throwable $exception, PhotoDownloadExecutionService $service): void
{
    $service->handleFailure($this->flickrPhotoId, $this->batchId, $exception->getMessage());
}
```

**Risk**: 🟢 Low — functionally equivalent; consistency and testability are the main concerns.

---

### Issue #13
**`FlickrAccountController::list()` calls `FlickrService::connections()->list()` directly without going through the Service layer**

**Breaks**: Controller → Service → Repository rule, DIP

**Explain**:

```php
// FlickrAccountController.php – lines 24-31
public function list(): Response
{
    $accounts = FlickrService::connections()  // ← direct Facade call in controller
        ->list()
        ->map(fn (Connection $connection): array => ConnectionPresenter::toArray($connection));

    return Inertia::render('Flickr/Index', ['accounts' => $accounts]);
}
```

The `FlickrOAuthService` already has a `listAccounts()` method that wraps the same Facade call. The controller should use the service.

**How to fix**:

```php
public function list(FlickrOAuthService $oauth): Response
{
    return Inertia::render('Flickr/Index', [
        'accounts' => $oauth->listAccounts()->values(),
    ]);
}
```

**Risk**: 🟡 Medium — if `listAccounts()` adds caching, permission checks, or filtering, the controller bypasses them.

---

### Issue #14
**`CatalogController` is injected with `FlickrOAuthService` via constructor — but the dependency is only needed to resolve the active connection**

**Breaks**: YAGNI, KISS (partially — per project rules: "If only used in one method → use `app()` inside method")

**Explain**:

```php
// CatalogController.php
public function __construct(
    private readonly FlickrOAuthService $oauth, // used only in presentAccount()
) {}
```

The `FlickrOAuthService` is injected globally but only needed for a single private helper method. Per the project's own rule: *"If it's really used for whole class: YES; but if only use for specific method → use `app($class)` inside method."*

All four public methods (`photos`, `photosets`, `galleries`, `favorites`) call `presentAccount()`, which calls `$this->oauth`. In this case the dependency IS used across all methods so constructor DI is actually appropriate — but the `CatalogController` violates SRP in a different way: it renders four different catalog pages. Each of these `photos()`, `photosets()`, `galleries()`, `favorites()` could arguably be separate controller methods, but the current grouping is reasonable per the "group by business logic" rule.

The real smell: the `FlickrOAuthService` is a heavy OAuth service used here only to call `activeConnection()`. A lighter `FlickrConnectionResolver` or passing the `Connection` via route model binding (which is already done in `FlickrContactController`) would be cleaner.

**How to fix**: Use Laravel route model binding for the optional `?Connection` with a fallback to the active connection inside the controller method using `app()` per-method — or pass `Connection` as an optional route parameter already resolved.

**Risk**: 🟡 Low — architectural smell; works correctly today.

---

### Issue #15
**`StorageDeleteService::deleteMany()` has a `if ($driver === GooglePhotos)` branch — Open/Closed Principle violation**

**Breaks**: O (Open/Closed Principle)

**Explain**:

```php
// StorageDeleteService.php – lines 46-53
if ($result['deleted'] !== []) {
    if ($driver === StorageDriver::GooglePhotos) {
        $this->browseLocal->deleteCachedItems($account, $result['deleted'], $containerId);
    } else {
        $this->browseLocal->deleteCachedItems($account, $result['deleted']);
        $this->browseLocal->purgeUploadRecords($account, $result['deleted']);
    }
}
```

Adding a new driver (e.g., iCloud) would require modifying this service — violating OCP. The driver-specific post-delete behavior should be encapsulated in the driver implementation.

**How to fix**: Extract a per-driver interface method:

```php
interface StorageDriverDeleter {
    public function deleteMany(array $credentials, array $itemIds, ?string $containerId): array;
    public function afterDelete(StorageAccount $account, array $deletedIds, ?string $containerId, StorageBrowseLocalService $local): void;
}
```

Each driver's delete class implements `afterDelete()`. `StorageDeleteService` calls `$deleter->afterDelete(...)` without branching.

**Risk**: 🟡 Medium — every new driver forces a `StorageDeleteService` modification.

---

### Issue #16
**`StorageBrowseSyncService` depends on `StorageBrowseService` (which aggregates all 4 drivers) — fat dependency**

**Breaks**: ISP (Interface Segregation Principle), DIP

**Explain**:

```php
// StorageBrowseSyncService.php
public function __construct(
    private readonly StorageBrowseService $browse, // aggregates all 4 drivers
    ...
) {}
```

`StorageBrowseService` is injected into `StorageBrowseSyncService` to call `$this->browse->browse(...)`. But `StorageBrowseService` knows about all 4 drivers (Google Photos, Google Drive, OneDrive, R2) and their repositories. `StorageBrowseSyncService` only needs the ability to browse — not all the account resolution and provider routing that `StorageBrowseService` does.

**How to fix**: Extract a `StorageBrowsePort` interface with only the `browse()` method. `StorageBrowseService` implements it. `StorageBrowseSyncService` depends on the interface only.

**Risk**: 🟢 Low — works correctly; the issue is test isolation and future driver isolation.

---

### Issue #17
**`useStorageBrowse` is a 376-line god hook with multiple responsibilities**

**Breaks**: S (Single Responsibility), React custom hook design principles

**Explain**:

The `useStorageBrowse` hook manages:
1. Account loading (`loadAccounts`)
2. Browse data loading (`loadBrowse`) with pagination/append modes
3. Background sync orchestration (`syncFromProvider`) with recursive retry
4. Delete operations (`deleteSelectedItems`)
5. Container (album) navigation (`openContainer`, `clearContainer`)
6. All related state: `accounts`, `accountId`, `containerId`, `containerTitle`, `albums`, `items`, `meta`, `loading`, `syncing`, `syncMode`, `error`, `deleting`, `syncInFlight` ref, `syncDepthRef`

This is equivalent to a "God Class" in the frontend. It mixes data fetching, navigation, mutation, and orchestration.

**How to fix**: Split into focused hooks:
- `useStorageAccounts(provider)` — account list, selection
- `useStorageBrowseData(accountId, providerSlug, containerId)` — albums/items loading
- `useStorageSync(accountId, providerSlug, containerId, onSyncComplete)` — sync from provider
- `useStorageDelete(accountId, providerSlug, containerId)` — delete operations

`useStorageBrowse` becomes a thin composition hook that wires these together.

**Risk**: 🟡 Medium — the hook is currently too large to test in isolation; any bug requires understanding the entire 376 lines.

---

### Issue #18
**`Browse.tsx` uses hardcoded `/settings?tab=storage` href string**

**Breaks**: DRY, maintainability

**Explain**:

```tsx
// Browse.tsx – line 197
<a href="/settings?tab=storage" ...>
    Connect in Settings
</a>
```

Route strings are hardcoded in JSX. If the settings URL changes, this will silently break. The project uses Inertia's `Link` component in other places, but falls back to a raw `<a>` here with a hardcoded path.

**How to fix**: Create a route constants module (`lib/routes.ts`) with typed route helpers:

```ts
export const routes = {
    settings: (tab?: string) => tab ? `/settings?tab=${tab}` : '/settings',
};
```

And replace the hardcoded string:

```tsx
<Link href={routes.settings('storage')}>Connect in Settings</Link>
```

**Risk**: 🟢 Low — minor maintenance issue.

---

### Issue #19
**`Dashboard.tsx` uses a naive `setInterval(poll, 5000)` without debounce or visibility guard**

**Breaks**: React performance best practices, resource efficiency

**Explain**:

```tsx
// Dashboard.tsx – lines 38-54
const poll = () => { ... };
poll();
const interval = setInterval(poll, 5000);
```

The dashboard polls every 5 seconds unconditionally, including when:
- The tab is not visible (user switched to another browser tab)
- The window is minimized
- A previous request is still in flight (no guard against concurrent requests)

While there is an `AbortController` for cleanup, there is no `document.visibilitychange` listener to pause polling when the page is hidden. For a self-hosted tool this is acceptable, but it's not React best-practice.

**How to fix**:

```tsx
useEffect(() => {
    const poll = () => {
        if (document.visibilityState === 'hidden') return;
        // ... fetch
    };
    const interval = setInterval(poll, 5000);
    return () => clearInterval(interval);
}, []);
```

**Risk**: 🟢 Low — waste of resources when tab is hidden.

---

### Issue #20
**`ContactListPresenter` is placed under `Services/Flickr/` but it is a Presenter, not a Service**

**Breaks**: Naming convention, Single Responsibility, discoverability

**Explain**:

`ContactListPresenter` orchestrates catalog counts, crawl states, and download counts to build the contact list view payload. It is a Presenter in the MVP sense. Placing it under `Services/` makes it harder to find and blurs the boundary between "business logic service" and "data presentation layer."

Similarly, `ContactListSorter` is a query-builder helper, not a service.

**How to fix**: Create a `Presenters/` namespace (or `Support/Presenters/`) and move:
- `ContactListPresenter` → `App\Presenters\Flickr\ContactListPresenter`
- `ContactListSorter` → `App\Support\Flickr\ContactListSorter` (already partially done — `ConnectionPresenter` and `ContactPresenter` live in `Support/Flickr/`)

Make the naming consistent: all presenters live in `Support/` or a new `Presenters/` folder.

**Risk**: 🟢 Low — discoverability and convention issue; no functional impact.

---

### Issue #21
**`ContactListSorter` is placed under `Services/Flickr/` but it is a Query-builder helper**

*(See Issue #20 — same namespace problem, listed separately for tracking)*

**Breaks**: Naming convention, discoverability

`ContactListSorter` contains sort logic for Eloquent builders. It is not a business service. It should live alongside query repositories or in a `Support/` namespace.

**Risk**: 🟢 Low — same as #20.

---

### Issue #22
**`StorageBrowseResult` is placed under `Services/Storage/` but it is a DTO**

**Breaks**: Naming convention, Single Responsibility

**Explain**:

```php
// Services/Storage/StorageBrowseResult.php
final class StorageBrowseResult { ... }
```

`StorageBrowseResult` is a plain value object / DTO that carries albums, items, and metadata out of browse operations. It has no behavior. Placing it inside `Services/` implies it has service-level logic.

**How to fix**: Move to `App\DTO\Storage\StorageBrowseResult` or `App\Support\Storage\StorageBrowseResult`.

**Risk**: 🟢 Low — convention issue; no functional impact.

---

### Issue #23
**`PhotoDownloadExecutionService` checks `$storedFile->status === 'completed'` with a raw string**

**Breaks**: DRY, type safety

**Explain**:

```php
// PhotoDownloadExecutionService.php – line 61
if ($storedFile->status === 'completed') {
```

Same issue as #11, listed separately because it is in the hot path of the download job. Using enums or constants makes typos impossible to introduce silently.

**Risk**: 🟡 Medium in context of the download pipeline.

---

### Issue #24
**`DownloadContactsPhotosJob` is `@deprecated` but still in production codebase**

**Breaks**: YAGNI, KISS, cleanliness

**Explain**:

```php
/**
 * @deprecated Use PhotoDownloadService directly. Kept for in-flight queue jobs.
 */
final class DownloadContactsPhotosJob implements ShouldQueue { ... }
```

The docblock says this is deprecated and kept only for in-flight jobs. If this is genuinely obsolete, it should have a scheduled removal date or a migration path. Dead code accumulates over time and creates noise.

**How to fix**: Create a tracking issue with a timeline to remove after the queue drain period ends (e.g., 2 weeks after deployment of the replacement). If the queue is Redis-backed, use `queue:flush` to drain stale jobs safely.

**Risk**: 🟢 Low — cosmetic, but dead code can confuse future maintainers.

---

### Issue #25
**`FlickrContactController::show()` calls `$contactList->findContact($contactNsid)` which throws if contact not found**

**Breaks**: Defensive programming, user experience consistency

**Explain**:

```php
// FlickrContactController.php – line 101-103
abort_unless($contactList->isLinked($connection, $contactNsid), 404);
$contact = $contactList->findContact($contactNsid); // throws if not found
```

The `abort_unless()` check on line 101 guards against unlinked contacts. But `findContact()` can still throw if the contact was deleted between the two calls (TOCTOU race). The controller should gracefully handle a null result from `findContact()`.

**How to fix**: Change `findContact()` to return `?Contact` and use `abort_if($contact === null, 404)`.

**Risk**: 🟢 Low — rare race condition; edge case in practice.

---

### Issue #26
**`StorageOAuthService::clearOAuthSession()` does not clear `storage_oauth_return_url`**

**Breaks**: Completeness, security hygiene

**Explain**:

```php
// StorageOAuthService.php – lines 99-104
private function clearOAuthSession(): void
{
    session()->forget([
        'storage_oauth_state',
        'storage_oauth_provider',
        'storage_oauth_account_id',
        // 'storage_oauth_return_url' is missing!
    ]);
}
```

The `storage_oauth_return_url` is set in `begin()` and consumed in `consumeReturnUrl()`, but `clearOAuthSession()` doesn't clear it. If the OAuth flow fails partway through, the stale URL could persist in the session.

The `consumeReturnUrl()` method does `session()->forget('storage_oauth_return_url')` — so in the success path it's cleaned up. But on error paths that call `clearOAuthSession()` directly, the URL is leaked.

**How to fix**: Add `'storage_oauth_return_url'` to the `clearOAuthSession()` array.

**Risk**: 🟢 Low — stale redirect URL in session; cosmetic but a minor security hygiene issue.

---

### Issue #27
**`useStorageBrowse::syncFromProvider` uses recursion for paginated sync**

**Breaks**: React hooks best practices, stack-overflow risk for large libraries

**Explain**:

```tsx
// useStorageBrowse.ts – lines 191-193
if (json.data.has_more && !options?.manual) {
    await syncFromProvider({ maxBatches: 3 }); // ← recursive call
}
```

When `has_more` is true, `syncFromProvider` calls itself recursively (inside an async IIFE). For a library with thousands of items across dozens of batches, this creates a deep async call stack. While JavaScript has no strict stack limit for async functions (they resolve via the event loop), this pattern makes it hard to cancel mid-sync and can cause `syncDepthRef` to increment unboundedly if the API always returns `has_more: true` (e.g., due to a bug).

**How to fix**: Convert to an iterative loop:

```ts
while (json.data.has_more && !options?.manual && !cancelled) {
    json = await apiPost(...);
    await loadBrowse({ silent: true });
}
```

**Risk**: 🟢 Low — works in practice for typical library sizes; risk materialises only with very large libraries or buggy API responses.

---

### Issue #28
**`StorageUploadService::remoteMetadata()` has dead match arms for non-R2 drivers**

**Breaks**: YAGNI, dead code

**Explain**:

```php
// StorageUploadService.php – lines 129-133
return match ($driver) {
    StorageDriver::GoogleDrive => ['id' => $objectKey, 'etag' => null],
    StorageDriver::GooglePhotos => ['id' => $objectKey, 'etag' => null],
    StorageDriver::OneDrive => ['id' => $objectKey, 'etag' => null],
};
```

These arms all return the same value and are never actually reached because the method only executes `remoteMetadata()` for non-GooglePhotos drivers (the GooglePhotos path returns early at line 33). The `GoogleDrive`, `GooglePhotos`, and `OneDrive` match arms appear to be copy-paste stubs waiting for real implementation.

**How to fix**: If the intent is future placeholders, document clearly. If not needed, simplify:

```php
// For non-R2, non-GooglePhotos drivers that use standard Flysystem:
return ['id' => ltrim($remotePath, '/'), 'etag' => null];
```

**Risk**: 🟢 Low — dead code; misleading if a new driver is added expecting real metadata from these arms.

---

## Required AI Check After Implementation

After all issues above are fixed, AI must re-audit and verify:

### Checklist for Re-Audit

- [ ] **#1**: Confirm `PhotoUploadExecutionService` no longer dispatches `DownloadPhotoJob`. Verify `TransferOrchestrationService` exists and handles the pre-download → upload flow. Check batch counter integrity.
- [ ] **#2**: Confirm `DashboardService::snapshot()` takes zero parameters; `FlickrRateLimitPresenter` is constructor-injected.
- [ ] **#3**: Confirm `QueuePhotoUploadRequest` has no repository calls. Storage account resolution is in `PhotoUploadService`.
- [ ] **#4**: Confirm `Api\StorageBrowseController::download()` delegates file streaming to a service method. No `app()` in controller.
- [ ] **#5**: Confirm `ContactDownloadCountsService` no longer calls `Storage::exists()`. Verify list-page performance is acceptable.
- [ ] **#6**: Confirm `contact_detail` key removed from `FlickrContactController::show()` response.
- [ ] **#7**: Confirm `$request->sort()` and `$request->direction()` are used directly in controller without re-sanitising.
- [ ] **#8**: Confirm `StorageOAuthService::provider()` no longer calls `config([...])`. Verify OAuth flow still works end-to-end.
- [ ] **#9**: Confirm `diskFor()` signature cleaned up — `$credentials` parameter removed.
- [ ] **#10**: Confirm branching logic is in `PhotoDownloadService` / `PhotoUploadService`, not in controllers.
- [ ] **#11**: Confirm `StoredFileStatus` and `TransferItemStatus` enums exist and are used in all relevant files.
- [ ] **#12**: Confirm `DownloadPhotoJob::failed()` and `UploadPhotoJob::failed()` use method injection.
- [ ] **#13**: Confirm `FlickrAccountController::list()` uses `FlickrOAuthService::listAccounts()`.
- [ ] **#14**: Decision tracked: keep or change `CatalogController` + `FlickrOAuthService` coupling.
- [ ] **#15**: Confirm `StorageDeleteService` uses driver-specific interface for post-delete side effects; no `if ($driver === GooglePhotos)`.
- [ ] **#16**: Confirm `StorageBrowseSyncService` depends on an interface, not the concrete `StorageBrowseService`.
- [ ] **#17**: Confirm `useStorageBrowse` split into focused sub-hooks. Each hook is < 100 lines.
- [ ] **#18**: Confirm hardcoded `/settings?tab=storage` replaced with route helper.
- [ ] **#19**: Confirm `Dashboard.tsx` polling respects `document.visibilityState`.
- [ ] **#20** / **#21** / **#22**: Confirm `ContactListPresenter`, `ContactListSorter`, `StorageBrowseResult` moved to correct namespaces.
- [ ] **#23**: Confirm `'completed'` literal replaced with enum in `PhotoDownloadExecutionService`.
- [ ] **#24**: Confirm `DownloadContactsPhotosJob` removed (after queue drain confirmed).
- [ ] **#25**: Confirm `findContact()` returns `?Contact` and controller handles null gracefully.
- [ ] **#26**: Confirm `storage_oauth_return_url` added to `clearOAuthSession()` forget list.
- [ ] **#27**: Confirm recursive `syncFromProvider` replaced with iterative loop.
- [ ] **#28**: Confirm dead match arms in `remoteMetadata()` removed or documented.

---

## Notes

- Issues are based on direct source code inspection — no assumptions from docs or AI-generated guides.
- Risk levels: 🔴 Critical / 🟠 High / 🟡 Medium / 🟢 Low
- The project architecture is generally solid. The controller→service→repository pattern is well-followed in most areas. Issues identified are refinements, not fundamental rewrites.
- The frontend (React/TypeScript) is well-typed with consistent use of hooks and component decomposition. The main issue is hook size and a few hardcoded strings.
- Service naming consistency is the most pervasive low-risk issue — Presenters/Sorters/DTOs mixed in with Services.
