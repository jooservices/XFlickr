# XFlickr Implementation Plan Critique

## Executive Summary

The plan is directionally sound: moving crawling into `jooservices/xflickr-crawler`, making crawl actions explicit, and treating API quota as the core constraint are good architectural choices.

The main risks are around ownership boundaries, identity modeling, deduplication semantics, queue idempotency, and package coupling. Several parts of the plan currently blur “account owner”, “subject user”, “discovered contact”, “photo owner”, “connection key”, and “storage target”. Those distinctions need to be made explicit early or the app will accumulate subtle bugs once users crawl contacts, favorites, and uploads across multiple Flickr/storage accounts.

The biggest design issues I would address before implementation:

1. `stored_files` cannot safely be keyed only by `flickr_photo_id`.
2. `connection_key = nsid` is convenient but may become brittle.
3. Favorites are not just another photo crawl; ownership and privacy semantics differ.
4. “No background spidering” conflicts slightly with crawler concepts like `CrawlTargets`.
5. Upload/download fan-out needs stronger idempotency and progress modeling.
6. Directly reading crawler tables from XFlickr weakens the package boundary.
7. MongoDB dependency creep may be overkill for the stated “leaner” app.
8. Laravel 12 + PHP 8.5 may be too aggressive depending on package compatibility.
9. Cloud upload dedup needs storage-account-aware and path-aware constraints.
10. Contact crawling through the discovering account raises authorization, visibility, and provenance questions.

---

## 1. Package Boundary Concerns

### `xflickr-crawler` is both engine and database owner

The plan says `xflickr-crawler` owns its own `xflickr_*` tables, but XFlickr pages will read from those tables heavily:

- `/contacts`
- `/photos`
- `/photosets`
- `/galleries`
- `/crawl/operations`
- count queries
- download/upload photo source queries

That creates a tight schema dependency. If `xflickr-crawler` changes table names, columns, enum values, or relationships, XFlickr breaks.

You already identify this in §5.3, but it should be treated as a core architectural decision, not an implementation detail.

Recommended direction:

- Expose read repositories/query services from `xflickr-crawler`.
- Treat direct table reads as a temporary shortcut only.
- Version the crawler’s read model API intentionally.

For example:

```php
CrawlerPhotos::forOwnerNsid($nsid)
CrawlerCounts::forSubject($connectionKey, $subjectNsid)
CrawlerRuns::forConnection($connectionKey)
```

That would preserve the package boundary while still allowing efficient SQL internally.

### Risk of duplicating repository abstractions

You plan to use:

- `jooservices/xflickr-crawler` repositories
- `jooservices/laravel-repository` for XFlickr models

This can work, but it may lead to two repository styles in one app. If the crawler package already has repositories, XFlickr should avoid wrapping them again unless there is a real domain reason.

Potential issue:

```text
XFlickr Repository -> Crawler Repository -> Eloquent Model
```

That often becomes ceremony without much value.

Recommendation:

- Use app repositories only for XFlickr-owned aggregates.
- Use crawler package query APIs directly for crawler-owned data.
- Avoid creating XFlickr repositories over `xflickr_*` tables.

---

## 2. Version and Compatibility Risks

### Laravel 12 + PHP 8.5+

This is an aggressive baseline.

Potential problems:

- PHP 8.5 may not be broadly supported by all dependencies yet.
- Laravel 12 compatibility must be verified across all six packages.
- React 19, Inertia 3, Tailwind 4 is also a modern stack with fewer mature examples.
- Flysystem drivers for OneDrive/Google Drive may lag behind newer Laravel/PHP versions.

This is especially risky because two packages are not yet published and may not have clean dependency constraints.

Recommendation:

- Confirm each package’s `composer.json` supports Laravel 12 and PHP 8.5.
- Consider targeting PHP 8.3 or 8.4 first unless PHP 8.5 is a hard requirement.
- Add a compatibility matrix before implementation.

Example:

| Package | PHP | Laravel | Queue | DB | Notes |
|---|---:|---:|---|---|---|
| `jooservices/flickr` | ? | ? | n/a | n/a | OAuth + services |
| `xflickr-crawler` | ? | ? | Redis | MySQL | critical |
| `laravel-events` | ? | ? | ? | MongoDB | check event storage |
| `laravel-logging` | ? | ? | ? | MongoDB | unpublished |

