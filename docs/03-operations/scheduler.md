# Scheduler

XFlickr registers scheduled commands in `routes/console.php`.

## Registered commands

| Command | Schedule | Purpose |
|---|---|---|
| `xflickr:dispatch` | Every minute (`withoutOverlapping`) | Drain pending crawl targets from the crawler package |

## Important

The scheduler **does not create new crawl targets**. It only processes targets created when a user clicks **Crawl**. This keeps Flickr API usage predictable.

## Docker

The dev stack runs the scheduler as the `scheduler` service.

Native install:

```bash
php artisan schedule:work
```

Or add to system cron:

```cron
* * * * * cd /path/to/xflickr && php artisan schedule:run >> /dev/null 2>&1
```

## Global crawl pause

When enabled in Settings, crawl dispatch respects the pause flag and jobs will not run until resumed.
