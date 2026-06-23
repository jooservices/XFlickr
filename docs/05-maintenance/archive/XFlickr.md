# XFlickr — Implementation Plan

Status: planning (no code written yet)
Audited: jflickr, flickr (SDK), xflickr-crawler, laravel-config, laravel-repository, laravel-logging, laravel-events
Date: 2026-06-22

## 1. Goal

Rebuild jflickr's app concerns from scratch as a new project, **XFlickr**, on top of the `jooservices/xflickr-crawler` package as the primary crawling engine (instead of jflickr's bespoke in-app crawling code). Same primary purpose (connect Flickr accounts, browse contacts/photos/photosets/galleries/favorites, download locally, upload to cloud) but with a leaner UI driven by explicit user actions — **no automatic/background spidering**, since Flickr API quota is the limiting resource.

## 2. Packages

| Package | Packagist status | Role in XFlickr |
|---|---|---|
| `jooservices/laravel-config` | ✅ published | Flickr app credentials (api key/secret) + storage app credentials, stored outside `.env`, with Redis caching |
| `jooservices/flickr` | ✅ published | Flickr API SDK — OAuth 1.0a, contacts/people/photos/photosets/galleries/favorites/upload services |
| `jooservices/xflickr-crawler` | ❌ **not on Packagist — needs release** | Main crawling engine: connections, rate limiting, crawl runs/targets, fetchers, jobs, repositories for contacts/photos/photosets/galleries (+ favorites to be added, see §5) |
| `jooservices/laravel-repository` | ✅ published | Repository pattern for XFlickr's own Eloquent models (FlickrAccount, StoredFile, UploadQueue, etc.) |
| `jooservices/laravel-logging` | ❌ **not on Packagist — needs release** | Structured audit logging (account connect/disconnect, crawl triggers, download/upload outcomes) to MongoDB |
| `jooservices/laravel-events` | ✅ published | Domain event sourcing (AccountConnected, CrawlRequested, DownloadCompleted, UploadCompleted, etc.) |

