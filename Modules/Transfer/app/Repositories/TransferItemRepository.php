<?php

declare(strict_types=1);

namespace Modules\Transfer\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Jooservices\LaravelRepository\Repositories\EloquentRepository;
use Jooservices\LaravelRepository\Traits\HasCrud;
use Jooservices\LaravelRepository\Traits\HasFilter;
use Modules\Transfer\Enums\TransferItemStatus;
use Modules\Transfer\Models\TransferItem;

/**
 * @extends EloquentRepository<TransferItem>
 */
final class TransferItemRepository extends EloquentRepository
{
    use HasCrud;
    use HasFilter;

    public function __construct(TransferItem $model)
    {
        parent::__construct($model);
    }

    public function createPending(int $batchId, string $sourceId): TransferItem
    {
        return $this->newQuery()->create([
            'transfer_batch_id' => $batchId,
            'source_id' => $sourceId,
            'status' => TransferItemStatus::Pending->value,
        ]);
    }

    /**
     * @param  list<string>  $sourceIds
     */
    public function createPendingBulk(int $batchId, array $sourceIds): void
    {
        if ($sourceIds === []) {
            return;
        }

        $now = now();

        $rows = array_map(
            static fn (string $sourceId): array => [
                'transfer_batch_id' => $batchId,
                'source_id' => $sourceId,
                'status' => TransferItemStatus::Pending->value,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $sourceIds,
        );

        foreach (array_chunk($rows, 500) as $chunk) {
            $this->newQuery()->insert($chunk);
        }
    }

    public function updateStatus(int $batchId, string $sourceId, TransferItemStatus $status, ?string $error = null): void
    {
        $this->newQuery()
            ->where('transfer_batch_id', $batchId)
            ->where('source_id', $sourceId)
            ->update([
                'status' => $status->value,
                'error_message' => $error,
            ]);
    }

    public function markCompleted(int $batchId, string $sourceId): void
    {
        $this->updateStatus($batchId, $sourceId, TransferItemStatus::Completed);
    }

    public function countByStatus(int $batchId, TransferItemStatus $status): int
    {
        return $this->newQuery()
            ->where('transfer_batch_id', $batchId)
            ->withStatus($status)
            ->count();
    }

    public function countFailedSince(\DateTimeInterface $since): int
    {
        return $this->newQuery()
            ->failed()
            ->where('created_at', '>=', $since)
            ->count();
    }

    public function countFailedForConnectionSince(string $connectionKey, \DateTimeInterface $since): int
    {
        return $this->newQuery()
            ->failed()
            ->where('created_at', '>=', $since)
            ->whereHas('batch', fn ($q) => $q->where('connection_key', $connectionKey))
            ->count();
    }

    /**
     * @param  list<string>  $connectionKeys
     * @return array<string, int>
     */
    public function countFailedGroupedByConnectionSince(array $connectionKeys, \DateTimeInterface $since): array
    {
        if ($connectionKeys === []) {
            return [];
        }

        return $this->newQuery()
            ->join('transfer_batches', 'transfer_items.transfer_batch_id', '=', 'transfer_batches.id')
            ->where('transfer_items.status', TransferItemStatus::Failed->value)
            ->where('transfer_items.created_at', '>=', $since)
            ->whereIn('transfer_batches.connection_key', $connectionKeys)
            ->selectRaw('transfer_batches.connection_key, count(*) as aggregate')
            ->groupBy('transfer_batches.connection_key')
            ->pluck('aggregate', 'connection_key')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    public function latestErrorMessage(int $batchId): ?string
    {
        $value = $this->newQuery()
            ->where('transfer_batch_id', $batchId)
            ->failed()
            ->whereNotNull('error_message')
            ->orderByDesc('id')
            ->value('error_message');

        return $value !== null ? (string) $value : null;
    }

    public function findForBatch(int $batchId, string $sourceId): ?TransferItem
    {
        $item = $this->newQuery()
            ->where('transfer_batch_id', $batchId)
            ->where('source_id', $sourceId)
            ->first();

        return $item instanceof TransferItem ? $item : null;
    }

    /**
     * @return Collection<int, TransferItem>
     */
    public function failedForBatch(int $batchId): Collection
    {
        return $this->newQuery()
            ->where('transfer_batch_id', $batchId)
            ->failed()
            ->get();
    }

    public function paginateForConnection(
        string $connectionKey,
        ?string $status,
        ?string $type,
        int $limit,
    ): LengthAwarePaginator {
        $query = $this->newQuery()
            ->with('batch')
            ->whereHas('batch', function (Builder $query) use ($connectionKey, $type): void {
                $query->where('connection_key', $connectionKey);

                if ($type !== null) {
                    $query->where('type', $type);
                }
            });

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->orderByDesc('id')->paginate($limit);
    }

    /**
     * @return Collection<int, TransferItem>
     */
    public function listFailedForBatch(int $batchId, int $limit = 25): Collection
    {
        /** @var Collection<int, TransferItem> */
        return $this->newQuery()
            ->where('transfer_batch_id', $batchId)
            ->failed()
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'source_id', 'status', 'error_message', 'updated_at']);
    }
}