### Unpublished packages in the critical path

Both `xflickr-crawler` and `laravel-logging` are central to the plan, but unpublished.

That creates several risks:

- No immutable version tags.
- Composer installs may depend on local filesystem state.
- CI/CD is harder.
- App scaffolding may begin before package APIs stabilize.
- Breaking changes are likely during XFlickr implementation.

Recommendation:

- Tag pre-release versions before app work, even if private or VCS-based.
- Use explicit version constraints like `dev-main as 0.1.0` only temporarily.
- Do not build XFlickr against moving `main` branches without lock discipline.

---

## 3. Identity and Ownership Model

This is the largest conceptual risk in the plan.

You have several different identities:

| Concept | Meaning |
|---|---|
| Flickr account | Authenticated user who connected OAuth |
| Connection | API credential/token context used to call Flickr |
| Subject NSID | User whose data is being crawled |
| Contact NSID | User discovered from another account’s contacts |
| Photo owner NSID | Owner of a photo row |
| Storage account | Cloud destination |
| Active account | UI/default account |

The plan sometimes treats these as interchangeable.

### `connection_key = nsid` needs scrutiny

Using NSID as `connection_key` is understandable, but it may cause problems.

Benefits:

- Stable across app database IDs.
- Human-debuggable.
- Naturally maps to Flickr identity.

Risks:

- If the same Flickr account is disconnected/reconnected, does the same connection key get reused?
- If token payload changes, do old crawl runs remain valid?
- If multiple local XFlickr users connect the same Flickr NSID, does the connection collide?
- If the app later supports multi-user tenancy, NSID alone is not sufficient.
- If a user has multiple Flickr auth tokens over time, connection history is flattened.

If XFlickr is single-user/local only, NSID is probably fine. If this could become multi-user, use an internal connection UUID or account ID.

A safer model:

```text
flickr_accounts.id = internal primary key
flickr_accounts.nsid = Flickr identity
flickr_connections.connection_key = stable UUID or account id
crawl_runs.connection_key = API credential context
crawl_runs.subject_nsid = user being crawled
```

If you still use NSID as connection key, document it as a single-tenant assumption.

### Contact provenance is underspecified

A contact discovered by Account A may also be discovered by Account B. Visibility and crawl results may differ depending on which authenticated token is used.

For example:

```text
Account A discovers Contact X
Account B also discovers Contact X
Contact X has public photos plus some photos visible only to Account A
```

If `xflickr_photos` rows are keyed only by `owner_nsid`, you can accidentally merge data observed through different connections.

Questions to answer:

- Are crawler rows global by `owner_nsid`?
- Or scoped by `(connection_key, owner_nsid)`?
- If Account A and Account B crawl the same contact, are results merged?
- Can one account see private/friends/family content another cannot?
- Does the UI show “photos for this contact” globally or “photos visible through this account”?

Recommendation:

Store both:

```text
connection_key       // credential used
subject_nsid         // user being crawled
owner_nsid           // actual owner of discovered content, usually same for photos
visibility_context   // optional, if supported
```

Counts should probably be scoped by `(connection_key, subject_nsid)`, not only `owner_nsid`.

---

## 4. Favorites Crawl Type Issues

The plan treats favorites as similar to contacts, but favorites are semantically different.

A favorite record is not a photo owned by the subject. It is a relationship:

```text
subject user favorited photo X owned by some other owner
```

So this table design is likely insufficient:

```text
xflickr_favorites:
  connection_key
  owner_nsid
  flickr_photo_id
  discovered_at
```

The column `owner_nsid` is ambiguous. Is it:

- The user whose favorites are being crawled?
- The owner of the favorited photo?

Those are different.

Better:

```text
xflickr_favorites:
  connection_key
  subject_nsid            // whose favorites list this is
  flickr_photo_id
  photo_owner_nsid        // owner of favorited photo, if available
  favorited_at            // if Flickr provides it
  discovered_at
```

Also consider whether a favorite should require a corresponding `xflickr_photos` row. If the favorite references a photo not otherwise crawled, you may not have full metadata.

Options:

