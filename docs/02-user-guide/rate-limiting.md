# Rate limiting

XFlickr respects Flickr API quotas through `jooservices/xflickr-crawler`.

## How it works

- Each connected account has a rate-limit budget tracked in Redis.
- Crawl fetchers check remaining quota before calling Flickr API.
- When quota is exhausted, the account enters a **cooldown** period.
- Dashboard shows quota meters, an hourly API usage chart (last 24 hours with quota reference line), and cooldown alerts per account.
- The sticky app status footer (all authenticated pages) shows a live Flickr API quota meter for the selected account.

## User impact

- Crawl operations may pause or slow down during cooldown.
- Operations page shows running/pending targets — they resume when quota allows.
- **No automatic retry storm** — the package backs off according to Flickr limits.

## Best practices

1. Crawl selectively — index contacts first, then crawl individual contacts as needed.
2. Avoid bulk-crawling all contacts at once on large contact lists.
3. Monitor the status-footer or Dashboard quota meters before starting large operations.
4. Use **global crawl pause** from the top navbar (**Pause** / **Resume**) if you need to stop all dispatch temporarily.

## Manual operations only

XFlickr never creates crawl targets on its own. The scheduler (`xflickr:dispatch`) only drains targets you created by clicking Crawl.

## Package reference

See `vendor/jooservices/xflickr-crawler/docs/02-user-guide/02-rate-limiting.md` for crawler-level details.
