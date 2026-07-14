<?php

declare(strict_types=1);

namespace Modules\Storage\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use JOOservices\LaravelController\Http\Controllers\BaseApiController;
use Modules\Storage\Http\Resources\StorageQuotaSnapshotResource;
use Modules\Storage\Services\StorageQuotaQueryService;

final class StorageQuotaController extends BaseApiController
{
    public function __construct(
        private readonly StorageQuotaQueryService $quotas,
    ) {}

    public function index(): JsonResponse
    {
        return $this->success(StorageQuotaSnapshotResource::make($this->quotas->snapshot()));
    }
}
