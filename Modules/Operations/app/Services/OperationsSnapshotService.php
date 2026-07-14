<?php

declare(strict_types=1);

namespace Modules\Operations\Services;

use App\Repositories\Crawler\CrawlRunQueryRepository;
use App\Repositories\Crawler\CrawlTargetQueryRepository;
use Modules\Flickr\Services\CrawlStatusQueryService;
use Modules\Flickr\Services\FlickrOAuthService;
use Modules\Flickr\Services\RateLimit\Presenter;
use Modules\Flickr\Support\ConnectionPresenter;
use Modules\Transfer\Services\TransferCountsQueryService;
use Modules\Transfer\Services\TransferProgressQueryService;

final class OperationsSnapshotService
{
    private const int FETCH_RUNS_PER_CONNECTION = 10;

    private const int TRANSFER_BATCHES_PER_CONNECTION = 20;

    public function __construct(
        private readonly FlickrOAuthService $oauth,
        private readonly CrawlStatusQueryService $crawlStatus,
        private readonly TransferProgressQueryService $transferProgress,
        private readonly TransferCountsQueryService $transferCounts,
        private readonly CrawlRunQueryRepository $crawlRuns,
        private readonly CrawlTargetQueryRepository $crawlTargets,
        private readonly Presenter $rateLimit,
        private readonly DatabaseUsageService $databases,
        private readonly ServicesDependencyProbeService $dependencies,
    ) {}

    /**
     * @return array{
     *     overview: array<string, int>,
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
    public function snapshot(): array
    {
        $connections = $this->oauth->listConnections();
        $connectionKeys = $connections->map(fn ($connection) => $connection->connection_key)->values()->all();
        $since = now()->subDay();

        $pendingByConnection = $this->crawlTargets->countPendingGroupedByConnection($connectionKeys);

        $fetchRuns = [];
        $downloadBatches = [];
        $uploadBatches = [];
        $accountRows = [];
        $accountsInCooldown = 0;

        foreach ($connections as $connection) {
            $key = (string) $connection->connection_key;
            $rateLimit = $this->rateLimit->present($key);
            $pending = $pendingByConnection[$key] ?? 0;

            if ((int) ($rateLimit['cooldown_seconds_remaining'] ?? 0) > 0) {
                $accountsInCooldown++;
            }

            $account = ConnectionPresenter::toArray($connection);
            $accountRows[] = [
                'connection_key' => $key,
                'public_id' => $account['public_id'],
                'label' => $account['fullname'] ?: ($account['username'] ?: $key),
                'pending_targets' => $pending,
                'rate_limit' => $rateLimit,
            ];

            $runs = $this->crawlStatus->runs(
                $connection,
                'id',
                'desc',
                self::FETCH_RUNS_PER_CONNECTION,
                1,
            );

            foreach ($runs['data'] as $run) {
                $fetchRuns[] = $run;
            }

            $downloads = $this->transferProgress->index(
                $connection,
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

            $uploads = $this->transferProgress->index(
                $connection,
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

        $usage = $this->databases->snapshot();

        return [
            'overview' => [
                'runs_running' => $this->crawlRuns->countByConnectionsAndStatus($connectionKeys, 'running'),
                'pending_targets' => $this->crawlTargets->countPendingForConnections($connectionKeys),
                'downloads_active' => $this->transferCounts->countBatchesByTypeAndStatus('download', 'running'),
                'uploads_active' => $this->transferCounts->countBatchesByTypeAndStatus('upload', 'running'),
                'failed_transfers_24h' => $this->transferCounts->countFailedItemsSince($since),
                'accounts_in_cooldown' => $accountsInCooldown,
            ],
            'dependencies' => $this->dependencies->probeAll(),
            'databases' => $usage,
            'accounts' => $accountRows,
            'fetch_runs' => $fetchRuns,
            'download_batches' => $downloadBatches,
            'upload_batches' => $uploadBatches,
        ];
    }
}