**Action needed from you:** publish `jooservices/xflickr-crawler` and `jooservices/laravel-logging` to Packagist (or add as path/VCS repositories in XFlickr's `composer.json` until then).

## 3. Tech stack (mirrors jflickr)

- Laravel 12, PHP 8.5+
- MySQL for app data (XFlickr's own tables + xflickr-crawler's own tables, same connection)
- Redis + Laravel Horizon for queues (xflickr-crawler already assumes Redis for rate limiting)
- React 19 + Inertia 3 + TypeScript + Tailwind 4 for UI (copy jflickr's frontend scaffolding/build setup, not its page content)
- MongoDB only as required by laravel-config / laravel-logging / laravel-events (same as jflickr already does)

## 4. Process flow (delta vs jflickr)

jflickr's OAuth connect/callback/disconnect/activate flow for Flickr accounts is reused as-is (Settings-driven Flickr app credentials, `FlickrApp` → `FlickrAccount`). What's different:

### 4.1 New `/flickr` view — connected accounts hub

Table of connected Flickr accounts (`FlickrAccount`: NSID, username, connected_at, is_active) with three explicit per-row actions. **None of these run automatically — each is a manual button click**, dispatching one queued job chain via XFlickrCrawler.

| Action | Behavior |
|---|---|
| **Crawl** | Opens a small picker (or runs all) for: contacts, photos, photosets, favorites, galleries. Each calls `FlickrService::connection($connectionKey, $token)->{contacts,photos,photosets,favorites,galleries}()` from xflickr-crawler, scoped to this account's own NSID as subject. |
| **Download** | Enqueues `DownloadAccountPhotosJob`: for every `xflickr_photos` row owned by this account not yet in `xflickr_stored_files` (local), download original/large file to local disk; skip if already present (checksum/size+flickr_photo_id dedupe). |
| **Upload** | Enqueues `UploadAccountPhotosJob`: for every local file pending upload, ensure downloaded first (reuses Download logic), then queue one-by-one upload jobs to the configured cloud storage account (OneDrive/Google Drive, same flysystem drivers as jflickr). |

### 4.2 `/contacts` view — discovered contacts table

Driven by `xflickr_contacts` (discovered via the account's own Crawl→contacts run). Columns:

| NSID | Name/username | Photos | Photosets | Galleries |
|---|---|---|---|---|

Counts are read off `xflickr_photos`/`xflickr_photosets`/`xflickr_galleries` rows scoped to `owner_nsid = contact.nsid` (populated once that contact has been crawled — initially 0 until crawled).

Per-row action:

| Action | Behavior |
|---|---|
| **Crawl** | Same four crawl types (photos, photosets, favorites, galleries — contacts crawl doesn't apply to a contact, only to "self") run against this contact's NSID as subject, via the *same connection/token* of the account that discovered them (Flickr's API allows querying another user's public data using your own authenticated connection). |

No contact-level Download/Upload buttons per the spec — those are account-scoped only (§4.1). If you later want per-contact download/upload, the same job classes can be parameterized by NSID instead of account.

### 4.3 No auto-spidering

xflickr-crawler's scheduler (`DispatchCrawlTargetsCommand`) only **drains already-enqueued CrawlTargets** — it does not create new ones on its own. New CrawlRuns/CrawlTargets are created exclusively by the explicit Crawl button presses described above. This is already xflickr-crawler's natural behavior (it has no cron-based "discover everything" trigger); XFlickr just needs to make sure no code path calls `$conn->contacts()` etc. except from a controller action a user triggered.

## 5. Required additions to `jooservices/xflickr-crawler`

The package already covers contacts/photos/photosets/galleries. Missing for this plan:

1. **Favorites crawl type** — add `CrawlType::Favorites`, `FavoritesFetcher` (wraps `$flickr->favorites()->getList()`), `FetchFavoritesPageJob`, `xflickr_favorites` migration (`connection_key`/`owner_nsid`, `flickr_photo_id` FK, `discovered_at`), and `FavoriteRepository`. Mirrors the existing Contacts crawl shape closely.
2. **Crawl-by-subject-NSID for "another user", reusing my own connection token** — confirm `FlickrCrawlerManager::connection()->photos($subjectNsid)` etc. already accept an explicit subject NSID distinct from the token owner (this looks supported today since `PeoplePhotosFetcher` takes an NSID param) — no change expected, just confirm during implementation.
3. Expose a clean way for the host app to query **per-NSID counts** (photos/photosets/galleries/favorites) without the host app reaching into xflickr-crawler's internal tables directly — either expose read models/repositories as public API, or document that host apps query xflickr-crawler's tables directly (acceptable since the package is MySQL/Eloquent-based already).

## 6. New XFlickr app — data model

XFlickr owns these tables (via `jooservices/laravel-repository`); xflickr-crawler owns its own `xflickr_*` tables untouched.

| Table | Purpose |
|---|---|
| `flickr_apps` | Flickr API app credentials (key/secret), managed via laravel-config |
| `flickr_accounts` | Connected Flickr accounts: nsid, username, fullname, encrypted token_payload, is_active, connected_at. Bridges to xflickr-crawler via `connection_key` (= account id or nsid) |
| `storage_apps` / `storage_accounts` | Cloud storage (OneDrive/Google Drive) app credentials + connected accounts — copy jflickr's existing schema/services largely as-is |
| `stored_files` | Local-download + cloud-upload tracking per `xflickr_photos.flickr_photo_id`: local_path, local_downloaded_at, storage_account_id, storage_path, uploaded_at — this is the de-dup source of truth for both Download and Upload actions |
| `upload_queue` (optional, or just queued jobs) | If you want a visible queue/progress UI for "uploading one by one", track per-photo upload progress here; otherwise rely on Horizon's job UI |

### Dedup logic (both Download and Upload)

```
Download(photo):
  if stored_files.where(flickr_photo_id, photo.id).local_downloaded_at is not null: skip
  else: fetch file from Flickr, save to local disk, upsert stored_files.local_downloaded_at

Upload(photo, storageAccount):
  if stored_files.where(flickr_photo_id, photo.id).uploaded_at (for this storage_account_id) is not null: skip
  else:
    if not locally downloaded: Download(photo)   # reuse above, still deduped
    queue UploadFileJob(photo, storageAccount)     # one job per photo, processed serially via a single-concurrency queue/horizon supervisor
```

"Queue one by one" = a dedicated Horizon supervisor/queue (e.g. `xflickr-uploads`) configured with `maxProcesses: 1` so uploads never run concurrently against the cloud API, while downloads can use a higher-concurrency queue.

## 7. Routes / Controllers / Pages (delta vs jflickr)

Reuse jflickr's Settings, Dashboard, Catalog (photos/photosets/galleries — galleries view stays), FlickrAuth, StorageAuth controllers/pages as a starting skeleton (copy code, not blind-paste — re-review each since crawling logic moves to the package).

| Route | New/Changed | Notes |
|---|---|---|
| `/flickr` | **new** | Replaces jflickr's implicit "Settings tab" account list with a dedicated page: account table + Crawl/Download/Upload actions |
| `/flickr/oauth`, `/flickr/callback`, `/flickr/disconnect`, `/flickr/activate` | reuse | Same OAuth 1.0a flow, same controller shape as jflickr's `FlickrAuthController` |
| `/contacts` | **changed** | Table columns simplified to NSID/Name/Photos/Photosets/Galleries + single Crawl action (drop jflickr's export/upload-to-Flickr/suggest features unless you want them back later) |
| `/contacts/{contact}` | keep (optional) | Detail drill-in showing the contact's crawled photos/photosets/galleries/favorites |
| `/photos`, `/photosets`, `/galleries` | reuse | Same catalog browsing concept, now reading from `xflickr_photos`/`xflickr_photosets`/`xflickr_galleries` instead of jflickr's own tables |
| `/crawl/operations` | reuse (optional) | Live view of `xflickr_crawl_runs`/`xflickr_crawl_targets`/`xflickr_api_logs` — useful for visibility into the package's queue, keep it |
| `/settings` | reuse | Flickr app + Storage app credential management (laravel-config backed) |
| jflickr's `ContactExportRun` / "export contact album" / "upload contact photos back to Flickr" features | **drop** | Not in scope per the spec — XFlickr's Upload action is "upload to cloud", not "push back to Flickr" |

API endpoints mirror the Web ones needed to drive the React/Inertia pages (`/api/flickr/accounts`, `/api/flickr/accounts/{id}/crawl`, `/api/flickr/accounts/{id}/download`, `/api/flickr/accounts/{id}/upload`, `/api/flickr/contacts`, `/api/flickr/contacts/{nsid}/crawl`, plus catalog/dashboard/settings endpoints copied from jflickr).

## 8. Jobs (XFlickr app layer, on top of xflickr-crawler's own jobs)

| Job | Queue | Purpose |
|---|---|---|
| `TriggerAccountCrawlJob` | default | Thin dispatcher: calls into `FlickrService::connection(...)->{type}()` for the requested crawl type(s) — most of the actual work happens inside xflickr-crawler's own jobs |
| `TriggerContactCrawlJob` | default | Same, scoped to a contact NSID using the discovering account's connection |
| `DownloadPhotoJob` | `xflickr-downloads` | Dedup-checked single-photo download to local disk |
| `UploadPhotoJob` | `xflickr-uploads` (concurrency=1) | Dedup-checked single-photo cloud upload, ensures local download first |
| `DownloadAccountPhotosJob` / `UploadAccountPhotosJob` | default | Fan-out: chunk through an account's `xflickr_photos`, dispatch one `DownloadPhotoJob`/`UploadPhotoJob` per photo |

## 9. Logging & events

- `jooservices/laravel-events`: emit `FlickrAccountConnected`, `FlickrAccountDisconnected`, `CrawlTriggered(account/contact, type)`, `DownloadCompleted(photo)`, `UploadCompleted(photo, storageAccount)`, `UploadFailed(photo, reason)`.
- `jooservices/laravel-logging`: structured log entries for every crawl trigger, download, and upload outcome (success/failure/skipped-dedup), for audit/troubleshooting — replaces jflickr's `FlickrCrawlLog`/`StorageRequestLimiter`-style ad hoc logging with the shared package.

## 10. Build order

1. Release `jooservices/xflickr-crawler` and `jooservices/laravel-logging` to Packagist (or wire as path repos meanwhile).
2. Add Favorites crawl type to `xflickr-crawler` (§5).
3. Scaffold new Laravel 12 app `XFlickr` (composer.json pulling all 6 packages), copy jflickr's Vite/Tailwind/Inertia/TS build config.
4. Build `flickr_apps`/`flickr_accounts` + Flickr OAuth controller/service (copy+adapt from jflickr, but token store now also seeds xflickr-crawler's `Connection` via `connection_key`).
5. Build `/flickr` page + account Crawl/Download/Upload actions and their controllers/jobs.
6. Build `/contacts` page + per-contact Crawl action, reading counts from xflickr-crawler's tables.
7. Build `stored_files` dedup model + Download/Upload job pipeline + storage app/account OAuth (copy jflickr's OneDrive/Google Drive flysystem integration).
8. Wire laravel-events + laravel-logging across the above.
9. Reuse/port Catalog (photos/photosets/galleries) and Dashboard/Settings/Crawl-operations pages from jflickr, pointed at the new tables.
10. Drop jflickr-only features out of scope: contact export runs, upload-back-to-Flickr, contact suggestion.

## 11. Open items to confirm during implementation

- Exact `connection_key` convention bridging `flickr_accounts.id` (or `nsid`) to xflickr-crawler's `Connection.connection_key` — recommend using the account's NSID directly since contacts will also need to be crawled under the discovering account's connection, not their own.
- Whether `/contacts/{contact}` detail page is needed at launch or can follow later (kept as "optional" in §7).
- Final list of dedup keys for `stored_files` per storage account (a photo can be uploaded to multiple storage accounts independently — confirm table needs `(flickr_photo_id, storage_account_id)` uniqueness, not just `flickr_photo_id`).
