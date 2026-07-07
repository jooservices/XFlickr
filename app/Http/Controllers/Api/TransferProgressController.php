<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ListTransferBatchesRequest;
use App\Models\TransferBatch;
use App\Services\Transfer\TransferItemRetryService;
use App\Services\Transfer\TransferProgressQueryService;
use Illuminate\Http\JsonResponse;
use JOOservices\XFlickrCrawler\Models\Connection;

final class TransferProgressController
{
    public function __construct(
        private readonly TransferProgressQueryService $transferProgress,
        private readonly TransferItemRetryService $transferRetry,
    ) {}

    public function show(Connection $connection, TransferBatch $batch): JsonResponse
    {
        $payload = $this->transferProgress->show($connection, $batch);

        if ($payload === null) {
            abort(404);
        }

        return response()->json($payload);
    }

    public function index(ListTransferBatchesRequest $request, Connection $connection): JsonResponse
    {
        return response()->json($this->transferProgress->index(
            $connection,
            $request->status(),
            $request->type(),
            $request->isActive(),
            $request->sort(),
            $request->direction(),
            $request->limit(),
        ));
    }

    public function retryItem(Connection $connection, TransferBatch $batch, string $flickrPhotoId): JsonResponse
    {
        $this->transferRetry->retry($connection, $batch, $flickrPhotoId);

        return response()->json(['status' => 'queued']);
    }
}
