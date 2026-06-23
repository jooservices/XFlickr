# Operations

Path: `/crawl/operations`

Monitor crawl runs and download/upload transfer batches in real time.

## Crawl runs

Shows recent crawl runs with:

- Account and subject NSID
- Crawl type (contacts, photos, photosets, galleries, favorites)
- Status (running, completed, failed)
- Discovered counts and API call usage
- Start/completion timestamps and failure reasons

## Transfer batches

Shows download and upload batches with:

- Batch type and status
- Progress counters (total, completed, failed, skipped)
- Subject NSID and grouping (owner, photoset, gallery)
- Sample error message for failed batches

## When to use

- Crawl seems stuck — check for rate-limit cooldown on Dashboard
- Download/upload progress — verify batch status here or in Horizon
- Debugging failures — read `failed_reason` on crawl runs and sample errors on batches

## Related

- [Dashboard](dashboard.md)
- [Horizon](../03-operations/horizon.md)