1. Store favorites as relationship-only rows and lazily hydrate photo metadata later.
2. Upsert minimal photo metadata into `xflickr_photos`.
3. Create a separate `xflickr_photo_refs` concept.

Do not assume favorites can simply FK into a complete `xflickr_photos` table unless the fetcher guarantees photo upsert first.

---

## 5. Crawl Flow and “No Background Spidering”

The “manual only” principle is good, but the current flow still relies on queued crawl targets. That is fine, but the terminology needs care.

Potential conflict:

- User clicks Crawl.
- Controller creates crawl run/targets.
- Scheduler command drains crawl targets.
- Queue workers process fetch jobs.

Even though the user initiated it, work continues asynchronously and may fan out across many pages. That is still background processing, just not background discovery.

Recommendation:

Use precise language:

```text
No automatic discovery or scheduled creation of new crawl targets.
User actions may enqueue asynchronous crawl jobs.
```

Also define guardrails:

- Maximum pages per manual crawl?
- Confirmation if crawl scope is large?
- Rate limit visibility?
- Ability to cancel a crawl run?
- Ability to pause a connection?
- Retry policy for failed pages?
- Resume behavior after failure?

Without these, “manual” can still become quota-expensive.

### “Crawl all” may be dangerous

The `/flickr` action can “run all” for contacts, photos, photosets, favorites, galleries.

That can burn quota quickly, especially favorites/photos/galleries for large accounts.

Recommendation:

- Default to picker, not all.
- Show estimated scope where possible.
- Add per-run limits.
- Add crawl type descriptions.
- Add a dry-run or “first page only” option for early testing.

---

## 6. Data Model Critique

### `stored_files` needs stronger uniqueness

The plan says:

```text
stored_files per xflickr_photos.flickr_photo_id:
  local_path
  local_downloaded_at
  storage_account_id
  storage_path
  uploaded_at
```

But later you note that uploads may go to multiple storage accounts. That means this table is mixing two concepts:

1. Local file cache.
2. Cloud upload state.

Those should probably be separate.

Current problem:

If one photo is downloaded once and uploaded to two storage accounts, you either duplicate local file data or lose per-storage upload state.

Recommended model:

```text
stored_files
  id
  flickr_photo_id
  local_disk
  local_path
  original_filename
  media_type
  byte_size
  checksum
  downloaded_at
  download_status
  UNIQUE(flickr_photo_id)

storage_uploads
  id
  stored_file_id
  storage_account_id
  storage_disk
  storage_path
  remote_file_id
  uploaded_at
  upload_status
  failure_reason
  UNIQUE(stored_file_id, storage_account_id)
```

If different sizes are downloaded, include variant:

```text
UNIQUE(flickr_photo_id, variant)
```

Example variants:

- original
- large
- medium
- video_original

### `flickr_photo_id` alone may not be globally enough for local files

Flickr photo IDs are globally unique in practice, but the file representation may differ:

- original image
- large image
- resized image
- video
- metadata sidecar
- album folder export
- filename collision

If XFlickr only downloads one canonical “best available” file, `flickr_photo_id` is acceptable. If it may download original vs large fallback, store the selected variant and source URL metadata.

Suggested columns:

```text
flickr_photo_id
owner_nsid
source_size_label
source_url_hash
extension
mime_type
byte_size
checksum
downloaded_at
```

### `upload_queue` optionality may hurt UX

The plan says `upload_queue` is optional, or rely on Horizon.

Horizon is an operator tool, not a user-facing progress model. If the XFlickr UI needs to show upload/download progress, retry state, or skipped counts, Horizon is not enough.

Recommendation:

Create durable operation tables for app-visible work:

```text
transfer_batches
  id
  account_id
  subject_nsid
  type: download/upload
  status
  total_count
  completed_count
  failed_count
  skipped_count

transfer_items
  id
  batch_id
  flickr_photo_id
  storage_account_id nullable
  status
  attempts
  last_error
```

Jobs can still be the execution mechanism, but the UI should not depend on queue introspection.

### `flickr_apps` managed by `laravel-config` is conceptually odd

The plan lists `flickr_apps` as a table but says credentials are managed via `laravel-config`.

