<?php

declare(strict_types=1);

namespace Modules\Transfer\Repositories;

use App\Support\Query\QuerySorter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jooservices\LaravelRepository\Repositories\EloquentRepository;
use Jooservices\LaravelRepository\Traits\HasCrud;
use Jooservices\LaravelRepository\Traits\HasFilter;
use Modules\Transfer\Enums\TransferBatchStatus;
use Modules\Transfer\Enums\TransferItemStatus;
use Modules\Transfer\Models\TransferBatch;

/**
 * @extends EloquentRepository<TransferBatch>
 */
final class TransferBatchRepository extends EloquentRepository
{
    use HasCrud;
    use HasFilter;

    public function __construct(
        TransferBatch $model,
        private readonly QuerySorter $sorter,
        private readonly TransferItemRepository $items,
    ) {
        parent::__construct($model);
    }

    /**
     * @param  array{group_type: string, group_id: string|null, group_label: string}  $groupMeta
     * @param  list<string>  $sourceIds
     */
    public function createDownloadBatchWithItems(
        string $connectionKey,
        string $subjectNsid,
        array $groupMeta,
        array $sourceIds,
    ): TransferBatch {
        return DB::transaction(function () use ($connectionKey, $subjectNsid, $groupMeta, $sourceIds): TransferBatch {
            $batch = $this->newQuery()->create([
                'type' => 'download',
                'connection_key' => $connectionKey,
                'subject_nsid' => $subjectNsid,
                'group_type' => $groupMeta['group_type'],
                'group_id' => $groupMeta['group_id'],
                'group_label' => $groupMeta['group_label'],
                'status' => TransferBatchStatus::Running->value,
                'total_count' => count($sourceIds),
            ]);

            $this->items->createPendingBulk($batch->id, $sourceIds);

            return $batch;
        });
    }

    /**
     * @param  list<string>  $sourceIds
     */
    public function createUploadBatchWithItems(
        string $connectionKey,
        int $storageAccountId,
        array $sourceIds,
        ?string $subjectNsid = null,
        ?bool $deleteLocalAfterUpload = null,
    ): TransferBatch {
        return DB::transaction(function () use (
            $connectionKey,
            $storageAccountId,
            $sourceIds,
            $subjectNsid,
            $deleteLocalAfterUpload,
        ): TransferBatch {
            $batch = $this->newQuery()->create([
                'type' => 'upload',
                'connection_key' => $connectionKey,
                'subject_nsid' => $subjectNsid,
                'storage_account_id' => $storageAccountId,
                'status' => TransferBatchStatus::Running->value,
                'total_count' => count($sourceIds),
                'delete_local_after_upload' => $deleteLocalAfterUpload,
            ]);

            $this->items->createPendingBulk($batch->id, $sourceIds);

            return $batch;
        });
    }

    public function findById(int $id): ?TransferBatch
    {
        return $this->newQuery()->find($id);
    }

    public function lockById(int $id): ?TransferBatch
    {
        return $this->newQuery()->lockForUpdate()->find($id);
    }

    public function connectionKeyForId(int $id): ?string
    {
        $value = $this->newQuery()->whereKey($id)->value('connection_key');

        return $value !== null ? (string) $value : null;
    }

    public function countByTypeAndStatus(string $type, string $status): int
    {
        return $this->newQuery()
            ->where('type', $type)
            ->withStatus($status)
            ->count();
    }

    public function countActiveForConnection(string $connectionKey, string $type): int
    {
        return $this->newQuery()
            ->forConnection($connectionKey)
            ->where('type', $type)
            ->running()
            ->count();
    }

