<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\DashboardService;
use App\Services\Flickr\FlickrRateLimitPresenter;
use Illuminate\Http\JsonResponse;

final class DashboardController
{
    public function snapshot(DashboardService $dashboard, FlickrRateLimitPresenter $rateLimit): JsonResponse
    {
        return response()->json([
            'data' => $dashboard->snapshot($rateLimit),
        ]);
    }
}