If `laravel-config` stores credentials outside `.env` and uses MongoDB/Redis, then is `flickr_apps` an actual SQL table or a config namespace?

Clarify:

- Are app credentials in MySQL?
- MongoDB?
- Redis cache backed by what?
- Are `flickr_apps` and `storage_apps` true Eloquent models or config records?

Avoid naming config-backed records as tables if they are not real tables.

---

## 7. Download Pipeline Issues

### Flickr original access is not guaranteed

The plan says download original/large file. But Flickr permissions and API responses may not always provide original URLs.

Potential cases:

- Original unavailable.
- Only public resized sizes available.
- Video assets.
- Restricted/private photos visible only with auth.
- Deleted or unavailable photo after crawl.
- Rate-limited size lookup.
- Hotlinked URL expires or changes.
- Photo metadata exists but no downloadable size exists.

Recommendation:

Model download status explicitly:

```text
pending
downloading
downloaded
skipped_unavailable
skipped_unsupported
failed_retryable
failed_permanent
```

Store the selected size:

```text
selected_size_label
selected_width
selected_height
source_url
source_url_checked_at
```

### Checksum strategy needs definition

The plan says checksum/size+photo ID dedupe.

Checksums require reading the full file after download, which is fine, but they do not help skip before download unless previously known.

Recommended:

- Use `flickr_photo_id + variant` as primary logical dedup.
- Use checksum as integrity validation and optional physical dedup.
- Use byte size to detect incomplete downloads.
- Write to temp path first, then atomic move.

### Concurrent download race

Two jobs could attempt the same photo simultaneously:

- Account upload ensures download first.
- User separately clicks Download.
- Retry overlaps with existing job.
- Multiple fan-out jobs include same photo.

A plain “check then insert” is not enough.

Recommendation:

Use a database unique constraint and transactional state transition:

```text
UNIQUE(flickr_photo_id, variant)
```

Then:

```text
firstOrCreate pending row
lock row
if downloaded skip
mark downloading
download temp file
atomic move
mark downloaded
```

Or use Laravel atomic locks keyed by photo ID, but still keep DB uniqueness.

---

## 8. Upload Pipeline Issues

### “Queue one-by-one” via Horizon `maxProcesses: 1` is fragile

A single-concurrency queue works, but it is operational configuration, not domain logic.

Potential issues:

- Another worker accidentally listens to the same queue.
- Horizon config differs by environment.
- Retries may interleave with new uploads.
- Multiple storage accounts all get serialized unnecessarily.
- A single stuck upload blocks everything.

Recommendation:

Use both:

- queue-level low concurrency
- application-level locks per `storage_account_id`

Example lock key:

```text
xflickr:upload:storage-account:{id}
```

This allows one upload per storage account while still supporting parallelism across different cloud accounts if desired.

### Upload dedup must include remote identity

For reliable cloud dedup, `uploaded_at` is not enough.

Store:

```text
storage_account_id
remote_file_id
remote_etag
remote_drive_id
remote_parent_id
storage_path
uploaded_at
```

If a file is moved or renamed remotely, path-based dedup may fail. A remote file ID is more durable.

### Folder/path strategy is missing

The plan does not define how files are organized in cloud storage.

Need to decide:

- By Flickr account?
- By contact owner?
- By album/photoset?
- Flat by date?
- Preserve Flickr title?
- Use photo ID in filename?
- Handle duplicate filenames?
- Handle unsafe characters?
- Handle path length limits?

Recommended filename format:

```text
{photo_id}_{safe_title}.{ext}
```

Recommended folder examples:

```text
/Flickr/{owner_nsid}/Photos/
/Flickr/{owner_nsid}/Photosets/{photoset_id}-{safe_title}/
/Flickr/Favorites/{subject_nsid}/
```

But choose one canonical layout before upload jobs are built.

---

## 9. Contacts View and Counts

### Counts scoped only by `owner_nsid` are likely wrong

The plan says counts are read from:

```text
xflickr_photos / xflickr_photosets / xflickr_galleries
where owner_nsid = contact.nsid
```

This ignores:

- Which connected account discovered the contact.
- Which connection crawled the data.
- Whether the data is public or connection-specific.
- Whether rows are stale.
- Whether the current active account has access.

Better count scope:

