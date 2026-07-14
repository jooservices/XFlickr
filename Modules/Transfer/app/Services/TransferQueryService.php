<?php

declare(strict_types=1);

namespace Modules\Transfer\Services;

use App\Support\Query\QuerySorter;
use Modules\Crawler\Models\Connection;
use Modules\Transfer\Enums\TransferBatchStatus;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Repositories\StoredFileRepository;
use Modules\Transfer\Repositories\TransferBatchRepository;
use Modules\Transfer\Repositories\TransferItemRepository;

final class TransferQueryService
{
    /** @var list<string> */
    private const BATCH_SORTS = ['id', 'type', 'subject_nsid', 'status', 'total_count', 'completed_count', 'failed_count', 'created_at'];

    public function __construct(
        private readonly StoredFileRepository $storedFiles,
        private readonly TransferBatchRepository $batches,
        private readonly TransferItemRepository $items,
        private readonly TransferBatchReconciler $batchReconciler,
        private readonly QuerySorter $sorter,
    ) {}

    public function countStoredFiles(): int
    {
        return $this->storedFiles->countAll();
    }

    public function countBatchesByTypeAndStatus(string $type, string $status): int
    {
        return $this->batches->countByTypeAndStatus($type, $status);
    }

    /**
     * @param  list<string>  $connectionKeys
     * @return array<string, int>
     */
    public function countActiveBatchesGroupedByConnection(array $connectionKeys, string $type): array
    {
        return $this->batches->countActiveGroupedByConnection($connectionKeys, $type);
    }

    public function countFailedItemsSince(\DateTimeInterface $since): int
    {
        return $this->items->countFailedSince($since);
    }

    /**
     * @param  list<string>  $connectionKeys
     * @return array<string, int>
     */
    public function countFailedItemsGroupedByConnectionSince(array $connectionKeys, \DateTimeInterface $since): array
    {
        return $this->items->countFailedGroupedByConnectionSince($connectionKeys, $since);
    }

    /**
     * @return array{batch: array<string, mixed>, items: mixed}|null
     */
    public function show(Connection $connection, TransferBatch $batch): ?array
    {
        if ($batch->connection_key !== $connection->connection_key) {
            return null;
        }

        $batch->load('items');

        return [
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
        ];
    }

    /**
     * @return array{data: mixed}
     */
    public function index(
        Connection $connection,
        ?string $status,
        ?string $type,
        bool $active,
        string $sort,
        string $direction,
        int $limit,
    ): array {
        $query = $this->batches->queryForConnection($connection->connection_key);

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($type !== null) {
            $query->where('type', $type);
        }

        if ($active) {
            $query->where(function ($builder): void {
                $builder
                    ->where('status', TransferBatchStatus::Running->value)
                    ->orWhere('updated_at', '>=', now()->subHours(6));
            });
        }

        $query = $this->sorter->apply($query, $sort, $direction, self::BATCH_SORTS);
        $batchList = $query->limit($limit)->get();

        return [
            'data' => $batchList->map(fn (TransferBatch $batch): array => [
                ...$batch->toArray(),
                'sample_error' => $batch->failed_count > 0
                    ? $this->batchReconciler->sampleError($batch->id)
                    : null,
            ]),
        ];
    }
}
