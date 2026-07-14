# Operations

Path: `/operations` (legacy `/crawl/operations` redirects here)

Live process console for crawls, transfers, and platform health. Prefer this page when diagnosing stuck jobs; use [Dashboard](dashboard.md) for catalog growth KPIs.

Query tabs: `?panel=overview` (default) · `crawl` · `transfers`. Live updates via JSON polling every 5 seconds.

## Overview

- Activity metrics: running crawls, pending targets, active transfers, failed transfers (24h), accounts in rate-limit cooldown
- Session activity chart (running / pending / transfers) built from each poll while the page stays open
- Service probes: MySQL, Redis, MongoDB (`ok` / latency)
- Databases panel with 24h size/connections chart (same samples as Dashboard; accumulates while Operations is open)
- Per-account pending targets and API quota snapshot
- **Open Horizon** link for queue worker detail

## Crawl

- Recent fetch runs (running plus recent completed/failed) with progress, started time, and failure reason
- Pending targets per account (and cooldown hint)

## Transfers

- Download batches with group/storage path, progress, sample errors, and failed-item retry
- Upload batches with progress and failed-item retry

## When to use

- Crawl seems stuck — check Overview cooldown / pending, then Crawl tab
- Download/upload progress — Transfers tab (or Horizon for worker depth)
- Debugging failures — `failed_reason` on fetch runs and sample errors on batches

## Related

- [Dashboard](dashboard.md)
- [Spider mode](spider-mode.md) — start/stop lives on Flickr account Expand actions, not here
- [Horizon](../03-operations/horizon.md)
