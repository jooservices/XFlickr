---
name: transfer-pipeline-safety
description: Download and upload deduplication, batch tracking, and job delegation.
---

# Skill: transfer-pipeline-safety

## Purpose

Keep download/upload pipelines safe, deduplicated, and consistent.

## Dedup rules

**Download:** skip when the original `stored_files` row for `source_id` is already completed.

**Upload:** `FileUploadExecutionService` reuses `(stored_file_id, storage_account_id)` bookkeeping and treats a completed upload as idempotent success.

**Upload ensures local:** if not downloaded, download first (still deduped).

## Key classes

| Class | Role |
|---|---|
| `PhotoTransferService` | Resolve selections and orchestrate download/upload queueing |
| `TransferBatchService` | Atomically create batches/items, query progress, and retry failures |
| `FileDownloadService` | Execute one local download and persist status |
| `FileUploadExecutionService` | Execute one upload through `StorageService` and persist status |
| `DownloadFileJob` / `UploadFileJob` | Queue wrappers that delegate to execution services |
| `FanOutTransferJob` | Chunk owner-wide selections without loading the full catalog in HTTP |
| `TransferBatchReconciler` | Recompute batch progress through repository transactions |
| `FlickrPhotoSourceService` | Flickr-owned source URL and remote-path boundary |
| `IntegrityScanService` / `RunIntegrityScanJob` | Queued local-cache consistency scan and server-authorized anomaly resolution |

## Rules

- Upload queue: limited concurrency (Horizon supervisor `maxProcesses: 1` for uploads).
- Jobs must not contain business logic — delegate to Services.
- Jobs serialize scalar IDs, strings, booleans, and enums only; do not serialize account or catalog models.
- Batch and item rows must commit before jobs are dispatched.
- Transfer may call Flickr and Storage facades; Flickr and Storage must never import Transfer services or models.
- Batch progress visible on Operations page and Dashboard.
- Integrity scans are persisted and queued; browser actions use opaque anomaly IDs. Never accept a client filesystem path or arbitrary stored-file ID for repair.

## Related skills

- `queue-horizon-operations`
- `form-request-service-repository`
