# Storage browse

Paths: `/storages/google-photos`, `/storages/google-drive`, `/storages/onedrive`, `/storages/r2`

Browse, sync, and manage files uploaded to connected cloud storage accounts.

## Features

- Browse remote folders and albums
- Sync remote state with local tracking tables
- Download files from cloud to local server
- Delete remote items (provider-specific rules apply)

## Provider notes

| Provider | Browse scope |
|---|---|
| Google Photos | Photos uploaded by this app |
| Google Drive | Files created by this app |
| OneDrive | Files in connected account |
| Cloudflare R2 | Configured bucket objects |

## Prerequisites

1. Configure provider credentials in [Settings](settings.md) → Storage tab
2. Connect a storage account
3. Run [Upload](connections.md) to push local files first

## Related

- [Settings](settings.md)
