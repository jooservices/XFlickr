# Data flow

For presentation-ready diagrams (end-to-end journey, OAuth, queues, storage browse), see [Architecture diagrams](architecture-diagrams.md).

## Crawl pipeline

```mermaid
flowchart LR
  User[User clicks Crawl] --> Controller[FlickrAccountController]
  Controller --> CrawlerPkg[jooservices/xflickr-crawler]
  CrawlerPkg --> Targets[CrawlTargets queue]
  Scheduler[xflickr:dispatch] --> Targets
  Targets --> Fetchers[Fetcher jobs]
  Fetchers --> CatalogDB[(xflickr_photos etc.)]
```

1. User triggers crawl from Flickr account or contact page.
2. Controller validates input via FormRequest and calls crawler package API.
3. Crawler creates `CrawlRun` and `CrawlTarget` records.
4. Scheduler command `xflickr:dispatch` (every minute) drains pending targets.
5. Fetcher jobs call Flickr API and persist catalog rows.

**Nothing crawls automatically** — targets exist only after user action.

## Download pipeline

```mermaid
flowchart LR
  User[User clicks Download] --> DLController[PhotoDownloadController]
  DLController --> DLService[PhotoDownloadService]
  DLService --> Batch[TransferBatch]
  Batch --> DLJob[DownloadPhotoJob]
  DLJob --> Disk[(Local disk)]
  DLJob --> StoredFiles[(stored_files)]
```

- Deduplication: skip photos already in `stored_files` with `local_downloaded_at`.
- `FlickrPhotoSizeResolver` resolves direct download URLs from Flickr.

## Upload pipeline

```mermaid
flowchart LR
  User[User clicks Upload] --> ULController[PhotoUploadController]
  ULController --> ULService[PhotoUploadService]
  ULService --> ULJob[UploadPhotoJob]
  ULJob --> EnsureLocal[Download if needed]
  ULJob --> Cloud[Storage driver]
  ULJob --> Uploads[(storage_uploads)]
```

- Upload queue runs with limited concurrency (Horizon supervisor).
- Deduplication per `(flickr_photo_id, storage_account_id)`.

## Settings and credentials

| Credential type | Storage |
|---|---|
| Flickr API key/secret | MongoDB via laravel-config (`xflickr_app.*`) |
| Storage OAuth clients | MongoDB (`storage_app.*`) |
| Connected Flickr tokens | MySQL `flickr_accounts.token_payload` (encrypted) |
| Connected storage tokens | MySQL `storage_accounts.credentials` (encrypted) |
