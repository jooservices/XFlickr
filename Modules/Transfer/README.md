# Transfer module

Owns local download/upload execution, `stored_files`, storage-upload bookkeeping, transfer batches/items, retries, progress, and integrity scans. Storage owns provider I/O; Contacts owns download/upload HTTP queue entry points.

Integrity scans are queued and persisted. Repairs operate only on server-created anomaly IDs, never a client-provided filesystem path. Transfer may depend on Flickr and `StorageService`; Storage must never depend on Transfer.

See `docs/00-architecture/modules.md` and `ai/skills/transfer-pipeline-safety/SKILL.md`.
