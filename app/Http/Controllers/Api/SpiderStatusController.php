<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\Flickr\SpiderPlannerService;
use Illuminate\Http\JsonResponse;
use JOOservices\XFlickrCrawler\Models\Connection;

final class SpiderStatusController
{
    public function show(Connection $connection, SpiderPlannerService $planner): JsonResponse
    {
        return response()->json($planner->statusForConnection($connection->connection_key));
    }
}
