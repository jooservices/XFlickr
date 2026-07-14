<?php

declare(strict_types=1);

namespace Modules\Operations\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use JOOservices\LaravelController\Http\Controllers\BaseApiController;
use Modules\Operations\Http\Resources\OperationsSnapshotResource;
use Modules\Operations\Services\SnapshotService;

final class OperationsSnapshotController extends BaseApiController
{
    public function __construct(
        private readonly SnapshotService $operations,
    ) {}

    public function show(): JsonResponse
    {
        return $this->success(OperationsSnapshotResource::make($this->operations->operations()));
    }
}
