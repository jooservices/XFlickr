<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ListCrawlLogsRequest;
use App\Http\Requests\Api\ListCrawlRunsRequest;
use App\Services\Crawl\CrawlStatusQueryService;
use Illuminate\Http\JsonResponse;
use JOOservices\XFlickrCrawler\Models\Connection;

final class CrawlStatusController
{
    public function __construct(
        private readonly CrawlStatusQueryService $crawlStatus,
    ) {}

    public function summary(Connection $connection): JsonResponse
    {
        return response()->json($this->crawlStatus->summary($connection));
    }

    public function runs(ListCrawlRunsRequest $request, Connection $connection): JsonResponse
    {
        return response()->json($this->crawlStatus->runs(
            $connection,
            $request->sort(),
            $request->direction(),
            $request->perPage(),
            $request->page(),
        ));
    }

    public function logs(ListCrawlLogsRequest $request, Connection $connection): JsonResponse
    {
        return response()->json($this->crawlStatus->logs(
            $connection,
            $request->perPage(),
            $request->page(),
        ));
    }
}
