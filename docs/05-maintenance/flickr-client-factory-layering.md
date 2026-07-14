# Flickr client factory layering (planned)

**Status:** Backlog ([N-16](BACKLOG.md)) — decide + implement soon; not started.  
**Related:** `Modules/Crawler/Services/FlickrClientFactory`, `ForceAuthenticatedFlickrClient`, audit 260713 C1 leaf.

## Target DAG

```
jooservices/flickr (SDK)
    ↓
Modules/Crawler — owns FlickrClientFactory (authenticated true|false)
  + crawl engine (runs, targets, fetchers, jobs, connections, limiter)
    ↓
Modules/Flickr — account/product facade (OAuth UI, crawl triggers, health, sizes)
Modules/Contacts | Catalog | Spider | Transfer | Operations | Settings
```

## Ownership

| Layer | Owns |
|-------|------|
| **SDK** (`jooservices/flickr`) | HTTP/OAuth primitives, `FlickrFactory`, contracts |
| **Crawler** | How XFlickr builds clients from stored connections; force-auth / explicit `authenticated` flag; crawl execution; catalog tables |
| **Flickr module** | Connect accounts, activate/disconnect, operator Flickr UX, token health / size resolution *via* Crawler factory or Crawler services — **not** a second ClientFactory |
| **Peers** | Domain features; call **Crawler** (`FlickrService`, query repos) or **Flickr** (accounts) — do **not** inject `FlickrFactory` / ClientFactory ad hoc |

## Why ClientFactory stays in Crawler (not Flickr)

Crawler is a **DAG leaf**: it must not import peer `Modules\*`. Putting the factory in Flickr would force `Crawler → Flickr` and break the leaf.

## Why peers go through Crawler

Crawler is the XFlickr extension of the SDK for connection-backed API and crawling. Other modules that need Flickr REST for catalog/crawl/health ultimately need the same connection-aware client rules (including always authenticating connection clients). Prefer Crawler facades/services over raw SDK clients.

## Exceptions (keep explicit)

- **Pre-connection OAuth** (request/access token) in the Flickr module may still call `FlickrFactory` directly — no stored connection yet.
- **Intentional anonymous probes** (e.g. API audit) must use a plain SDK client with `authenticated: false`, never a connection factory client silently flipped to anonymous.

## Implementation sketch (when scheduled)

1. Expand `FlickrClientFactory` with an explicit `authenticated: bool` (default `true` for `forConnection`); fold/replace decorator as needed.
2. Prefer wrapping getSizes / test.login behind Crawler or Flickr services so Contacts/Transfer never inject the factory.
3. Document the allowlist: only Crawler + Flickr may construct connection clients; everyone else uses `FlickrService` / module facades (A6).
4. Guard test: no new `FlickrFactory::make` outside Crawler + approved Flickr OAuth/audit paths.
