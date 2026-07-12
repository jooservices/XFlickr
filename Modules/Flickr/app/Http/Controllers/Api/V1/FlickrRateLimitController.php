<?php

declare(strict_types=1);

namespace Modules\Flickr\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use JOOservices\LaravelController\Http\Controllers\BaseApiController;
use Modules\Flickr\Http\Resources\FlickrRateLimitSnapshotResource;
use Modules\Flickr\Services\FlickrRateLimitQueryService;

final class FlickrRateLimitController extends BaseApiController
{
    public function __construct(
        private readonly FlickrRateLimitQueryService $rateLimits,
    ) {}

    public function index(): JsonResponse
    {
        return $this->success(FlickrRateLimitSnapshotResource::make($this->rateLimits->snapshot()));
    }
}
