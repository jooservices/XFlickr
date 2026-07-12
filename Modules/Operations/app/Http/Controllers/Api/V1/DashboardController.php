<?php

declare(strict_types=1);

namespace Modules\Operations\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use JOOservices\LaravelController\Http\Controllers\BaseApiController;
use Modules\Flickr\Services\FlickrRateLimitPresenter;
use Modules\Operations\Http\Resources\DashboardSnapshotResource;
use Modules\Operations\Services\DashboardService;

final class DashboardController extends BaseApiController
{
    public function snapshot(DashboardService $dashboard, FlickrRateLimitPresenter $rateLimit): JsonResponse
    {
        return $this->success(DashboardSnapshotResource::make($dashboard->snapshot($rateLimit)));
    }
}