```text
connection_key = discovering account connection
subject_nsid = contact.nsid
crawl_type = photos/photosets/galleries/favorites
```

If crawler tables do not distinguish these, add that now.

### Contact identity table may need app-level normalization

If `xflickr_contacts` belongs to crawler, does XFlickr have its own `contacts` table? The plan says `/contacts` is driven by `xflickr_contacts`.

That may be okay, but consider whether XFlickr needs app-level contact annotations later:

- hidden
- pinned
- last crawled
- display alias
- selected for download
- notes
- crawl preference

If yes, introduce an app-owned `flickr_contact_profiles` or `contact_preferences` table keyed by `nsid`.

Do not overload crawler-owned `xflickr_contacts` with UI state.

---

## 10. OAuth and Token Storage

### Token payload encryption is necessary but not sufficient

The plan says `encrypted token_payload`.

Good, but also clarify:

- Which Laravel cast/encryption mechanism?
- Is token payload ever logged?
- Does `laravel-logging` redact it?
- Are failed Flickr API requests logging Authorization headers?
- Is disconnect deleting tokens or marking revoked?
- Is reconnect rotating crawler connection credentials?

Recommendation:

Create a redaction policy early. Anything involving OAuth tokens, API secrets, refresh tokens, or cloud credentials should be impossible to serialize into logs by accident.

### Disconnect behavior needs definition

If a Flickr account is disconnected:

- Are crawl rows deleted?
- Are local files retained?
- Are cloud uploads retained?
- Are queued jobs cancelled?
- Are tokens revoked remotely?
- Are pending jobs allowed to fail?
- Does account row soft-delete?

Recommendation:

Soft-delete or deactivate the account, revoke/delete token payload, and prevent new jobs from starting. Existing crawler data can remain but should be marked as disconnected/unavailable in UI.

---

## 11. Events and Logging Concerns

### Event sourcing may be too heavy

The plan says `laravel-events` for domain event sourcing.

But the listed events are mostly audit/integration events:

- `FlickrAccountConnected`
- `CrawlTriggered`
- `DownloadCompleted`
- `UploadCompleted`

If these are not used to rebuild state, this is not really event sourcing; it is domain event logging.

Potential issue:

You may end up with:

- SQL rows for state
- Mongo logs for audit
- Mongo events for domain history
- Horizon for queue status

That is four places to inspect one user action.

Recommendation:

Clarify whether events are:

1. Event-sourced source of truth.
2. Domain events for side effects.
3. Audit trail.

If state lives in MySQL, call them domain events, not event sourcing. Keep event payloads small and avoid duplicating full model snapshots.

### MongoDB dependency creep

The goal is a leaner app, but the plan requires MongoDB for:

- config
- logging
- events

That makes local setup and deployment heavier.

Possible issue:

For a small explicit-action app, MySQL plus Redis may be enough.

Recommendation:

Justify MongoDB explicitly. If these packages require it, okay, but recognize the operational cost:

- backup strategy
- local dev setup
- production monitoring
- data retention
- indexes
- PII/token redaction

---

## 12. Queue and Job Design

### Fan-out jobs need batch tracking

`DownloadAccountPhotosJob` and `UploadAccountPhotosJob` fan out into many jobs. Without durable batch rows, the UI cannot reliably answer:

- How many were queued?
- How many skipped?
- How many failed?
- Which failed?
- Can I retry only failures?
- Did this run finish?

Laravel Bus batches could help, but persistent app tables are often better for user-facing operations.

Recommendation:

Use Laravel batches plus app-level batch records, or just app-level transfer tables.

### Idempotency keys should be first-class

Every job should have a natural idempotency key.

Examples:

```text
crawl: {connection_key}:{subject_nsid}:{crawl_type}:{requested_at/run_id}
download: {flickr_photo_id}:{variant}
upload: {stored_file_id}:{storage_account_id}
```

For crawl jobs, you may want to prevent duplicate active crawl runs for the same tuple:

```text
UNIQUE(connection_key, subject_nsid, crawl_type, status in active statuses)
```

SQL partial unique indexes vary by database, so this may require application locks.

### Retry policy differs by job type

Do not use one default retry policy.

Suggested:

