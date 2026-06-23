<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ListTransferBatchesRequest;
use App\Models\TransferBatch;
use App\Services\Transfer\TransferProgressQueryService;
use Illuminate\Http\JsonResponse;
use JOOservices\XFlickrCrawler\Models\Connection;

final class TransferProgressController
{
    public function __construct(
        private readonly TransferProgressQueryService $transferProgress,
    ) {}

    public function show(Connection $connection, TransferBatch $batch): JsonResponse
    {
        return $this->transferProgress->show($connection, $batch);
    }

    public function index(ListTransferBatchesRequest $request, Connection $connection): JsonResponse
    {
        return $this->transferProgress->index($request, $connection);
    }
}
