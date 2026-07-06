# Architecture diagrams

Visual reference for onboarding, design reviews, and presentations. Narrative detail for each pipeline lives in [Data flow](data-flow.md); ownership rules in [Package boundaries](package-boundaries.md).

**Terminology:** XFlickr uses **manual crawl** only. The scheduler drains crawl targets the user already queued — it does not discover new subjects or auto-spider the web.

---

## End-to-end operator journey

```mermaid
flowchart TB
  subgraph setup [1. Setup]
    S1[Settings: Flickr API key/secret]
    S2[Settings: storage OAuth apps or R2 keys]
  end

  subgraph connect [2. Connect]
    C1[Flickr OAuth 1.0a]
    C2[Storage OAuth or R2 connect]
  end

  subgraph index [3. Index — manual only]
    I1[User clicks Crawl on account or contact]
    I2[Crawler package queues fetch jobs]
    I3[Catalog rows in xflickr_* tables]
  end

  subgraph local [4. Local backup]
    L1[User clicks Download]
    L2[DownloadPhotoJob → local disk]
    L3[stored_files tracking]
  end

  subgraph cloud [5. Cloud backup]
    U1[User clicks Upload]
    U2[UploadPhotoJob → storage driver]
    U3[storage_uploads tracking]
    B1[Storage browse / sync / delete]
  end

  setup --> connect --> index --> local --> cloud
  U3 --> B1
```

| Step | User action | Primary outcome |
|---|---|---|
| Setup | Configure credentials in Settings | MongoDB app profiles (`xflickr_app.*`, `storage_app.*`) |
| Connect | Authorize Flickr and storage accounts | Crawler `Connection` + MySQL `storage_accounts` |
| Index | Crawl contacts, photos, photosets, galleries, favorites | Catalog metadata in crawler tables |
| Local backup | Download selected or account photos | Files under `storage/app/private/flickr/` |
| Cloud backup | Upload to Google Photos, Drive, OneDrive, or R2 | Remote copies + browse UI |

---

## System context

```mermaid
flowchart TB
  Browser[Browser — React 19 + Inertia]

  subgraph app [XFlickr app — Laravel 12]
    Web[Web + API controllers]
    Services[Services]
    Jobs[Horizon jobs]
  end

  subgraph pkg [jooservices/xflickr-crawler]
    CrawlerAPI[FlickrService API]
    Fetchers[Fetcher jobs]
    Limiter[Redis rate limiter]
  end

  Flickr[(Flickr API)]
  MySQL[(MySQL — accounts, transfers, storage)]
  Mongo[(MongoDB — app credentials, config)]
  Redis[(Redis — queues, rate limits)]
  Disk[(Local disk — downloaded photos)]
  Cloud[(Cloud storage providers)]

  Browser --> Web --> Services
  Services --> CrawlerAPI
  Services --> Jobs
  Fetchers --> Flickr
  Fetchers --> MySQL
  CrawlerAPI --> Limiter --> Redis
  Jobs --> Disk
  Jobs --> Cloud
  Services --> MySQL
  Services --> Mongo
  Jobs --> Redis
```

---

## Application layers

Backend code follows a single request lifecycle (see [Application standards](application-standards.md)):

```mermaid
flowchart TB
  HTTP[HTTP request]
  Controller[Controller — thin]
  FormRequest[FormRequest — validate + authorize]
  Service[Service — business logic]
  Repository[Repository — queries]
  Model[(Model / crawler tables)]

  HTTP --> Controller --> FormRequest --> Service --> Repository --> Model

  subgraph async [Background work]
    Job[Job — thin wrapper]
    Service2[Service method]
    Job --> Service2
  end

  Service -.-> Job
```

| Layer | Must not |
|---|---|
| Controller | Inline validation, direct `Model::query()` |
| Job | Own orchestration — delegate to Services |
| Service | Write crawler tables via raw SQL — use package API |

---

## Package boundaries

```mermaid
flowchart LR
  subgraph xflickr [XFlickr app]
    OAuth[Flickr + storage OAuth]
    DL[Download / upload tracking]
    TB[transfer_batches]
    SF[stored_files]
    SU[storage_uploads]
    Browse[Storage browse / sync]
  end

  subgraph crawler [jooservices/xflickr-crawler]
    Conn[xflickr_connections]
    Runs[xflickr_crawl_runs / targets]
    Cat[xflickr_photos, contacts, …]
    RL[Rate limiting]
  end

  OAuth -->|connection_key = NSID| Conn
  xflickr -->|read via Repositories/Crawler| Cat
  xflickr -->|FlickrService::connection| crawler
  Fetchers[Fetcher jobs] --> Cat
  Runs --> Fetchers
```

