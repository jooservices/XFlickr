<?php

declare(strict_types=1);

namespace Modules\Operations\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use JOOservices\LaravelController\Http\Controllers\BaseApiController;
use Modules\Operations\Http\Resources\DashboardSnapshotResource;
use Modules\Operations\Services\DashboardService;

final class DashboardController extends BaseApiController
{
    public function __construct(
        private readonly DashboardService $dashboard,
    ) {}

    public function snapshot(): JsonResponse
    {
        return $this->success(DashboardSnapshotResource::make($this->dashboard->snapshot()));
    }
}
