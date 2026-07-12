<?php

declare(strict_types=1);

namespace Modules\Flickr\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use JOOservices\LaravelController\Http\Controllers\BaseApiController;
use Modules\Flickr\Http\Requests\Api\ShowFlickrRateLimitUsageRequest;
use Modules\Flickr\Http\Resources\FlickrRateLimitUsageResource;
use Modules\Flickr\Services\FlickrRateLimitUsageQueryService;

final class FlickrRateLimitUsageController extends BaseApiController
{
    public function __construct(
        private readonly FlickrRateLimitUsageQueryService $usage,
    ) {}

    public function show(ShowFlickrRateLimitUsageRequest $request): JsonResponse
    {
        return $this->success(FlickrRateLimitUsageResource::make($this->usage->usage(
            $request->connectionKey(),
            $request->hours(),
        )));
    }
}
