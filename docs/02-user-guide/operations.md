# Operations

Path: `/operations` (legacy `/crawl/operations` redirects here)

Live process console for crawls, transfers, and platform health. Prefer this page when diagnosing stuck jobs; use [Dashboard](dashboard.md) for catalog growth KPIs.

Query tabs: `?panel=overview` (default) · `crawl` · `transfers`.

**Live updates:** Laravel Reverb WebSocket (private `operations` channel) when configured; otherwise JSON polling every 5 seconds. On WebSocket disconnect longer than 15 seconds the UI falls back to polling until reconnect. Legacy SSE `GET /api/v1/operations/stream` is frozen (one-shot) and unused by the UI.

## Overview

- Activity metrics: running crawls, pending targets, active transfers, failed transfers (24h), accounts in rate-limit cooldown, global pause badge (display-only)
- Queue depth meters for `xflickr`, `xflickr-downloads`, `xflickr-uploads`
- Session activity chart (running / pending / transfers) while the page stays open
- Flickr API usage chart (hourly buckets; polled)
- Service probes: MySQL, Redis, MongoDB (`ok` / latency)
- Databases panel with 24h size/connections chart (same samples as Dashboard; accumulates while Operations is open)
- Per-account pending targets and API quota snapshot
- **Open Horizon** link for queue worker detail

## Crawl

- Spider frontier (read-only) when auto-expand is active — pending / queued / crawled
- Active target breakdown by status × task type for running fetch runs
- Recent fetch runs (running plus recent completed/failed) with progress, started time, and failure reason
- Pending targets per account (and cooldown hint)

## Transfers

- Download batches with group/storage path, progress, sample errors, and failed-item retry (pushed on reconcile when WebSocket is live)
- Upload batches with progress and failed-item retry

## When to use

- Crawl seems stuck — check Overview cooldown / pending / queues, then Crawl tab target breakdown
- Download/upload progress — Transfers tab (or Horizon for worker detail)
- Debugging failures — `failed_reason` on fetch runs and sample errors on batches

## Related

- [Dashboard](dashboard.md)
- [Spider mode](spider-mode.md) — start/stop lives on Flickr account Expand actions, not here
- [Horizon](../03-operations/horizon.md)
- [Reverb / WebSockets](../03-operations/reverb.md)
