<?php

declare(strict_types=1);

namespace App\Services\Transfer;

use App\Enums\TransferBatchStatus;
use App\Http\Requests\Api\ListTransferBatchesRequest;
use App\Models\TransferBatch;
use App\Repositories\TransferBatchRepository;
use App\Support\Query\QuerySorter;
use Illuminate\Http\JsonResponse;
use JOOservices\XFlickrCrawler\Models\Connection;

final class TransferProgressQueryService
{
    /** @var list<string> */
    private const BATCH_SORTS = ['id', 'type', 'subject_nsid', 'status', 'total_count', 'completed_count', 'failed_count', 'created_at'];

    public function __construct(
        private readonly TransferBatchRepository $batches,
        private readonly TransferBatchReconciler $batchReconciler,
        private readonly QuerySorter $sorter,
    ) {}

    public function show(Connection $connection, TransferBatch $batch): JsonResponse
    {
        if ($batch->connection_key !== $connection->connection_key) {
            abort(404);
        }

        $batch->load('items');

        return response()->json([
            'batch' => [
                'id' => $batch->id,
                'type' => $batch->type,
                'status' => $batch->status,
                'total_count' => $batch->total_count,
                'completed_count' => $batch->completed_count,
                'failed_count' => $batch->failed_count,
                'connection_key' => $batch->connection_key,
                'subject_nsid' => $batch->subject_nsid,
                'group_type' => $batch->group_type,
                'group_id' => $batch->group_id,
                'group_label' => $batch->group_label,
                'storage_account_id' => $batch->storage_account_id,
            ],
            'items' => $batch->items,
        ]);
    }

    public function index(ListTransferBatchesRequest $request, Connection $connection): JsonResponse
    {
        $query = $this->batches->queryForConnection($connection->connection_key);

        if ($status = $request->status()) {
            $query->where('status', $status);
        }

        if ($type = $request->type()) {
            $query->where('type', $type);
        }

        if ($request->isActive()) {
            $query->where(function ($builder): void {
                $builder
                    ->where('status', TransferBatchStatus::Running->value)
                    ->orWhere('updated_at', '>=', now()->subHours(6));
            });
        }

        $query = $this->sorter->apply($query, $request->sort(), $request->direction(), self::BATCH_SORTS);
        $batchList = $query->limit($request->limit())->get();

        if ($request->isActive()) {
            foreach ($batchList as $batch) {
                $this->batchReconciler->reconcile($batch);
            }

            $batchList = $batchList->map(fn (TransferBatch $batch): TransferBatch => $batch->fresh());
        }

        return response()->json([
            'data' => $batchList->map(fn (TransferBatch $batch): array => [
                ...$batch->toArray(),
                'sample_error' => $batch->failed_count > 0
                    ? $this->batchReconciler->sampleError($batch->id)
                    : null,
            ]),
        ]);
    }
}
