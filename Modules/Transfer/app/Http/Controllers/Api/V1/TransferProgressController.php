<?php

declare(strict_types=1);

namespace Modules\Transfer\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use JOOservices\LaravelController\Http\Controllers\BaseApiController;
use Modules\Crawler\Models\Connection;
use Modules\Transfer\Http\Requests\Api\ListTransferBatchesRequest;
use Modules\Transfer\Http\Resources\TransferBatchDetailResource;
use Modules\Transfer\Http\Resources\TransferBatchResource;
use Modules\Transfer\Http\Resources\TransferHistoryItemResource;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Services\TransferBatchService;

final class TransferProgressController extends BaseApiController
{
    public function __construct(
        private readonly TransferBatchService $transfers,
    ) {}

    public function show(Connection $connection, TransferBatch $batch): JsonResponse
    {
        $payload = $this->transfers->batchDetail($connection->connection_key, $batch);

        if ($payload === null) {
            return $this->notFound();
        }

        return $this->success(TransferBatchDetailResource::make($payload));
    }

    public function index(ListTransferBatchesRequest $request, Connection $connection): JsonResponse
    {
        $paginator = $this->transfers->batchesForConnectionPaginated(
            $connection->connection_key,
            $request->status(),
            $request->type(),
            $request->isActive(),
            $request->sort(),
            $request->direction(),
            $request->limit(),
        );

        return $this->success(TransferBatchResource::collection($paginator));
    }

    public function itemIndex(ListTransferBatchesRequest $request, Connection $connection): JsonResponse
    {
        $paginator = $this->transfers->transferHistoryForConnectionPaginated(
            $connection->connection_key,
            $request->status(),
            $request->type(),
            $request->limit(),
        );

        return $this->success(TransferHistoryItemResource::collection($paginator));
    }

    public function retryItem(Connection $connection, TransferBatch $batch, string $flickrPhotoId): JsonResponse
    {
        $this->transfers->retryItem($connection->connection_key, $batch, $flickrPhotoId);

        return $this->accepted([], 'Queued');
    }

    public function retryBatch(Connection $connection, TransferBatch $batch): JsonResponse
    {
        $result = $this->transfers->retryFailedItems($connection->connection_key, $batch);

        return $this->accepted([
            'count' => $result->queued,
            'queued_count' => $result->queued,
            'skipped' => $result->skipped,
        ], "Queued {$result->queued} failed item(s) for retry.");
    }
}
