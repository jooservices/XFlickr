---
name: transfer-pipeline-safety
description: Download and upload deduplication, batch tracking, and job delegation.
---

# Skill: transfer-pipeline-safety

## Purpose

Keep download/upload pipelines safe, deduplicated, and consistent.

## Dedup rules

**Download:** skip if `stored_files.local_downloaded_at` is set for `flickr_photo_id`.

**Upload:** skip if `storage_uploads` exists for `(flickr_photo_id, storage_account_id)`.

**Upload ensures local:** if not downloaded, download first (still deduped).

## Key classes

| Class | Role |
|---|---|
| `PhotoDownloadService` | Queue download batches |
| `PhotoUploadService` | Queue upload batches |
| `DownloadPhotoJob` | Single photo download (delegates to Service) |
| `UploadPhotoJob` | Single photo upload (delegates to Service) |
| `TransferBatchTracker` | Atomic progress counters |
| `FlickrPhotoSizeResolver` | Resolve Flickr download URLs |

## Rules

- Upload queue: limited concurrency (Horizon supervisor `maxProcesses: 1` for uploads).
- Jobs must not contain business logic — delegate to Services.
- Batch progress visible on Operations page and Dashboard.

## Related skills

- `queue-horizon-operations`
- `form-request-service-repository`
