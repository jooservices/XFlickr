# Package boundaries

XFlickr is a **host application**. The crawling engine is a separate Composer package.

## Ownership table

| Concern | Owner | Location |
|---|---|---|
| Flickr OAuth connect/disconnect | XFlickr app | `FlickrAuthController`, `flickr_accounts` |
| App credentials (API keys) | XFlickr + laravel-config | Settings UI, MongoDB |
| Crawl runs, targets, fetchers | `jooservices/xflickr-crawler` | Package jobs, `xflickr_crawl_*` tables |
| Catalog data (photos, contacts) | `jooservices/xflickr-crawler` | `xflickr_photos`, `xflickr_contacts`, etc. |
| Local download tracking | XFlickr app | `stored_files`, `DownloadPhotoJob` |
| Cloud upload tracking | XFlickr app | `storage_uploads`, `UploadPhotoJob` |
| Transfer batch progress | XFlickr app | `transfer_batches`, `transfer_items` |
| Storage browse/sync | XFlickr app | `StorageBrowseService`, remote item tables |
| Rate limiting | `jooservices/xflickr-crawler` | Redis-backed limiter |

## Bridge: connection_key

Connected Flickr accounts bridge to the crawler via `connection_key` (typically the account NSID). The `FlickrAccountObserver` synchronizes credentials with crawler `Connection` records.

## What the app must not do

- Reimplement crawl fetchers — call `FlickrService::connection(...)` from the crawler package.
- Query crawler tables with ad-hoc SQL in controllers — use `app/Repositories/Crawler/*` repositories.
- Auto-dispatch crawls on a schedule beyond draining existing targets.

## Package documentation

Crawler package docs ship in `vendor/jooservices/xflickr-crawler/docs/`:

- [Crawl types](https://github.com/jooservices/xflickr-crawler) — contacts, photos, photosets, galleries, favorites
- [Rate limiting](https://github.com/jooservices/xflickr-crawler) — quota and cooldown behavior

For host-app integration, see the crawler's `docs/01-getting-started/host-app-integration.md`.