Bridge field: **`connection_key`** (typically the Flickr account NSID). App controllers pass crawler `Connection` models into Services; crawls call `FlickrCrawlService` → `FlickrService` from the package.

---

## Manual crawl pipeline

Nothing crawls until a user clicks **Crawl**. The scheduler only drains existing targets.

```mermaid
flowchart TB
  User[User clicks Crawl]
  Acc[FlickrAccountController or FlickrContactController]
  FR[CrawlFlickrAccountRequest / CrawlFlickrContactRequest]
  Svc[FlickrCrawlService]
  Pkg[FlickrService — xflickr-crawler]
  Run[(xflickr_crawl_runs)]
  Tgt[(xflickr_crawl_targets — pending)]
  Sched[Scheduler: xflickr:dispatch every minute]
  Q[Redis queue xflickr]
  Fetch[Fetcher jobs — package]
  API[Flickr API]
  DB[(xflickr_photos, contacts, …)]

  User --> Acc --> FR --> Svc --> Pkg
  Pkg --> Run
  Pkg --> Tgt
  Sched --> Tgt
  Tgt --> Q --> Fetch
  Fetch --> API
  Fetch --> DB
  Fetch -->|next page| Tgt
```

**Crawl types:** contacts, photos, photosets, galleries, favorites.

**Not auto-spidering:** `xflickr:dispatch` does not create targets — it locks and dispatches targets already written by a user-triggered crawl.

Monitor progress: [Operations](../02-user-guide/operations.md), [Dashboard](../02-user-guide/dashboard.md), Horizon.

---

## Download pipeline

```mermaid
flowchart TB
  User[User clicks Download]
  Ctrl[PhotoDownloadController]
  Req[QueuePhotoDownloadRequest]
  Svc[PhotoDownloadService]
  Batch[(transfer_batches + transfer_items)]
  Job[DownloadPhotoJob]
  Exec[PhotoDownloadExecutionService]
  Resolver[FlickrPhotoSizeResolver]
  Flickr[Flickr API — getSizes if needed]
  Disk[(Local disk)]
  SF[(stored_files)]

  User --> Ctrl --> Req --> Svc --> Batch --> Job --> Exec
  Exec --> Resolver --> Flickr
  Exec --> Disk
  Exec --> SF
```

**Dedup:** skip when `stored_files.local_downloaded_at` is set for the `flickr_photo_id`.

**Queue:** `xflickr-downloads` (dedicated Horizon supervisor).

---

## Upload pipeline

```mermaid
flowchart TB
  User[User clicks Upload]
  Ctrl[PhotoUploadController]
  Req[QueuePhotoUploadRequest]
  Svc[PhotoUploadService]
  Batch[(transfer_batches)]
  Job[UploadPhotoJob]
  Exec[PhotoUploadExecutionService]
  Local{Local file ready?}
  DL[DownloadPhotoJob — ensure local]
  Lock[Cache lock per storage_account_id]
  Driver[Flysystem storage driver]
  Cloud[(Google Photos / Drive / OneDrive / R2)]
  SU[(storage_uploads)]

  User --> Ctrl --> Req --> Svc --> Batch --> Job --> Exec
  Exec --> Local
  Local -->|no| DL
  Local -->|yes| Lock --> Driver --> Cloud
  Exec --> SU
```

**Dedup:** skip when `storage_uploads` exists for `(flickr_photo_id, storage_account_id)`.

**Concurrency:** `xflickr-uploads` supervisor runs with limited `maxProcesses` to avoid hammering cloud APIs.

---

## Storage browse and sync

After uploads, operators browse remote content under `/storages/*`.

```mermaid
flowchart TB
  UI[Storage Browse page]
  API[Api/StorageBrowseController]
  Browse[StorageBrowseService]
  Sync[StorageBrowseSyncService]
  Reg[StorageDriverRegistry]
  Drv[Provider browse driver]
  Prov[(Remote provider API)]
  Local[(MySQL remote item tables)]

  UI -->|GET browse| API --> Browse --> Reg --> Drv --> Prov
  UI -->|POST sync| API --> Sync --> Drv --> Prov
  Sync --> Local
  UI -->|GET download / POST delete| API
```

| Provider | Browse scope |
|---|---|
| Google Photos | App-created uploads only |
| Google Drive | App-created files |
| OneDrive | Connected account |
| Cloudflare R2 | Configured bucket |

Credentials: OAuth tokens in MySQL `storage_accounts.credentials` (encrypted); R2 uses API keys from Settings (MongoDB profile).

---

## Flickr OAuth and connection sync

