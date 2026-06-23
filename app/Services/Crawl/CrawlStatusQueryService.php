<?php

declare(strict_types=1);

namespace App\Services\Crawl;

use App\Http\Requests\Api\ListCrawlLogsRequest;
use App\Http\Requests\Api\ListCrawlRunsRequest;
use App\Repositories\Crawler\ApiLogQueryRepository;
use App\Repositories\Crawler\CrawlRunQueryRepository;
use App\Repositories\Crawler\CrawlTargetQueryRepository;
use App\Services\Flickr\FlickrRateLimitPresenter;
use App\Support\Query\QuerySorter;
use Illuminate\Http\JsonResponse;
use JOOservices\XFlickrCrawler\Models\Connection;
use JOOservices\XFlickrCrawler\Support\XFlickrConfig;

final class CrawlStatusQueryService
{
    /** @var list<string> */
    private const RUN_SORTS = ['id', 'crawl_type', 'subject_nsid', 'status', 'photos_discovered', 'api_calls', 'started_at'];

    public function __construct(
        private readonly CrawlRunQueryRepository $crawlRuns,
        private readonly CrawlTargetQueryRepository $crawlTargets,
        private readonly ApiLogQueryRepository $apiLogs,
        private readonly QuerySorter $sorter,
    ) {}

    public function summary(Connection $connection, FlickrRateLimitPresenter $rateLimit): JsonResponse
    {
        $connectionKey = $connection->connection_key;

        return response()->json([
            'connection_key' => $connectionKey,
            'runs' => $this->crawlRuns->statusCountsForConnection($connectionKey),
            'pending_targets' => $this->crawlTargets->countPendingForConnection($connectionKey),
            'global_pause' => XFlickrConfig::globalPause(),
            'rate_limit' => $rateLimit->present($connectionKey),
        ]);
    }

    public function runs(ListCrawlRunsRequest $request, Connection $connection): JsonResponse
    {
        $query = $this->crawlRuns->queryForConnection($connection->connection_key);
        $query = $this->sorter->apply($query, $request->sort(), $request->direction(), self::RUN_SORTS);
        $paginator = $this->crawlRuns->paginateQuery($query, $request->perPage(), $request->page());

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'sort' => $this->sorter->resolveSort($request->sort(), self::RUN_SORTS),
                'direction' => $this->sorter->resolveDirection($request->direction()),
            ],
        ]);
    }

    public function logs(ListCrawlLogsRequest $request, Connection $connection): JsonResponse
    {
        $paginator = $this->apiLogs->paginateForConnection(
            $connection->connection_key,
            $request->perPage(),
            $request->page(),
        );

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
