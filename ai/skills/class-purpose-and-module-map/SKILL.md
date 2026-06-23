---
name: class-purpose-and-module-map
description: Which XFlickr module owns what — Services, Repositories, Jobs, Pages.
---

# Skill: class-purpose-and-module-map

## Purpose

Route changes to the correct module and avoid cross-layer leaks.

## Module map

| Module | Path | Owns |
|---|---|---|
| Flickr OAuth | `app/Http/Controllers/FlickrAuth*` | Connect, disconnect, activate |
| Flickr accounts | `FlickrAccountController`, `FlickrContactController` | Crawl triggers, contact lists |
| Catalog | `CatalogController`, `Services/Catalog/` | Photo/photoset/gallery browsing |
| Transfers | `PhotoDownload/Upload*`, `Services/Flickr/Photo*Service`, `Jobs/Download*`, `Jobs/Upload*` | Download/upload pipelines |
| Storage | `Storage*`, `Services/Storage/`, `Enums/StorageDriver` | Cloud providers, browse, sync |
| Settings | `SettingsController`, `RuntimeConfig*`, `*ProfileService` | Credentials, runtime config |
| Crawler reads | `app/Repositories/Crawler/` | Read-only queries on crawler tables |
| App persistence | `app/Repositories/` (non-Crawler) | stored_files, transfers, storage |
| Frontend | `resources/js/Pages/`, `Components/` | Inertia UI |

## Rules

- Do not put business logic in Controllers or Jobs.
- Crawler table writes go through the crawler package API, not raw SQL in app Services.
- New API routes: `routes/api.php` + `app/Http/Controllers/Api/`.

## Related skills

- `form-request-service-repository`
- `crawler-pipeline-integrity`
