# Storage module

Owns cloud destination accounts, OAuth/R2 configuration, account-bound provider adapters, browse/sync/delete/stream/upload/quota, and connection verification for Google Drive, Google Photos, OneDrive, and R2.

`StorageService` is the peer-facing provider-I/O facade. Storage never imports Transfer; Transfer may call `StorageService`. Adapters are freshly created per account and must not be cached in Horizon workers.

See `docs/00-architecture/modules.md`, `docs/02-user-guide/storage-browse.md`, and `ai/skills/storage-driver-safety/SKILL.md`.
