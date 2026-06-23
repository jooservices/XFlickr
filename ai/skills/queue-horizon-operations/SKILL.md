---
name: queue-horizon-operations
description: Laravel Horizon queue supervisors and XFlickr queue naming.
---

# Skill: queue-horizon-operations

## Purpose

Configure and operate Horizon queues for crawls, downloads, and uploads.

## Access

- Local: http://localhost:8082/horizon
- Config: `config/horizon.php`

## Queue conventions

| Queue | Jobs | Concurrency |
|---|---|---|
| default | Crawl fan-out, batch dispatchers | Higher |
| downloads | `DownloadPhotoJob` | Moderate |
| uploads | `UploadPhotoJob` | **1** (serial cloud uploads) |

## Rules

- Do not flush Redis queues on dev stack without user request.
- Failed jobs: inspect Horizon UI and `storage/logs/laravel.log`.
- Docker dev stack runs Horizon as `horizon` service.
- Global crawl pause in Settings blocks dispatch — respect in new code.

## Scheduler

`xflickr:dispatch` every minute in `routes/console.php` — drains crawl targets only.

## Related skills

- `crawler-pipeline-integrity`
- `transfer-pipeline-safety`
