<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\Flickr\FlickrRateLimitQueryService;
use Illuminate\Http\JsonResponse;

final class FlickrRateLimitController
{
    public function __construct(
        private readonly FlickrRateLimitQueryService $rateLimits,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->rateLimits->snapshot(),
        ]);
    }
}
