<?php

declare(strict_types=1);

namespace Modules\Spider\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use JOOservices\LaravelController\Http\Controllers\BaseApiController;
use JOOservices\XFlickrCrawler\Models\Connection;
use Modules\Spider\Http\Resources\SpiderStatusResource;
use Modules\Spider\Services\SpiderPlannerService;

final class SpiderStatusController extends BaseApiController
{
    public function show(Connection $connection, SpiderPlannerService $planner): JsonResponse
    {
        return $this->success(SpiderStatusResource::make(
            $planner->statusForConnection($connection->connection_key),
        ));
    }
}
