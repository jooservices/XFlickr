<?php

declare(strict_types=1);

namespace Modules\Flickr\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use JOOservices\LaravelController\Http\Controllers\BaseApiController;
use Modules\Crawler\Models\Connection;
use Modules\Flickr\Http\Requests\Api\ListCrawlLogsRequest;
use Modules\Flickr\Http\Requests\Api\ListCrawlRunsRequest;
use Modules\Flickr\Http\Resources\CrawlLogResource;
use Modules\Flickr\Http\Resources\CrawlRunResource;
use Modules\Flickr\Http\Resources\CrawlSummaryResource;
use Modules\Flickr\Services\CrawlStatusQueryService;

final class CrawlStatusController extends BaseApiController
{
    public function __construct(
        private readonly CrawlStatusQueryService $crawlStatus,
    ) {}

    public function summary(Connection $connection): JsonResponse
    {
        return $this->success(CrawlSummaryResource::make($this->crawlStatus->summary($connection)));
    }

    public function runs(ListCrawlRunsRequest $request, Connection $connection): JsonResponse
    {
        return $this->successList(
            $this->crawlStatus->runs(
                $connection,
                $request->sort(),
                $request->direction(),
                $request->perPage(),
                $request->page(),
            ),
            CrawlRunResource::class,
        );
    }

    public function logs(ListCrawlLogsRequest $request, Connection $connection): JsonResponse
    {
        return $this->successList(
            $this->crawlStatus->logs(
                $connection,
                $request->perPage(),
                $request->page(),
            ),
            CrawlLogResource::class,
        );
    }

    /**
     * @param  array{data?: mixed, meta?: array<string, mixed>}  $payload
     * @param  class-string  $resourceClass
     */
    private function successList(array $payload, string $resourceClass): JsonResponse
    {
        $meta = $payload['meta'] ?? [];
        $rows = $payload['data'] ?? [];

        return $this->success(
            $resourceClass::collection(is_array($rows) || $rows instanceof Collection ? $rows : []),
            meta: is_array($meta) ? $meta : [],
        );
    }
}