    /**
     * @param  list<string>  $connectionKeys
     * @return array<string, int>
     */
    public function countActiveGroupedByConnection(array $connectionKeys, string $type): array
    {
        if ($connectionKeys === []) {
            return [];
        }

        return $this->newQuery()
            ->whereIn('connection_key', $connectionKeys)
            ->where('type', $type)
            ->running()
            ->selectRaw('connection_key, count(*) as aggregate')
            ->groupBy('connection_key')
            ->pluck('aggregate', 'connection_key')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * @param  list<string>  $subjectNsids
     * @return Collection<int, TransferBatch>
     */
    public function runningDownloadsForSubjects(string $connectionKey, array $subjectNsids): Collection
    {
        if ($subjectNsids === []) {
            return collect();
        }

        return $this->newQuery()
            ->forConnection($connectionKey)
            ->where('type', 'download')
            ->running()
            ->whereIn('subject_nsid', $subjectNsids)
            ->get(['subject_nsid', 'completed_count', 'total_count']);
    }

    /**
     * @return Builder<TransferBatch>
     */
    public function queryForConnection(string $connectionKey): Builder
    {
        return $this->newQuery()->forConnection($connectionKey);
    }

    public function findWithItemsForConnection(int $id, string $connectionKey): ?TransferBatch
    {
        $batch = $this->newQuery()
            ->forConnection($connectionKey)
            ->with('items')
            ->find($id);

        return $batch instanceof TransferBatch ? $batch : null;
    }

    /**
     * @return Collection<int, TransferBatch>
     */
    public function listForConnection(
        string $connectionKey,
        ?string $status,
        ?string $type,
        bool $active,
        string $sort,
        string $direction,
        int $limit,
        array $allowedSorts,
    ): Collection {
        $query = $this->newQuery()->forConnection($connectionKey);

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($type !== null) {
            $query->where('type', $type);
        }

        if ($active) {
            $query->where(function (Builder $builder): void {
                $builder
                    ->where('status', TransferBatchStatus::Running->value)
                    ->orWhere('updated_at', '>=', now()->subHours(6));
            });
        }

        return $this->sorter
            ->apply($query, $sort, $direction, $allowedSorts)
            ->limit($limit)
            ->get();
    }

    public function updateCounts(TransferBatch $batch, int $completed, int $failed, TransferBatchStatus $status): void
    {
        $batch->update([
            'completed_count' => $completed,
            'failed_count' => $failed,
            'status' => $status->value,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function reconcile(int $batchId): ?array
    {
        return DB::transaction(function () use ($batchId): ?array {
            $batch = $this->lockById($batchId);
            if ($batch === null) {
                return null;
            }

            $completed = $this->items->countByStatus($batch->id, TransferItemStatus::Completed);
            $failed = $this->items->countByStatus($batch->id, TransferItemStatus::Failed);
            $processed = $completed + $failed;
            $status = TransferBatchStatus::tryFrom((string) $batch->status) ?? TransferBatchStatus::Running;

            if ($processed >= $batch->total_count) {
                $status = match (true) {
                    $failed >= $batch->total_count => TransferBatchStatus::Failed,
                    $failed > 0 => TransferBatchStatus::CompletedWithErrors,
                    default => TransferBatchStatus::Completed,
                };
            } elseif ($batch->status !== TransferBatchStatus::Running->value) {
                $status = TransferBatchStatus::Running;
            }

            $this->updateCounts($batch, $completed, $failed, $status);

            return [
                'batchId' => $batch->id,
                'type' => (string) $batch->type,
                'connectionKey' => (string) $batch->connection_key,
                'subjectNsid' => $batch->subject_nsid !== null ? (string) $batch->subject_nsid : null,
                'status' => $status->value,
                'totalCount' => (int) $batch->total_count,
                'completedCount' => $completed,
                'failedCount' => $failed,
                'sampleError' => $this->items->latestErrorMessage($batch->id),
                'groupType' => $batch->group_type !== null ? (string) $batch->group_type : null,
                'groupId' => $batch->group_id !== null ? (string) $batch->group_id : null,
                'groupLabel' => $batch->group_label !== null ? (string) $batch->group_label : null,
                'storageAccountId' => $batch->storage_account_id !== null ? (int) $batch->storage_account_id : null,
                'updatedAt' => now()->toISOString(),
            ];
        });
    }

    public function paginateForConnection(
        string $connectionKey,
        ?string $status,
        ?string $type,
        bool $active,
        string $sort,
        string $direction,
        int $limit,
        array $allowedSorts,
    ): LengthAwarePaginator {
        $query = $this->newQuery()->forConnection($connectionKey);

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($type !== null) {
            $query->where('type', $type);
        }

        if ($active) {
            $query->where(function (Builder $builder): void {
                $builder
                    ->where('status', TransferBatchStatus::Running->value)
                    ->orWhere('updated_at', '>=', now()->subHours(6));
            });
        }

        return $this->sorter
            ->apply($query, $sort, $direction, $allowedSorts)
            ->paginate($limit);
    }
}