| Job | Retry style |
|---|---|
| Crawl API page | retry with backoff; respect rate limits |
| Download file | retry network failures; permanent skip for unavailable size |
| Upload file | retry API/network/rate limit; permanent fail on auth/path errors |
| Trigger fan-out | low retry; idempotent dispatch required |

---

## 13. Flickr API and Quota Concerns

### Manual UI alone does not protect quota

A user can repeatedly click Crawl. Multiple crawl runs can overlap.

You need controls:

- disable button while active run exists
- debounce controller action
- server-side duplicate protection
- per-account crawl cooldown
- quota usage display
- confirmation for large crawls

### Rate limiting across package and app

`xflickr-crawler` assumes Redis rate limiting. But XFlickr download jobs may also call Flickr APIs, especially for sizes/original URLs.

Make sure download jobs use the same rate limiter namespace as crawler jobs. Otherwise the crawler and downloader can collectively exceed quota.

### API logs may grow quickly

`xflickr_api_logs` is useful, but large crawls can create huge logs.

Plan retention:

- prune after N days
- keep failures longer
- aggregate quota stats
- index by `connection_key`, `run_id`, `endpoint`, `created_at`

---

## 14. UI / Route Design Concerns

### Web routes plus API endpoints may duplicate Inertia patterns

The plan mentions Web routes and matching API endpoints. Inertia apps often do not need separate JSON APIs for every page action. You can use controller actions returning redirects/Inertia responses.

Potential issue:

You may double the surface area:

```text
/flickr
/api/flickr/accounts
/api/flickr/accounts/{id}/crawl
```

Recommendation:

Choose a pattern:

- Inertia page props for page data.
- Web POST actions for mutations.
- JSON APIs only for live polling/progress or async widgets.

### `/contacts/{contact}` route ambiguity

If `{contact}` is an NSID, be explicit:

```text
/contacts/{nsid}
```

Also consider that NSIDs may contain characters requiring route constraints.

### Active account behavior is underspecified

The plan includes `is_active`, but many routes need to know which account context they are in.

Questions:

- Does `/contacts` show contacts for all accounts or active account?
- Does `/photos` show all crawled photos or active account scope?
- Does `/flickr` account Crawl use each account row.
- Does contact Crawl require selecting which discovering account to use if multiple discovered the same contact?

Recommendation:

Make account scope explicit in the UI and queries.

Possible routes:

```text
/flickr/accounts/{account}/contacts
/flickr/accounts/{account}/photos
/flickr/accounts/{account}/contacts/{nsid}
```

Or keep global routes but require an account filter.

---

## 15. Build Order Issues

The build order mostly makes sense, but Favorites may not need to block the entire app.

Current order:

1. Release packages.
2. Add Favorites.
3. Scaffold app.

Potential issue:

You delay all app work on Favorites even though photos/contacts/photosets/galleries are enough to validate the architecture.

Suggested build order:

1. Stabilize `xflickr-crawler` package API and publish/tag it.
2. Scaffold XFlickr with one connected account.
3. Implement account crawl for contacts/photos only.
4. Implement crawler read model/query API.
5. Implement local download pipeline for photos.
6. Add upload pipeline.
7. Add photosets/galleries.
8. Add favorites once the subject/owner model is settled.
9. Wire logging/events after core state transitions are stable.

This reduces early risk.

### Logging/events should not block core behavior

Wiring events and structured logs before the model stabilizes can create churn. I would define event names early, but implement them after core jobs and tables have settled.

---

## 16. Specific Data Model Recommendations

### `flickr_accounts`

Add or confirm:

```text
id
nsid
username
fullname
connection_key
token_payload encrypted nullable
token_valid boolean
is_active boolean
connected_at
disconnected_at nullable
last_authenticated_at
timestamps
UNIQUE(nsid) // only if single-tenant
UNIQUE(connection_key)
```

If multi-user later:

```text
UNIQUE(user_id, nsid)
```

### `stored_files`

Prefer local file cache only:

```text
id
flickr_photo_id
owner_nsid
variant
local_disk
local_path
original_filename
mime_type
byte_size
checksum
status
downloaded_at
last_error
timestamps

UNIQUE(flickr_photo_id, variant)
```

### `storage_uploads`

