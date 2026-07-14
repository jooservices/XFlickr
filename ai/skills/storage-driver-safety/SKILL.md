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

Source: `app/Enums/StorageDriver.php`

## Rules

- OAuth app credentials in MongoDB (`storage_app.*`) — never commit secrets.
- Connected account tokens in MySQL `storage_accounts.credentials` (encrypted).
- R2: no OAuth — configure endpoint, bucket, keys in Settings.
- Google Photos scope: app-created data only — browse/upload limited to app uploads.
- Delete operations: use `StorageDeleteService` — provider-specific rules apply.

## Key services

- `StorageAccountService` — connect/disconnect accounts
- `StorageUploadService` — Flysystem upload
- `StorageBrowseService` / `StorageBrowseSyncService` — remote browse (registry → provider `*\BrowseService` / `*\DeleteService`)
- Provider deletes: `GooglePhotos\DeleteService`, `GoogleDrive\DeleteService`, `OneDrive\DeleteService`, `R2\DeleteService` (cache sync in `StorageDeleteService`)

## Related skills

- `transfer-pipeline-safety`
- `security-hardening`
