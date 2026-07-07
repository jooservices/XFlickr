# xflickr-crawler — spider extensions

Spider mode in XFlickr v1.2.0+ uses **`jooservices/xflickr-crawler` ^1.3.0** for subject-scoped contact discovery and completion events.

## Shipped in crawler 1.3.0

### Subject-scoped contacts crawl

```php
FlickrConnection::contacts(?string $subjectNsid = null, ?int $spiderRunId = null, ?int $spiderFrontierItemId = null): CrawlRun
```

| `subjectNsid` | Flickr API | Purpose |
|---------------|------------|---------|
| `null` | `flickr.contacts.getList` | Owner contact list (seed) |
| contact NSID | `flickr.contacts.getPublicList` | Public contacts of that contact (BFS depth + 1) |

### `xflickr_subject_contacts` table

Stores `(connection_key, subject_nsid, contact_nsid)` with optional `crawl_run_id` for per-run discovery.

### `ContactsCrawlCompleted` event

Fired when a contacts crawl run finishes. Payload:

- `connectionKey`, `subjectNsid` (null for owner seed)
- `crawlRunId`, `discoveredContactNsids`
- `spiderRunId`, `spiderFrontierItemId` (passthrough from crawl run)

XFlickr listens via `HandleContactsCrawlCompleted` → `SpiderPlannerService::handleContactsCrawlCompleted()`.

### Spider context on crawl runs

Columns on `xflickr_crawl_runs`:

- `spider_run_id` nullable
- `spider_frontier_item_id` nullable

## App contract

XFlickr `SpiderPlannerService`:

- **Start** — create run, dispatch owner `contacts()` with `spiderRunId`; no synchronous DB seed
- **Expand** — `photos($nsid)` + `contacts($nsid)` with spider context; mark frontier `Queued`
- **On event** — seed depth 0 or enqueue depth + 1; mark frontier `Crawled`
- **Complete** — when no `Pending` or `Queued` frontier items remain

## Optional future work (crawler package)

| Item | Purpose |
|------|---------|
| `depth` / `origin_nsid` on `xflickr_crawl_targets` | Finer-grained target metadata |
| Token encryption hardening | See BACKLOG N-08 |

## Version bump

Release as `xflickr-crawler` **1.3.0**. XFlickr `composer.json` constraint: `^1.3.0`.

No breaking changes to existing manual crawl flows.
