---
name: crawler-pipeline-integrity
description: Manual crawl triggers, rate limits, and xflickr-crawler package boundaries.
---

# Skill: crawler-pipeline-integrity

## Purpose

Protect crawl pipeline invariants: manual triggers, rate limits, and package ownership.

## Rules

- **Manual only:** Crawl targets are created only when a user clicks Crawl — never add auto-spidering.
- **Scheduler drains only:** `xflickr:dispatch` processes existing targets; it does not create new ones.
- **Use package API:** Call `FlickrService::connection(...)` from `jooservices/xflickr-crawler` — do not reimplement fetchers.
- **Respect rate limits:** Check Dashboard quota meters; honor global crawl pause in Settings.
- **Connection key:** Use account NSID as `connection_key` when bridging to crawler.

## Crawl types

contacts, photos, photosets, galleries, favorites

## App responsibilities

- Controllers validate and trigger crawls via FormRequest + Service
- Read catalog via `app/Repositories/Crawler/*`
- Display status via `CrawlStatusQueryService`, Operations page

## Package responsibilities

- Fetchers, jobs, rate limiting, `xflickr_*` catalog tables

## Related skills

- `class-purpose-and-module-map`
- `queue-horizon-operations`
