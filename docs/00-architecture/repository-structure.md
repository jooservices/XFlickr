# Repository structure

Architecture diagrams (flows, queues, OAuth): [Architecture diagrams](architecture-diagrams.md).

```text
XFlickr/
├── app/                        # Host bootstrap only
│   ├── Http/
│   │   ├── Controllers/        # Empty base Controller
│   │   ├── Middleware/
│   │   └── Requests/           # Shared FormRequest base + cross-module traits
│   ├── Models/                 # User only
│   ├── Providers/              # App / Horizon / Repository / Event
│   ├── Repositories/Crawler/   # Shared crawler table reads
│   └── Support/                # Shared host helpers (config catalog, query sort, …)
├── Modules/                    # Business domains (nwidart/laravel-modules)
│   ├── Auth/
│   ├── Settings/
│   ├── Operations/
│   ├── Flickr/
│   ├── Contacts/
│   ├── Catalog/
│   ├── Spider/
│   ├── Transfer/
│   └── Storage/
│       # Each module: Http/, Services/, Repositories/, Models/,
│       # Jobs/, routes/{web,api}.php, optional Console/, Listeners/
├── config/
├── database/migrations/        # App-owned MySQL migrations
├── docs/
├── ai/skills/
├── resources/js/
│   ├── Components/             # ui/, macros/, layout/page-shell
│   ├── Pages/                  # Inertia pages (AppShell + PageShell)
│   ├── hooks/
│   └── lib/                    # apiClient, apiPaths, toast
├── routes/
│   ├── web.php                 # Stub — modules own web routes
│   ├── api.php                 # Stub — modules own /api/v1 routes
│   └── console.php             # Scheduler
├── scripts/
└── tests/                      # PHPUnit + Vitest + Playwright smokes
```

## Module ownership (controllers)

| Area | Module |
|---|---|
| Login / logout / register / password reset / optional admin seed / activate CLI | Auth (`AuthService`, `UserService`, `AdminUserSeeder`) |
| Flickr OAuth / accounts / crawl / rate-limit / token-health | Flickr |
| Contacts / annotations / contact-graph / full-pass | Contacts |
| Catalog photosets / photos / galleries / favorites | Catalog |
| Spider start/stop / status | Spider |
| Download / upload / stored-files / transfer progress | Transfer |
| Storage OAuth / browse / sync / delete | Storage |
| Settings / runtime config / app profiles | Settings |
| Dashboard / operations snapshot/stream | Operations |

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
