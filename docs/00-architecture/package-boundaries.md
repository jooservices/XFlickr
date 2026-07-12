# Package boundaries

XFlickr is a **host application**. The crawling engine is a separate Composer package. Business domains live in `Modules/` (`nwidart/laravel-modules`).

## Ownership table

| Concern | Owner | Location |
|---|---|---|
| Flickr OAuth connect/disconnect | **Flickr** module | `Modules/Flickr` (controllers, OAuth service, events) |
| App credentials (API keys) | **Settings** + laravel-config | Settings UI, MongoDB |
| Crawl runs, targets, fetchers | `jooservices/xflickr-crawler` | Package jobs, `xflickr_crawl_*` tables |
| Catalog data (photos, contacts) | `jooservices/xflickr-crawler` | `xflickr_photos`, `xflickr_contacts`, etc. |
| Catalog UI / API | **Catalog** module | `Modules/Catalog` |
| Contacts / graph / full-pass | **Contacts** module | `Modules/Contacts` |
| Spider frontier | **Spider** module | `Modules/Spider` |
| Local download tracking | **Transfer** module | `Modules/Transfer` (`stored_files`, `DownloadPhotoJob`) |
| Cloud upload tracking | **Transfer** + **Storage** | `Modules/Transfer` + `Modules/Storage` |
| Storage browse/sync / provider HTTP | **Storage** module | `Modules/Storage` |
| Operations dashboard | **Operations** module | `Modules/Operations` |
| Rate limiting | `jooservices/xflickr-crawler` | Redis-backed limiter |
| Shared crawler reads | Host `app/` | `app/Repositories/Crawler/*` |

## Bridge: connection_key

Connected Flickr accounts bridge to the crawler via `connection_key` (typically the account NSID). Public IDs and presenters live under `Modules/Flickr/Support`.

## What the app must not do

- Reimplement crawl fetchers — call `FlickrService::connection(...)` from the crawler package.
- Query crawler tables with ad-hoc SQL in controllers — use `app/Repositories/Crawler/*` repositories.
- Auto-dispatch crawls on a schedule beyond draining existing targets.

## Package documentation

Crawler package docs ship in `vendor/jooservices/xflickr-crawler/docs/`:

- [Crawl types](https://github.com/jooservices/xflickr-crawler) — contacts, photos, photosets, galleries, favorites
- [Rate limiting](https://github.com/jooservices/xflickr-crawler) — quota and cooldown behavior

For host-app integration, see the crawler's `docs/01-getting-started/host-app-integration.md`.
