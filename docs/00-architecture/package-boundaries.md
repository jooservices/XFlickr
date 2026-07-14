# Package boundaries

XFlickr is a **host application**. Business domains live in `Modules/` (`nwidart/laravel-modules`). The crawl **engine** lives in-repo as **`Modules/Crawler`** (namespace `Modules\Crawler\*`), not as a separate Packagist package.

Per-module **scope / purpose / features:** [Modules catalog](modules.md).

## Ownership table

| Concern | Owner | Location |
|---|---|---|
| Flickr OAuth connect/disconnect | **Flickr** module | `Modules/Flickr` (controllers, OAuth service, events) |
| App credentials (API keys) | **Settings** + laravel-config | Settings UI, MongoDB |
| Crawl runs, targets, fetchers | **Crawler** module | `Modules/Crawler` (`Modules\Crawler\*`), `xflickr_crawl_*` tables |
| Catalog data (photos, contacts) | **Crawler** module | `xflickr_photos`, `xflickr_contacts`, etc. |
| Catalog UI / API | **Catalog** module | `Modules/Catalog` |
| Contacts / graph / full-pass | **Contacts** module | `Modules/Contacts` |
| Spider frontier | **Spider** module | `Modules/Spider` |
| Local download tracking | **Transfer** module | `Modules/Transfer` (`stored_files`, `DownloadPhotoJob`) |
| Cloud upload tracking | **Transfer** + **Storage** | `Modules/Transfer` + `Modules/Storage` |
| Storage browse/sync / provider HTTP | **Storage** module | `Modules/Storage` |
| Operations dashboard | **Operations** module | `Modules/Operations` |
| Rate limiting | **Crawler** module | Redis-backed limiter |
| Connection Flickr REST clients | **Crawler** module | `FlickrClientFactory` (+ force-auth); peers must not invent clients |
| Shared crawler reads | Host `app/` | `app/Repositories/Crawler/*` (follow-up: move into Crawler) |

## Planned: Flickr client layering

Target: `jooservices/flickr` (SDK) → **Crawler** `FlickrClientFactory` (authenticated true/false) → domain modules via Crawler / Flickr product services. Do **not** put ClientFactory in the Flickr module (would break the Crawler leaf). Tracked as [N-16](../05-maintenance/BACKLOG.md); detail: [flickr-client-factory-layering.md](../05-maintenance/flickr-client-factory-layering.md).

## Bridge: connection_key

Connected Flickr accounts bridge to the crawler via `connection_key` (typically the account NSID). Public IDs and presenters live under `Modules/Flickr/Support`.

## What the app must not do

- Reimplement crawl fetchers — call `FlickrService::connection(...)` from the Crawler module.
- Import crawler `Fetchers\` or `Jobs\` from Http controllers — use services / the facade.
- Query crawler tables with ad-hoc SQL in controllers — use `app/Repositories/Crawler/*` repositories.
- Auto-dispatch crawls on a schedule beyond draining existing targets (`xflickr:dispatch`).
- Import peer `Modules\*` from inside `Modules/Crawler` (Crawler is a DAG leaf).

## Config

1. Module defaults: `Modules/Crawler/config/xflickr-crawler.php`
2. Provider `mergeConfigFrom` that path
3. Host root `config/xflickr-crawler.php` remains the committed **override** (not a duplicate to delete)

## Engine documentation

Crawler docs that formerly shipped under the Packagist package now live alongside the module source / historical sibling repo. Host integration notes: keep Horizon `xflickr` supervisor and `routes/console.php` `xflickr:dispatch` schedule.