```mermaid
sequenceDiagram
  actor User
  participant Settings
  participant Auth as FlickrAuthController
  participant OAuth as FlickrOAuthService
  participant Flickr as Flickr API
  participant Pkg as FlickrService connections

  User->>Settings: Connect Flickr
  Settings->>Auth: GET /flickr/oauth
  Auth->>OAuth: begin(appProfile)
  OAuth->>Flickr: requestToken
  Auth->>User: Redirect to Flickr authorize
  User->>Flickr: Approve
  Flickr->>Auth: Callback with verifier
  Auth->>OAuth: complete(...)
  OAuth->>Flickr: accessToken
  OAuth->>Pkg: register(connectionKey, tokenPayload)
  Pkg-->>User: Redirect to /flickr/accounts
```

App credentials (`xflickr_app.*`) live in MongoDB via laravel-config. Connected tokens are stored on crawler `Connection` records (`token_payload`), keyed by NSID.

---

## Storage connect

```mermaid
flowchart TB
  subgraph oauth [OAuth providers]
    S1[Settings: storage app credentials]
    S2[StorageAuthController redirect]
    S3[Provider consent screen]
    S4[StorageOAuthService callback]
    S5[storage_accounts row]
  end

  subgraph r2 [Cloudflare R2]
    R1[Settings: endpoint, bucket, keys]
    R2[StorageAuthController connectR2]
    R3[StorageR2ConnectionVerifier]
    R4[storage_accounts row]
  end

  S1 --> S2 --> S3 --> S4 --> S5
  R1 --> R2 --> R3 --> R4
```

---

## Queue and Horizon topology

```mermaid
flowchart TB
  Sched[Laravel scheduler — routes/console.php]
  Cmd[xflickr:dispatch]
  H[Horizon master]

  subgraph sup1 [supervisor-1]
    Q0[default]
    Q1[xflickr — crawl fetchers]
  end

  subgraph supDL [supervisor-downloads]
    Q2[xflickr-downloads]
  end

  subgraph supUL [supervisor-uploads]
    Q3[xflickr-uploads]
  end

  Redis[(Redis)]
  Sched --> Cmd --> Q1
  H --> sup1
  H --> supDL
  H --> supUL
  sup1 --> Redis
  supDL --> Redis
  supUL --> Redis
```

| Queue | Typical jobs | Notes |
|---|---|---|
| `xflickr` | Crawler fetcher jobs | Drained by `xflickr:dispatch` |
| `xflickr-downloads` | `DownloadPhotoJob` | Longer timeout (180s) |
| `xflickr-uploads` | `UploadPhotoJob` | Limited concurrency; lock per storage account |

Dashboard: `/horizon`. See [Horizon](../03-operations/horizon.md).

---

## Data stores

```mermaid
flowchart LR
  subgraph mysql [MySQL xflickr]
    Conn[xflickr_connections — Flickr accounts]
    SF[stored_files]
    TB[transfer_batches / items]
    SA[storage_accounts]
    SU[storage_uploads]
    XR[xflickr_* crawler catalog]
  end

  subgraph mongo [MongoDB xflickr]
    XC[xflickr_app.* credentials]
    SC[storage_app.* credentials]
    EV[events / audit via packages]
  end

  subgraph redis [Redis]
    QU[Horizon queues]
    RL[Crawler rate limits]
  end

  subgraph files [Local filesystem]
    PH[storage/app/private/flickr/]
  end
```

| Store | Examples |
|---|---|
| MySQL | Transfers, storage metadata, crawler catalog |
| MongoDB | Flickr/storage **app** credentials (not user tokens) |
| Redis | Job queues, API quota counters |
| Local disk | Downloaded photo bytes |

---

## Module map (code layout)

Quick reference when navigating the repo — full tree in [Repository structure](repository-structure.md).

```mermaid
flowchart TB
  subgraph fe [Frontend — resources/js]
    Pages[Pages/]
    Comp[Components/]
    Hooks[hooks/]
  end

  subgraph be [Backend — app/]
    Flickr[Flickr OAuth + crawl triggers]
    Catalog[Catalog browse]
    Transfer[Download + upload]
    Storage[Storage OAuth + browse]
    CrawlerRepo[Repositories/Crawler — read only]
  end

  Pages --> Flickr
  Pages --> Catalog
  Pages --> Transfer
  Pages --> Storage
  Catalog --> CrawlerRepo
  Transfer --> CrawlerRepo
```

---

## Related documentation

| Topic | Document |
|---|---|
| Pipeline step-by-step | [Data flow](data-flow.md) |
| App vs crawler ownership | [Package boundaries](package-boundaries.md) |
| Folder and controller map | [Repository structure](repository-structure.md) |
| Backend layering rules | [Application standards](application-standards.md) |
| Stack versions | [Tech stack](tech-stack.md) |
| Operator UI | [User guide](../02-user-guide/dashboard.md) |
| Crawler package | `vendor/jooservices/xflickr-crawler/docs/` |