Separate upload state:

```text
id
stored_file_id
storage_account_id
remote_file_id
remote_path
remote_etag
status
uploaded_at
last_error
timestamps

UNIQUE(stored_file_id, storage_account_id)
```

### `transfer_batches` / `transfer_items`

Strongly recommended if the UI will show progress:

```text
transfer_batches:
  id
  type
  flickr_account_id
  subject_nsid nullable
  storage_account_id nullable
  status
  total_count
  completed_count
  failed_count
  skipped_count
  started_at
  finished_at

transfer_items:
  id
  transfer_batch_id
  flickr_photo_id
  status
  attempts
  last_error
```

---

## 17. Conflicts or Ambiguities to Resolve

### Account-scoped download vs contact-crawled photos

The plan says account Download downloads every `xflickr_photos` row owned by this account.

But contact crawls produce photos owned by contacts. Are those excluded from account Download? It sounds like yes.

If so, define “account download” as:

```text
download photos where subject_nsid = account.nsid
```

not:

```text
all photos visible through account connection
```

This matters because an authenticated account may crawl many contacts.

### Contact has Crawl but no Download

The spec says no contact-level Download/Upload. Fine.

But then why crawl contact photos if they cannot be downloaded/uploaded? Maybe for browsing only. That is okay, but the UI should make this distinction clear.

Potential future conflict:

If users see contact photos in `/photos`, they may expect account Download to download them. Scope filters need to be obvious.

### Galleries and favorites may reference other owners’ photos

Galleries and favorites often contain photos owned by other users. The plan’s count model by `owner_nsid` may not correctly represent “galleries by this subject” or “favorites by this subject”.

Use subject-based relationships, not just owner-based photo rows.

---

## 18. Testing Strategy Missing

The plan needs a testing section.

Minimum tests I would add:

- OAuth callback stores encrypted token and creates crawler connection.
- Crawl trigger creates exactly one run/target per requested type.
- Duplicate crawl click does not create duplicate active runs.
- Contact crawl uses discovering account token and contact subject NSID.
- Download job is idempotent under duplicate dispatch.
- Upload job is idempotent per storage account.
- `stored_files` uniqueness works under concurrency.
- Favorites distinguish subject NSID from photo owner NSID.
- Disconnect prevents new jobs from using the token.
- API/logging redacts secrets.

Also add integration tests against fake Flickr SDK responses, not live Flickr.

---

## 19. Security and Privacy Issues

Potentially sensitive data:

- Flickr OAuth tokens
- Cloud storage tokens
- private/friends/family photo metadata
- downloaded photo files
- API logs
- event payloads
- storage paths

Recommendations:

- Encrypt token payloads.
- Never log token payloads or auth headers.
- Add log redaction at package level.
- Store downloaded files outside public web root.
- Use signed temporary URLs for local previews if needed.
- Add retention/delete behavior for disconnected accounts.
- Consider a “delete local files” action.

---

## 20. Revised Decisions I Would Make Now

Before writing code, I would lock these down:

1. Use `connection_key` as an internal stable key, not necessarily NSID, unless this is intentionally single-tenant.
2. Add `subject_nsid` consistently to crawler records where a crawl targets a user.
3. Separate local file cache from cloud upload state.
4. Add durable transfer batch/item tables if progress UI matters.
5. Define favorites as a relationship from subject user to photo, not as owner-owned content.
6. Expose crawler read repositories/query APIs instead of having XFlickr query package tables directly.
7. Make account scope explicit in `/contacts`, `/photos`, `/photosets`, `/galleries`.
8. Add duplicate-run prevention and job idempotency from the start.
9. Treat Horizon as operational visibility, not app state.
10. Reconsider PHP 8.5+ unless every package is already verified.

---

## Bottom Line

The plan is viable, but it currently underestimates the complexity introduced by crawling “another user’s data through my connection.” That one requirement affects table keys, counts, deduplication, permissions, UI filters, favorites, galleries, and download semantics.

The most important correction is to model three identities separately everywhere:

```text
connection_key  = which authenticated token was used
subject_nsid    = whose resource/list was crawled
owner_nsid      = who owns the photo/content
```

Once that is clean, the rest of the system becomes much easier to reason about.