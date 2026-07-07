# Product and architecture constraints

Durable rules for XFlickr. For live gap tracking see [BACKLOG.md](BACKLOG.md).

## Spider mode

- **Opt-in only** — `spider.enabled` defaults to `false` in curated runtime config (Settings → General).
- **Depth and caps** — `spider.max_depth`, `spider.max_new_contacts_per_run`, `spider.max_contacts_total` bound expansion.
- **Depth > 0** — discovers **public** contacts only (`getPublicList`); requires `jooservices/xflickr-crawler` ^1.3.0.
- **Rate limits** — spider expansion respects Flickr API quota and the global crawl pause (`xflickr.global_pause`).
- **Scheduler** — `xflickr:spider:expand` drains the app frontier; `xflickr:dispatch` drains crawler targets. Frontier reconciliation uses `ContactsCrawlCompleted`. See [Spider mode](../02-user-guide/spider-mode.md).

Manual per-contact and per-account crawls remain supported alongside spider runs.

## Flickr API quota

Rate limits are a hard constraint. Bulk operations and spider mode need operator awareness; dashboard quota meters reflect crawler throttle state.

## Two Docker stacks

Dev data (`docker-compose.dev.yml`, project `xflickr-dev`) is precious. Agents must use `bash scripts/test.sh` only — never touch dev. See [Docker safety](docker-safety.md).
