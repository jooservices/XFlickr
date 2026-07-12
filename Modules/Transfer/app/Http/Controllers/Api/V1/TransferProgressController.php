<?php

declare(strict_types=1);

namespace Modules\Transfer\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use JOOservices\LaravelController\Http\Controllers\BaseApiController;
use JOOservices\XFlickrCrawler\Models\Connection;
use Modules\Transfer\Http\Requests\Api\ListTransferBatchesRequest;
use Modules\Transfer\Http\Resources\TransferBatchDetailResource;
use Modules\Transfer\Http\Resources\TransferBatchResource;
use Modules\Transfer\Http\Resources\TransferRetryAcceptedResource;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Services\TransferItemRetryService;
use Modules\Transfer\Services\TransferProgressQueryService;

final class TransferProgressController extends BaseApiController
{
    public function __construct(
        private readonly TransferProgressQueryService $transferProgress,
        private readonly TransferItemRetryService $transferRetry,
    ) {}

    public function show(Connection $connection, TransferBatch $batch): JsonResponse
    {
        $payload = $this->transferProgress->show($connection, $batch);

        if ($payload === null) {
            return $this->notFound();
        }

        return $this->success(TransferBatchDetailResource::make($payload));
    }

    public function index(ListTransferBatchesRequest $request, Connection $connection): JsonResponse
    {
        $payload = $this->transferProgress->index(
            $connection,
            $request->status(),
            $request->type(),
            $request->isActive(),
            $request->sort(),
            $request->direction(),
            $request->limit(),
        );

        $rows = $payload['data'] ?? [];

        return $this->success(TransferBatchResource::collection($rows));
    }

    public function retryItem(Connection $connection, TransferBatch $batch, string $flickrPhotoId): JsonResponse
    {
        $this->transferRetry->retry($connection, $batch, $flickrPhotoId);

        return $this->accepted(TransferRetryAcceptedResource::make([]), 'Queued');
    }
}
