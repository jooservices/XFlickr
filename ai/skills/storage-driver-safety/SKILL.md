---
name: storage-driver-safety
description: Google Photos, Drive, OneDrive, and Cloudflare R2 OAuth and Flysystem safety.
---

# Skill: storage-driver-safety

## Purpose

Safe handling of storage providers and credentials.

## Providers

| Driver | Auth | Enum |
|---|---|---|
| Google Drive | OAuth 2.0 | `StorageDriver::GoogleDrive` |
| Google Photos | OAuth 2.0 | `StorageDriver::GooglePhotos` |
| OneDrive | OAuth 2.0 | `StorageDriver::OneDrive` |
| Cloudflare R2 | API keys (S3) | `StorageDriver::R2` |

Source: `Modules/Storage/app/Enums/StorageDriver.php`

## Rules

- OAuth app credentials in MongoDB (`storage_app.*`) — never commit secrets.
- Connected account tokens in MySQL `storage_accounts.credentials` (encrypted).
- R2: no OAuth — configure endpoint, bucket, keys in Settings.
- Google Photos scope: app-created data only — browse/upload limited to app uploads.
- Delete operations: use `StorageService::delete()` — provider-specific rules apply.
- Never add a provider transport/transfer service between `StorageService` and an adapter.
- Adapters are account-bound, created fresh by `StorageAdapterFactory`, and never cached in Horizon workers.
- Provider SDK/Flysystem failures must leave the Storage boundary as `StorageOperationException` without secrets or raw provider response bodies.
- Google Photos album lookup must honor pagination, provider title limits, and concurrency; do not treat a temporary cache entry as the only album identity control.

## Key services

- `StorageAccountService` — connect/disconnect accounts
- `StorageService` — peer facade for `upload()` / `openStreamForAccount()` / `delete()` / `resolveAccountId()`; resolves a fresh adapter and synchronizes local remote-item cache
- `StorageAdapterFactory` — exhaustive `StorageDriver` match that constructs one account-bound adapter per call
- `AbstractFlysystemAdapter` — shared Drive/OneDrive/R2 upload, stream, list, and delete algorithms
- `GooglePhotosAdapter` — direct Google Photos API behavior where Flysystem is not applicable
- `StorageBrowseService` / `StorageBrowseSyncService` — remote browse and atomic local-cache synchronization
- Connection health: `ConnectionVerificationService` calls the optional `StorageVerifiable` capability (CLI `xflickr:storage:verify-connections`)

Transfer batches, jobs, stored files, and upload bookkeeping live in `Modules/Transfer`. Storage publishes `StorageRemoteItemsRemoved`; the Transfer listener owns cleanup of `storage_uploads` records.

## Related skills

- `transfer-pipeline-safety`
- `security-hardening`
