# Horizon

XFlickr uses Laravel Horizon to process queued jobs.

## Access

- URL: `http://localhost:8082/horizon` (local dev)
- Config: `config/horizon.php`

## Key queues

| Queue area | Jobs |
|---|---|
| Default | Crawl dispatch |
| Downloads (`xflickr-downloads`) | `FanOutTransferJob`, `DownloadFileJob` |
| Uploads (`xflickr-uploads`) | `FanOutTransferJob`, `UploadFileJob` (limited concurrency) |

Upload jobs use a dedicated supervisor with `maxProcesses: 1` to avoid concurrent cloud API calls.

## Docker

The dev stack starts Horizon automatically as the `horizon` service.

Native install:

```bash
php artisan horizon
```

## Monitoring

- Horizon dashboard for job throughput and failures
- In-app [Operations](../02-user-guide/operations.md) page for crawl runs and transfer batches
- [Dashboard](../02-user-guide/dashboard.md) for high-level transfer counts

## Troubleshooting

- Jobs not processing: verify Redis is running and `QUEUE_CONNECTION=redis`
- Failed jobs: check Horizon "Failed Jobs" tab and `storage/logs/laravel.log`
