# Dashboard

Path: `/dashboard`

The dashboard provides a live snapshot of crawl progress, catalog growth, transfer health, and database usage.

## Global stats

- Connected account count
- Running crawl runs and pending targets
- Catalog counts for the selected account (contacts, photos, photosets, galleries)
- Active download/upload batches
- Failed transfers in the last 24 hours

## Databases

Host-level MySQL and MongoDB usage (not per Flickr account):

- Reachability status
- Data size (MySQL schema / MongoDB `dbStats`)
- MySQL connection gauge (`Threads_connected` / `max_connections`) when the SQL driver supports it
- Top MySQL tables by size (when `information_schema` is available)
- Size / connections chart for the last 24 hours — samples accumulate while the Dashboard is open (about one per minute)

## Per-account rows

Each connected Flickr account shows:

- API quota meter (requests remaining, cooldown)
- Crawl run status counts
- Latest crawl run details
- Active transfer batches

## Auto-refresh

The page polls `/api/v1/dashboard/snapshot` every 15 seconds. Database usage itself is cached for about 60 seconds on the server.

## Alerts

Banner appears when:

- Any account is in rate-limit cooldown
- Transfers failed in the last 24 hours
- MySQL or MongoDB is unreachable
- MySQL connections are at or above 80% of `max_connections`

## Related

- [Operations](operations.md) — process console (Overview / Crawl / Transfers); compact DB/service probes without the 24h chart
- [Rate limiting](rate-limiting.md)
