# Settings

Path: `/settings`

Configure runtime options (crawl pause, spider, discovery, and other curated keys).

Flickr and storage **connect / app credentials** live under **[Connections](connections.md)** (`/connections`). Visiting `/settings?tab=flickr` or `/settings?tab=storage` redirects there.

## General

- Runtime config browser (collapsible groups, search, expert / technical-key toggles, hints)
- Global crawl pause and other curated keys (laravel-config backed)
- Custom (raw) config keys

## Global crawl pause

When enabled, a banner appears across the app and crawl jobs will not dispatch until pause is cleared. Starting a crawl (web or API) returns an error instead of creating a run; crawl menus disable crawl actions while pause is on.

Toggle pause from the top navbar (**Pause** / **Resume**) or under Settings → General → **Global crawl pause** (`xflickr.global_pause`).

Use the header **Spider** control for spider mode enable/caps (or Settings → General → Spider).

## Transfers

Under Settings → General → **Transfer / Downloads**, **Photo download HTTP timeout (seconds)** (`xflickr_download.timeout_seconds`) controls how long download jobs wait for Flickr original HTTP responses (30–900 seconds). Increase for large originals on slow links. The `.env` default (`XFLICKR_DOWNLOAD_TIMEOUT` via `config/xflickr.php`) applies when no runtime override is stored.

## Credential storage

| Type | Stored in |
|---|---|
| Flickr API keys | MongoDB (laravel-config) — managed on Connections → Flickr → Apps |
| Storage OAuth clients | MongoDB — Connections → Storage → Apps |
| Connected account tokens | MySQL (encrypted) — Connections → Accounts |

## Related

- [First run](../01-getting-started/first-run.md)
- [Connections](connections.md)
