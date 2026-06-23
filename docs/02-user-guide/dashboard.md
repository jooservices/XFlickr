# Dashboard

Path: `/dashboard`

The dashboard provides a live snapshot of crawl progress, catalog growth, and transfer health.

## Global stats

- Connected account count
- Running crawl runs and pending targets
- Total photos in catalog (with sizes resolved)
- Stored files count
- Active download/upload batches
- Failed transfers in the last 24 hours

## Per-account rows

Each connected Flickr account shows:

- API quota meter (requests remaining, cooldown)
- Crawl run status counts
- Latest crawl run details
- Active transfer batches

## Auto-refresh

The page polls `/api/dashboard/snapshot` every 5 seconds.

## Alerts

Banner appears when any account is in rate-limit cooldown or transfers failed in the last 24 hours.

## Related

- [Operations](operations.md) — detailed crawl run and batch lists
- [Rate limiting](rate-limiting.md)
