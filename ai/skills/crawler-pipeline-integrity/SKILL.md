---
name: crawler-pipeline-integrity
description: Manual and spider crawl triggers, rate limits, and xflickr-crawler package boundaries.
---

# Skill: crawler-pipeline-integrity

## Purpose

Protect crawl pipeline invariants: user-initiated and opt-in spider crawls, rate limits, and package ownership.

## Rules

- **Manual crawls:** Crawl targets are created when a user clicks Crawl — unchanged.
- **Spider mode (opt-in):** Only when runtime config `spider.enabled` is true (Settings → General, MongoDB via laravel-config). Bounded by `spider.max_depth`, `spider.max_new_contacts_per_run`, `spider.max_contacts_total`. Start/stop from Operations UI; expansion via `xflickr:spider:expand`.
- **Scheduler drains only:** `xflickr:dispatch` processes existing crawler targets; it does not create manual targets.
- **Use package API:** Call `FlickrService::connection(...)` from `jooservices/xflickr-crawler` — do not reimplement fetchers.
- **Respect rate limits:** Check Dashboard quota meters; honor global crawl pause in Settings.
- **Connection key:** Use account NSID as `connection_key` when bridging to crawler.

## Crawl types

contacts, photos, photosets, galleries, favorites

## App responsibilities

- Controllers validate and trigger crawls via FormRequest + Service
- `SpiderPlannerService` manages BFS frontier (`spider_runs`, `spider_frontier_items`)
- Read catalog via `app/Repositories/Crawler/*`
- Display status via `CrawlStatusQueryService`, Operations page

## Package responsibilities

- Fetchers, jobs, rate limiting, `xflickr_*` catalog tables
- Planned: depth/origin on crawl targets (see `docs/04-development/xflickr-crawler-spider-extensions.md`)

## Related skills

- `class-purpose-and-module-map`
- `queue-horizon-operations`
