<?php

declare(strict_types=1);

namespace Modules\Operations\Services;

use Modules\Flickr\Services\CrawlStatusQueryService;
use Modules\Flickr\Services\FlickrOAuthService;
use Modules\Transfer\Services\TransferProgressQueryService;

final class OperationsSnapshotService
{
    public function __construct(
        private readonly FlickrOAuthService $oauth,
        private readonly CrawlStatusQueryService $crawlStatus,
        private readonly TransferProgressQueryService $transferProgress,
    ) {}

    /**
     * @return array{
     *     fetch_runs: list<mixed>,
     *     download_batches: list<mixed>,
     *     upload_batches: list<mixed>,
     * }
     */
    public function snapshot(): array
    {
        $fetchRuns = [];
        $downloadBatches = [];
        $uploadBatches = [];

        foreach ($this->oauth->listConnections() as $connection) {
            $runs = $this->crawlStatus->runs($connection, 'id', 'desc', 10, 1);

            foreach ($runs['data'] as $run) {
                if (is_object($run) && isset($run->status) && $run->status === 'running') {
                    $fetchRuns[] = $run;
                }
            }

            $downloads = $this->transferProgress->index(
                $connection,
                null,
                'download',
                true,
                'id',
                'desc',
                20,
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
                20,
            );

            foreach ($uploads['data'] as $batch) {
                $uploadBatches[] = $batch;
            }
        }

        return [
            'fetch_runs' => $fetchRuns,
            'download_batches' => $downloadBatches,
            'upload_batches' => $uploadBatches,
        ];
    }
}
