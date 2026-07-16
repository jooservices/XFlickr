<?php

declare(strict_types=1);

namespace Modules\Operations\Services;

use App\Repositories\Crawler\CatalogQueryRepository;
use App\Repositories\Crawler\ContactQueryRepository;
use App\Repositories\Crawler\CrawlRunQueryRepository;
use App\Repositories\Crawler\CrawlTargetQueryRepository;
use App\Repositories\Crawler\PhotoQueryRepository;
use Illuminate\Support\Facades\Cache;
use Modules\Flickr\Services\FlickrAccountsService;
use Modules\Flickr\Support\ConnectionPresenter;
use Modules\Spider\Services\SpiderPlannerService;
use Modules\Transfer\Services\TransferBatchService;

final class SnapshotService
{
    private const int FETCH_RUNS_PER_CONNECTION = 10;

    private const int TRANSFER_BATCHES_PER_CONNECTION = 20;

    public function __construct(
        private readonly CatalogQueryRepository $catalog,
        private readonly ContactQueryRepository $contacts,
        private readonly CrawlRunQueryRepository $crawlRuns,
        private readonly CrawlTargetQueryRepository $crawlTargets,
        private readonly PhotoQueryRepository $photos,
        private readonly TransferBatchService $transfers,
        private readonly FlickrAccountsService $flickr,
        private readonly DatabaseUsageService $databases,
        private readonly ServicesDependencyProbeService $dependencies,
        private readonly QueueDepthService $queues,
        private readonly SpiderPlannerService $spider,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        return Cache::remember('xflickr:dashboard:snapshot', 15, function (): array {
            $connections = $this->flickr->listConnections();
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
            $downloadsActiveByConnection = $this->transfers->countActiveBatchesGroupedByConnection($connectionKeys, 'download');
            $uploadsActiveByConnection = $this->transfers->countActiveBatchesGroupedByConnection($connectionKeys, 'upload');
            $failedItemsByConnection = $this->transfers->countFailedItemsGroupedByConnectionSince($connectionKeys, $since);

            $crawl = $this->crawlCounts($connectionKeys);
            $transfer = $this->transferCounts($since);

            $global = [
                'accounts' => $connections->count(),
                'runs_running' => $crawl['runs_running'],
                'pending_targets' => $crawl['pending_targets'],
                'stored_files' => $this->transfers->countStoredFiles(),
                'downloads_active' => $transfer['downloads_active'],
                'uploads_active' => $transfer['uploads_active'],
                'failed_transfers_24h' => $transfer['failed_transfers_24h'],
            ];

            $perAccount = [];
            foreach ($connections as $connection) {
                $key = (string) $connection->connection_key;
                $latestRun = $latestRuns[$key] ?? null;

                $perAccount[] = [
                    'account' => ConnectionPresenter::toArray($connection),
                    'rate_limit' => $this->flickr->rateLimitPresent($key),
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

            $databases = $this->databaseUsage();
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

    /**
     * Lightweight overview + queue depths for WebSocket patches.
     *
     * @return array{overview: array<string, int>, queues: array<string, int|null>}
     */
    public function overviewAndQueues(): array
    {
        $connections = $this->flickr->listConnections();
        $connectionKeys = $connections->map(fn ($connection) => $connection->connection_key)->values()->all();
        $since = now()->subDay();
        $accountsInCooldown = 0;
        $globalPause = false;

        foreach ($connections as $connection) {
            $rateLimit = $this->flickr->rateLimitPresent((string) $connection->connection_key);
            if ((int) ($rateLimit['cooldown_seconds_remaining'] ?? 0) > 0) {
                $accountsInCooldown++;
            }
            if (($rateLimit['global_pause'] ?? false) === true) {
                $globalPause = true;
            }
        }

        $crawl = $this->crawlCounts($connectionKeys);
        $transfer = $this->transferCounts($since);

        return [
            'overview' => [
                'runs_running' => $crawl['runs_running'],
                'pending_targets' => $crawl['pending_targets'],
                'downloads_active' => $transfer['downloads_active'],
                'uploads_active' => $transfer['uploads_active'],
                'failed_transfers_24h' => $transfer['failed_transfers_24h'],
                'accounts_in_cooldown' => $accountsInCooldown,
                'global_pause' => $globalPause ? 1 : 0,
            ],
            'queues' => $this->queues->depths(),
        ];
    }

    /**
     * @return array{
     *     overview: array<string, int>,
     *     queues: array<string, int|null>,
     *     target_breakdown: list<array<string, mixed>>,
     *     spider: list<array<string, mixed>>,
     *     dependencies: array<string, array{ok: bool, latency_ms: int|null, detail: string|null}>,
     *     databases: array{
     *         mysql: array<string, mixed>,
     *         mongodb: array<string, mixed>,
     *         history: list<array<string, mixed>>
     *     },
     *     accounts: list<array<string, mixed>>,
     *     fetch_runs: list<mixed>,
     *     download_batches: list<mixed>,
     *     upload_batches: list<mixed>,
     * }
     */
    public function operations(): array
    {
        $connections = $this->flickr->listConnections();
        $connectionKeys = $connections->map(fn ($connection) => $connection->connection_key)->values()->all();
        $since = now()->subDay();

        $pendingByConnection = $this->crawlTargets->countPendingGroupedByConnection($connectionKeys);

        $fetchRuns = [];
        $downloadBatches = [];
        $uploadBatches = [];
        $accountRows = [];
        $spiderRows = [];
        $accountsInCooldown = 0;
        $globalPause = false;

        foreach ($connections as $connection) {
            $key = (string) $connection->connection_key;
            $rateLimit = $this->flickr->rateLimitPresent($key);
            $pending = $pendingByConnection[$key] ?? 0;

            if ((int) ($rateLimit['cooldown_seconds_remaining'] ?? 0) > 0) {
                $accountsInCooldown++;
            }

            if (($rateLimit['global_pause'] ?? false) === true) {
                $globalPause = true;
            }

            $account = ConnectionPresenter::toArray($connection);
            $accountRows[] = [
                'connection_key' => $key,
                'public_id' => $account['public_id'],
                'label' => $account['fullname'] ?: ($account['username'] ?: $key),
                'pending_targets' => $pending,
                'rate_limit' => $rateLimit,
            ];

            $spiderRows[] = [
                'connection_key' => $key,
                'public_id' => $account['public_id'],
                'label' => $account['fullname'] ?: ($account['username'] ?: $key),
                'status' => $this->spider->statusForConnection($key),
            ];

            $runs = $this->flickr->crawlStatusRuns(
                $connection,
                'id',
                'desc',
                self::FETCH_RUNS_PER_CONNECTION,
                1,
            );

            foreach ($runs['data'] as $run) {
                $fetchRuns[] = $run;
            }

            $downloads = $this->transfers->batchesForConnection(
                $key,
                null,
                'download',
                true,
                'id',
                'desc',
                self::TRANSFER_BATCHES_PER_CONNECTION,
            );

            foreach ($downloads['data'] as $batch) {
                $downloadBatches[] = $batch;
            }

            $uploads = $this->transfers->batchesForConnection(
                $key,
                null,
                'upload',
                true,
                'id',
                'desc',
                self::TRANSFER_BATCHES_PER_CONNECTION,
            );

            foreach ($uploads['data'] as $batch) {
                $uploadBatches[] = $batch;
            }
        }

        $crawl = $this->crawlCounts($connectionKeys);
        $transfer = $this->transferCounts($since);

        return [
            'overview' => [
                'runs_running' => $crawl['runs_running'],
                'pending_targets' => $crawl['pending_targets'],
                'downloads_active' => $transfer['downloads_active'],
                'uploads_active' => $transfer['uploads_active'],
                'failed_transfers_24h' => $transfer['failed_transfers_24h'],
                'accounts_in_cooldown' => $accountsInCooldown,
                'global_pause' => $globalPause ? 1 : 0,
            ],
            'queues' => $this->queues->depths(),
            'target_breakdown' => $this->crawlTargets->statusTaskTypeCountsForRunningRuns($connectionKeys),
            'spider' => $spiderRows,
            'dependencies' => $this->dependencies->probeAll(),
            'databases' => $this->databaseUsage(),
            'accounts' => $accountRows,
            'fetch_runs' => $fetchRuns,
            'download_batches' => $downloadBatches,
            'upload_batches' => $uploadBatches,
        ];
    }

    /**
     * @param  list<string>  $connectionKeys
     * @return array{runs_running: int, pending_targets: int}
     */
    private function crawlCounts(array $connectionKeys): array
    {
        return [
            'runs_running' => $this->crawlRuns->countByConnectionsAndStatus($connectionKeys, 'running'),
            'pending_targets' => $this->crawlTargets->countPendingForConnections($connectionKeys),
        ];
    }

    /**
     * @return array{downloads_active: int, uploads_active: int, failed_transfers_24h: int}
     */
    private function transferCounts(\DateTimeInterface $since): array
    {
        return [
            'downloads_active' => $this->transfers->countBatchesByTypeAndStatus('download', 'running'),
            'uploads_active' => $this->transfers->countBatchesByTypeAndStatus('upload', 'running'),
            'failed_transfers_24h' => $this->transfers->countFailedItemsSince($since),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseUsage(): array
    {
        return $this->databases->snapshot();
    }
}
