<?php

declare(strict_types=1);

namespace Modules\Operations\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use JOOservices\LaravelController\Http\Controllers\BaseApiController;
use Modules\Operations\Http\Resources\OperationsSnapshotResource;
use Modules\Operations\Services\OperationsSnapshotService;

final class OperationsSnapshotController extends BaseApiController
{
    public function __construct(
        private readonly OperationsSnapshotService $operations,
    ) {}

    public function show(): JsonResponse
    {
        return $this->success(OperationsSnapshotResource::make($this->operations->snapshot()));
    }
}
