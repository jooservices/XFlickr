<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ListCrawlLogsRequest;
use App\Http\Requests\Api\ListCrawlRunsRequest;
use App\Services\Crawl\CrawlStatusQueryService;
use App\Services\Flickr\FlickrRateLimitPresenter;
use Illuminate\Http\JsonResponse;
use JOOservices\XFlickrCrawler\Models\Connection;

final class CrawlStatusController
{
    public function __construct(
        private readonly CrawlStatusQueryService $crawlStatus,
    ) {}

    public function summary(Connection $connection, FlickrRateLimitPresenter $rateLimit): JsonResponse
    {
        return $this->crawlStatus->summary($connection, $rateLimit);
    }

    public function runs(ListCrawlRunsRequest $request, Connection $connection): JsonResponse
    {
        return $this->crawlStatus->runs($request, $connection);
    }

    public function logs(ListCrawlLogsRequest $request, Connection $connection): JsonResponse
    {
        return $this->crawlStatus->logs($request, $connection);
    }
}
