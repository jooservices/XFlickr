# Tech stack

| Layer | Technology | Version |
|---|---|---|
| Backend | Laravel | 12 |
| PHP | PHP | 8.5+ |
| Frontend | React | 19 |
| SPA bridge | Inertia | 3 |
| Language (FE) | TypeScript | 5.8+ |
| Styling | Tailwind CSS | 4 |
| Build | Vite | 8 |
| Primary DB | MySQL | 8 |
| Queue / rate limit | Redis | 7 |
| Config / logging / events | MongoDB | 7 |
| Queue dashboard | Laravel Horizon | 5 |
| OAuth (storage) | Laravel Socialite | 5 |

## Key packages

| Package | Role |
|---|---|
| `jooservices/xflickr-crawler` | Crawling engine, rate limits, crawl jobs |
| `jooservices/flickr` | Flickr API SDK (OAuth 1.0a) |
| `jooservices/laravel-config` | App credentials in MongoDB |
| `jooservices/laravel-repository` | Repository base classes |
| `jooservices/laravel-logging` | Structured audit logs |
| `jooservices/laravel-events` | Domain event sourcing |
| `league/flysystem-aws-s3-v3` | Cloudflare R2 |
| `masbug/flysystem-google-drive-ext` | Google Drive |
| `justus/flysystem-onedrive` | OneDrive |

## Data stores

| Store | Contents |
|---|---|
| MySQL `xflickr` | Accounts, stored files, transfers, storage metadata |
| MySQL `xflickr_*` (crawler) | Connections, contacts, photos, crawl runs |
| MongoDB `xflickr` | Flickr/storage app credentials, config entries, event logs |
| Redis | Queues, Horizon meta, crawler rate limiting |
| Local disk | Downloaded photo files under `storage/app/private/flickr/` |
