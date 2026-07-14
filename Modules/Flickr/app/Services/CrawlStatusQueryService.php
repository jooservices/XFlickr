<?php

declare(strict_types=1);

namespace Modules\Flickr\Services;

use App\Repositories\Crawler\ApiLogQueryRepository;
use App\Repositories\Crawler\CrawlRunQueryRepository;
use App\Repositories\Crawler\CrawlTargetQueryRepository;
use App\Support\Query\QuerySorter;
use Modules\Crawler\Models\Connection;
use Modules\Crawler\Support\XFlickrConfig;
use Modules\Flickr\Services\RateLimit\Presenter;

final class CrawlStatusQueryService
{
    /** @var list<string> */
    private const RUN_SORTS = ['id', 'crawl_type', 'subject_nsid', 'status', 'photos_discovered', 'api_calls', 'started_at'];

    public function __construct(
        private readonly CrawlRunQueryRepository $crawlRuns,
        private readonly CrawlTargetQueryRepository $crawlTargets,
        private readonly ApiLogQueryRepository $apiLogs,
        private readonly Presenter $rateLimit,
        private readonly QuerySorter $sorter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(Connection $connection): array
    {
        $connectionKey = $connection->connection_key;

        return [
            'connection_key' => $connectionKey,
            'runs' => $this->crawlRuns->statusCountsForConnection($connectionKey),
            'pending_targets' => $this->crawlTargets->countPendingForConnection($connectionKey),
            'global_pause' => XFlickrConfig::globalPause(),
            'rate_limit' => $this->rateLimit->present($connectionKey),
        ];
    }

    /**
     * @return array{data: list<mixed>, meta: array<string, mixed>}
     */
    public function runs(Connection $connection, string $sort, string $direction, int $perPage, int $page): array
    {
        $query = $this->crawlRuns->queryForConnection($connection->connection_key);
        $query = $this->sorter->apply($query, $sort, $direction, self::RUN_SORTS);
        $paginator = $this->crawlRuns->paginateQuery($query, $perPage, $page);

        return [
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'sort' => $this->sorter->resolveSort($sort, self::RUN_SORTS),
                'direction' => $this->sorter->resolveDirection($direction),
            ],
        ];
    }

    /**
     * @return array{data: list<mixed>, meta: array<string, mixed>}
     */
    public function logs(Connection $connection, int $perPage, int $page): array
    {
        $paginator = $this->apiLogs->paginateForConnection(
            $connection->connection_key,
            $perPage,
            $page,
        );

        return [
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
