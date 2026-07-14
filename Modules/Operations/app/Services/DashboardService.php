<?php

declare(strict_types=1);

namespace Modules\Operations\Services;

use App\Repositories\Crawler\CatalogQueryRepository;
use App\Repositories\Crawler\ContactQueryRepository;
use App\Repositories\Crawler\CrawlRunQueryRepository;
use App\Repositories\Crawler\CrawlTargetQueryRepository;
use App\Repositories\Crawler\PhotoQueryRepository;
use Illuminate\Support\Facades\Cache;
use Modules\Flickr\Services\FlickrOAuthService;
use Modules\Flickr\Services\FlickrRateLimitPresenter;
use Modules\Flickr\Support\ConnectionPresenter;
use Modules\Transfer\Services\TransferCountsQueryService;

final class DashboardService
{
    public function __construct(
        private readonly CatalogQueryRepository $catalog,
        private readonly ContactQueryRepository $contacts,
        private readonly CrawlRunQueryRepository $crawlRuns,
        private readonly CrawlTargetQueryRepository $crawlTargets,
        private readonly PhotoQueryRepository $photos,
        private readonly TransferCountsQueryService $transferCounts,
        private readonly FlickrOAuthService $oauth,
        private readonly FlickrRateLimitPresenter $rateLimit,
        private readonly DatabaseUsageService $databases,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return Cache::remember('xflickr:dashboard:snapshot', 15, function (): array {
            $connections = $this->oauth->listConnections();
            $connectionKeys = $connections->map(fn ($connection) => $connection->connection_key)->values()->all();

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
            $downloadsActiveByConnection = $this->transferCounts->countActiveBatchesGroupedByConnection($connectionKeys, 'download');
            $uploadsActiveByConnection = $this->transferCounts->countActiveBatchesGroupedByConnection($connectionKeys, 'upload');
            $failedItemsByConnection = $this->transferCounts->countFailedItemsGroupedByConnectionSince($connectionKeys, $since);

            $global = [
                'accounts' => $connections->count(),
                'runs_running' => $this->crawlRuns->countByConnectionsAndStatus($connectionKeys, 'running'),
                'pending_targets' => $this->crawlTargets->countPendingForConnections($connectionKeys),
                'stored_files' => $this->transferCounts->countStoredFiles(),
                'downloads_active' => $this->transferCounts->countBatchesByTypeAndStatus('download', 'running'),
                'uploads_active' => $this->transferCounts->countBatchesByTypeAndStatus('upload', 'running'),
                'failed_transfers_24h' => $this->transferCounts->countFailedItemsSince($since),
            ];

            $perAccount = [];
            foreach ($connections as $connection) {
                $key = (string) $connection->connection_key;
                $latestRun = $latestRuns[$key] ?? null;

                $perAccount[] = [
                    'account' => ConnectionPresenter::toArray($connection),
                    'rate_limit' => $this->rateLimit->present($key),
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

            $databases = $this->databases->snapshot();
            $mysql = $databases['mysql'] ?? [];
            $mongodb = $databases['mongodb'] ?? [];
            $mysqlConnectionsCurrent = isset($mysql['connections_current']) ? (int) $mysql['connections_current'] : null;
            $mysqlConnectionsMax = isset($mysql['connections_max']) ? (int) $mysql['connections_max'] : null;
            $mysqlConnectionsHigh = $mysqlConnectionsCurrent !== null
                && $mysqlConnectionsMax !== null
                && $mysqlConnectionsMax > 0
                && ($mysqlConnectionsCurrent / $mysqlConnectionsMax) >= 0.8;

            return [
                'generated_at' => now()->toISOString(),
                'global' => $global,
                'accounts' => $perAccount,
                'databases' => $databases,
                'alerts' => [
                    'any_cooldown' => $cooldownSeconds > 0,
                    'database_unreachable' => ($mysql['status'] ?? null) === 'error' || ($mongodb['status'] ?? null) === 'error',
                    'mysql_connections_high' => $mysqlConnectionsHigh,
                ],
            ];
        });
    }
}
