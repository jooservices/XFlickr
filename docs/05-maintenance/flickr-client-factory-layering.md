# Flickr client factory layering (N-16)

**Status:** Implemented (2026-07-13).  
**Related:** `Modules/Crawler/Services/FlickrClientFactory`, `ForceAuthenticatedFlickrClient`, `FlickrClientFactoryLayeringTest`, audit 260713 C1 leaf.

## Target DAG

```
jooservices/flickr (SDK)
    ↓
Modules/Crawler — owns FlickrClientFactory (authenticated true|false, anonymousClient)
  + crawl engine (runs, targets, fetchers, jobs, connections, limiter)
    ↓
Modules/Flickr — account/product facade (OAuth UI, crawl triggers, health, sizes)
Modules/Contacts | Catalog | Spider | Transfer | Operations | Settings
```

## Ownership

| Layer | Owns |
|-------|------|
| **SDK** (`jooservices/flickr`) | HTTP/OAuth primitives, `FlickrFactory`, contracts |
| **Crawler** | How XFlickr builds clients from stored connections; force-auth / explicit `authenticated` flag; `anonymousClient()` for intentional anonymous probes; crawl execution; catalog tables |
| **Flickr module** | Connect accounts, activate/disconnect, operator Flickr UX, token health / size resolution *via* Crawler factory or Crawler services — **not** a second ClientFactory |
| **Peers** | Domain features; call **Crawler** (`FlickrService`, query repos) or **Flickr** (accounts) — do **not** inject `FlickrFactory` / ClientFactory ad hoc |

## Why ClientFactory stays in Crawler (not Flickr)

Crawler is a **DAG leaf**: it must not import peer `Modules\*`. Putting the factory in Flickr would force `Crawler → Flickr` and break the leaf.

## Why peers go through Crawler

Crawler is the XFlickr extension of the SDK for connection-backed API and crawling. Other modules that need Flickr REST for catalog/crawl/health ultimately need the same connection-aware client rules (including always authenticating connection clients). Prefer Crawler facades/services over raw SDK clients.

## Exceptions (keep explicit)

- **Pre-connection OAuth** (request/access token) in the Flickr module may still call `FlickrFactory` directly — no stored connection yet (`FlickrOAuthService`).
- **Intentional anonymous probes** (e.g. API audit) use `FlickrClientFactory::anonymousClient()`, never a connection factory client silently flipped to anonymous.

## Landed

1. `FlickrClientFactory::forConnection` / `makeClient` take `authenticated: bool` (default `true`); force-auth wrap only when true.
2. `anonymousClient(FlickrAppCredentialsDto)` for app-key-only probes (`FlickrApiAuditCommand`).
3. Allowlist guard: `Tests\Unit\Architecture\FlickrClientFactoryLayeringTest` — only Crawler factory + Flickr OAuth may `use FlickrFactory` in module `app/`.
4. Peers reach connection clients via Flickr module facade (`FlickrAccountsService` → size resolver / health), not ad hoc factories.
