# Maintenance backlog

Single source of truth for known gaps. Detail lives in the linked audit finding — do not restate it here.

## Open

_None._

## Blocked

_None — spider unblocked by crawler 1.3.0._

## Resolved (prune after next release)

| ID | Item | Resolved in | Evidence |
|----|------|-------------|----------|
| N-08 | Flickr `token_payload` plaintext (now `Modules/Crawler` connections) | Unreleased | `Connection` model `token_payload` => `encrypted` cast; migration `2026_07_06_000100_encrypt_xflickr_connection_token_payload`; `ConnectionTokenEncryptionTest`; `SECURITY.md` |
| N-13 | README badges missing | Unreleased | CI + Release badges in `README.md` |
| N-15 | Hardcoded download HTTP timeout (120s) | Unreleased | Curated `xflickr_download.timeout_seconds` in Settings; `TransferRuntimeConfig` + `FileDownloadService` |
| N-16 | Flickr client layering: SDK → Crawler `FlickrClientFactory` (auth true/false) → peers via Crawler/Flickr (not ad hoc `FlickrFactory`) | Unreleased | `FlickrClientFactory` (`authenticated` flag, `anonymousClient`), `FlickrClientFactoryLayeringTest` |
| N-01 | Default admin credential / rotation | v1.2.0 | `ADMIN_PASSWORD`, `xflickr:auth:reset-password`, Auth `AdminUserSeeder` first-create only (optional / explicit class) |
| N-02 | Unused `jooservices/dto` | v1.2.0 | `DownloadCandidateDto`, `OAuthAppConfigDto` wired at call sites |
| N-03 | Spider mode | v1.2.0 | Crawler 1.3.0 + event-driven `SpiderPlannerService` |
| N-04 | Secret-scanning CI stub | v1.2.0 | `.github/workflows/secret-scanning.yml` + gitleaks |
| N-05 | `attempts()` vs `maxExceptions` conflation | v1.2.0 | Execution services + job tests |
| N-06 | Fan-out N+1 queries | v1.2.0 | Chunked set-based fan-out |
| N-07 | Upload lock / deferral | v1.2.0 | `WithoutOverlapping` per storage account; `retryUntil` without fixed tries cap |
| N-09 | Dashboard query storm | v1.2.0 | Aggregate queries + 15s cache |
| N-10 | Silent OAuth catch blocks | v1.2.0 | `Log::warning` in auth controllers |
| F-07 | Reconciler race | v1.1.0 | `TransferBatchReconciler` transaction |
| F-10 | Dashboard per-account loop (partial) | v1.2.0 | Bulk aggregate maps |
| S-11 | `xflickr:flickr:doctor` health command | v1.2.0 | `DoctorCommand` |
