<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ShowFlickrRateLimitUsageRequest;
use App\Services\Flickr\FlickrRateLimitUsageQueryService;
use Illuminate\Http\JsonResponse;

final class FlickrRateLimitUsageController
{
    public function __construct(
        private readonly FlickrRateLimitUsageQueryService $usage,
    ) {}

    public function show(ShowFlickrRateLimitUsageRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->usage->usage(
                $request->connectionKey(),
                $request->hours(),
            ),
        ]);
    }
}
