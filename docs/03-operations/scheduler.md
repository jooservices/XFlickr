# Scheduler

XFlickr registers scheduled commands in `routes/console.php`.

## Registered commands

| Command | Schedule | Purpose |
|---|---|---|
| `xflickr:crawler:dispatch` | Every minute (`withoutOverlapping`) | Drain pending crawl targets; it never creates them |
| `xflickr:spider:expand` | Every minute (`withoutOverlapping`) | Expand opt-in Spider frontier work |
| `xflickr:contacts:full-pass-expand` | Every minute (`withoutOverlapping`) | Expand an active full contact pass |
| `xflickr:transfer:integrity-scan` | Daily at 02:00 (`withoutOverlapping`) | Queue a persisted local-storage integrity scan |

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
