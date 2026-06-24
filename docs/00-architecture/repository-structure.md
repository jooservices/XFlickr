# Repository structure

Architecture diagrams (flows, queues, OAuth): [Architecture diagrams](architecture-diagrams.md).

```text
XFlickr/
├── app/
│   ├── Enums/              # StorageDriver, crawl types
│   ├── Http/
│   │   ├── Controllers/    # Web + API controllers (thin)
│   │   └── Requests/       # FormRequest validation
│   ├── Jobs/               # Queue jobs (delegate to Services)
│   ├── Models/             # Eloquent models (app-owned tables)
│   ├── Providers/          # Service providers
│   ├── Repositories/       # Persistence layer
│   │   └── Crawler/        # Read models for xflickr-crawler tables
│   └── Services/           # Business logic
│       ├── Catalog/
│       ├── Crawl/
│       ├── Flickr/
│       ├── Storage/
│       └── Transfer/
├── config/                 # Laravel + horizon + package configs
├── database/migrations/    # App-owned MySQL migrations
├── docs/                   # Numbered documentation (00–05)
├── ai/skills/              # Canonical AI skills
├── resources/js/
│   ├── Components/         # Shared React components
│   ├── Pages/              # Inertia page components
│   ├── hooks/              # React hooks
│   └── lib/                # Utilities
├── routes/
│   ├── web.php             # Inertia routes
│   ├── api.php             # JSON API routes
│   └── console.php         # Scheduler
├── scripts/                # Docker and deploy scripts
└── tests/                  # PHPUnit feature + unit tests
```

## Controller grouping

| Area | Controllers |
|---|---|
| Flickr OAuth | `FlickrAuthController`, `FlickrAccountController`, `FlickrContactController` |
| Catalog | `CatalogController`, `Api\CatalogController` |
| Transfers | `PhotoDownloadController`, `PhotoUploadController` |
| Storage | `StorageAuthController`, `StorageBrowseController`, `Api/StorageBrowseController` |
| Settings | `SettingsController`, `RuntimeConfigController`, profile controllers |
| Monitoring | `DashboardController`, `CrawlOperationsController`, API status controllers |

## Frontend pages

| Path | Page component |
|---|---|
| `/dashboard` | `Pages/Dashboard.tsx` |
| `/contacts` | `Pages/Contacts/Index.tsx` |
| `/flickr/accounts` | `Pages/Flickr/Index.tsx` |
| `/photos`, `/photosets`, `/galleries`, `/favorites` | `Pages/Catalog/*.tsx` |
| `/storages/*` | `Pages/Storage/Browse.tsx` |
| `/settings` | `Pages/Settings/Index.tsx` |
| `/crawl/operations` | `Pages/Crawl/Operations.tsx` |
