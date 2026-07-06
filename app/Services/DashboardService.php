<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\Crawler\CatalogQueryRepository;
use App\Repositories\Crawler\ContactQueryRepository;
use App\Repositories\Crawler\CrawlRunQueryRepository;
use App\Repositories\Crawler\CrawlTargetQueryRepository;
use App\Repositories\Crawler\PhotoQueryRepository;
use App\Repositories\StoredFileRepository;
use App\Repositories\TransferBatchRepository;
use App\Repositories\TransferItemRepository;
use App\Services\Flickr\FlickrRateLimitPresenter;
use App\Support\Flickr\ConnectionPresenter;
use Illuminate\Support\Facades\Cache;
use JOOservices\XFlickrCrawler\Facades\FlickrService;

final class DashboardService
{
    public function __construct(
        private readonly CatalogQueryRepository $catalog,
        private readonly ContactQueryRepository $contacts,
        private readonly CrawlRunQueryRepository $crawlRuns,
        private readonly CrawlTargetQueryRepository $crawlTargets,
        private readonly PhotoQueryRepository $photos,
        private readonly StoredFileRepository $storedFiles,
        private readonly TransferBatchRepository $batches,
        private readonly TransferItemRepository $items,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(FlickrRateLimitPresenter $rateLimit): array
    {
        return Cache::remember('xflickr:dashboard:snapshot', 5, function () use ($rateLimit): array {
            $connections = FlickrService::connections()->list();
            $connectionKeys = $connections->map(fn ($connection) => $connection->connection_key)->values()->all();

            $accounts = $connections
                ->map(fn ($connection): array => ConnectionPresenter::toArray($connection))
                ->values()
                ->all();

            $global = [
                'accounts' => count($accounts),
                'runs_running' => $this->crawlRuns->countByConnectionsAndStatus($connectionKeys, 'running'),
                'pending_targets' => $this->crawlTargets->countPendingForConnections($connectionKeys),
                'stored_files' => $this->storedFiles->countAll(),
                'downloads_active' => $this->batches->countByTypeAndStatus('download', 'running'),
                'uploads_active' => $this->batches->countByTypeAndStatus('upload', 'running'),
                'failed_transfers_24h' => $this->items->countFailedSince(now()->subDay()),
            ];

            $perAccount = [];
            foreach ($connections as $connection) {
                $key = (string) $connection->connection_key;

                $latestRun = $this->crawlRuns->latestForConnection($key);

                $perAccount[] = [
                    'account' => ConnectionPresenter::toArray($connection),
                    'rate_limit' => $rateLimit->present($key),
                    'runs' => $this->crawlRuns->statusCountsForConnection($key),
                    'pending_targets' => $this->crawlTargets->countPendingForConnection($key),
                    'contacts_db' => $this->contacts->countForConnection($key),
                    'photos_db' => $this->photos->countForConnection($key),
                    'photos_with_sizes' => $this->photos->countWithSizesForConnection($key),
                    'photosets_db' => $this->catalog->countPhotosetsForConnection($key),
                    'galleries_db' => $this->catalog->countGalleriesForConnection($key),
                    'latest_run' => $latestRun === null ? null : [
                        'id' => $latestRun->id,
                        'crawl_type' => $latestRun->crawl_type,
                        'subject_nsid' => $latestRun->subject_nsid,
                        'status' => $latestRun->status,
                        'contacts_discovered' => (int) $latestRun->contacts_discovered,
                        'photos_discovered' => (int) $latestRun->photos_discovered,
                        'api_calls' => (int) $latestRun->api_calls,
                        'started_at' => $latestRun->started_at?->toISOString(),
                        'completed_at' => $latestRun->completed_at?->toISOString(),
                        'failed_reason' => $latestRun->failed_reason,
                    ],
                    'transfers' => [
                        'downloads_active' => $this->batches->countActiveForConnection($key, 'download'),
                        'uploads_active' => $this->batches->countActiveForConnection($key, 'upload'),
                        'failed_items_24h' => $this->items->countFailedForConnectionSince($key, now()->subDay()),
                    ],
                ];
            }

            $cooldownSeconds = array_reduce(
                $perAccount,
                fn (int $max, array $row): int => max($max, (int) ($row['rate_limit']['cooldown_seconds_remaining'] ?? 0)),
                0,
            );

            return [
                'generated_at' => now()->toISOString(),
                'global' => $global,
                'accounts' => $perAccount,
                'alerts' => [
                    'any_cooldown' => $cooldownSeconds > 0,
                ],
            ];
        });
    }
}
