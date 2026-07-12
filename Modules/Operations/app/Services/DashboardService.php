<?php

declare(strict_types=1);

namespace Modules\Operations\Services;

use App\Repositories\Crawler\CatalogQueryRepository;
use App\Repositories\Crawler\ContactQueryRepository;
use App\Repositories\Crawler\CrawlRunQueryRepository;
use App\Repositories\Crawler\CrawlTargetQueryRepository;
use App\Repositories\Crawler\PhotoQueryRepository;
use Illuminate\Support\Facades\Cache;
use JOOservices\XFlickrCrawler\Facades\FlickrService;
use Modules\Flickr\Services\FlickrRateLimitPresenter;
use Modules\Flickr\Support\ConnectionPresenter;
use Modules\Transfer\Repositories\StoredFileRepository;
use Modules\Transfer\Repositories\TransferBatchRepository;
use Modules\Transfer\Repositories\TransferItemRepository;

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
        return Cache::remember('xflickr:dashboard:snapshot', 15, function () use ($rateLimit): array {
            $connections = FlickrService::connections()->list();
            $connectionKeys = $connections->map(fn ($connection) => $connection->connection_key)->values()->all();

            $accounts = $connections
                ->map(fn ($connection): array => ConnectionPresenter::toArray($connection))
                ->values()
                ->all();

            $since = now()->subDay();

            $runStatusByConnection = $this->crawlRuns->statusCountsGroupedByConnection($connectionKeys);
            $latestRuns = $this->crawlRuns->latestRunsByConnection($connectionKeys);
            $pendingTargetsByConnection = $this->crawlTargets->countPendingGroupedByConnection($connectionKeys);
            $contactsByConnection = $this->contacts->countsGroupedByConnection($connectionKeys);
            $photosByConnection = $this->photos->countsGroupedByConnection($connectionKeys);
            $photosWithSizesByConnection = $this->photos->countsWithSizesGroupedByConnection($connectionKeys);
            $photosetsByConnection = $this->catalog->photosetCountsGroupedByConnection($connectionKeys);
            $galleriesByConnection = $this->catalog->galleryCountsGroupedByConnection($connectionKeys);
            $favoritesByConnection = $this->catalog->favoriteCountsGroupedByConnection($connectionKeys);
            $downloadsActiveByConnection = $this->batches->countActiveGroupedByConnection($connectionKeys, 'download');
            $uploadsActiveByConnection = $this->batches->countActiveGroupedByConnection($connectionKeys, 'upload');
            $failedItemsByConnection = $this->items->countFailedGroupedByConnectionSince($connectionKeys, $since);

            $global = [
                'accounts' => count($accounts),
                'runs_running' => $this->crawlRuns->countByConnectionsAndStatus($connectionKeys, 'running'),
                'pending_targets' => $this->crawlTargets->countPendingForConnections($connectionKeys),
                'stored_files' => $this->storedFiles->countAll(),
                'downloads_active' => $this->batches->countByTypeAndStatus('download', 'running'),
                'uploads_active' => $this->batches->countByTypeAndStatus('upload', 'running'),
                'failed_transfers_24h' => $this->items->countFailedSince($since),
            ];

            $perAccount = [];
            foreach ($connections as $connection) {
                $key = (string) $connection->connection_key;
                $latestRun = $latestRuns[$key] ?? null;

                $perAccount[] = [
                    'account' => ConnectionPresenter::toArray($connection),
                    'rate_limit' => $rateLimit->present($key),
                    'runs' => $runStatusByConnection[$key] ?? ['running' => 0, 'completed' => 0, 'failed' => 0],
                    'pending_targets' => $pendingTargetsByConnection[$key] ?? 0,
                    'contacts_db' => $contactsByConnection[$key] ?? 0,
                    'photos_db' => $photosByConnection[$key] ?? 0,
                    'photos_with_sizes' => $photosWithSizesByConnection[$key] ?? 0,
                    'photosets_db' => $photosetsByConnection[$key] ?? 0,
                    'galleries_db' => $galleriesByConnection[$key] ?? 0,
                    'favorites_db' => $favoritesByConnection[$key] ?? 0,
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
                        'downloads_active' => $downloadsActiveByConnection[$key] ?? 0,
                        'uploads_active' => $uploadsActiveByConnection[$key] ?? 0,
                        'failed_items_24h' => $failedItemsByConnection[$key] ?? 0,
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
