<?php

declare(strict_types=1);

namespace Modules\Transfer\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Jooservices\LaravelRepository\Repositories\EloquentRepository;
use Jooservices\LaravelRepository\Traits\HasCrud;
use Jooservices\LaravelRepository\Traits\HasFilter;
use Modules\Crawler\Models\Connection;
use Modules\Transfer\Enums\TransferBatchStatus;
use Modules\Transfer\Models\TransferBatch;

/**
 * @extends EloquentRepository<TransferBatch>
 */
final class TransferBatchRepository extends EloquentRepository
{
    use HasCrud;
    use HasFilter;

    public function __construct(TransferBatch $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array{group_type: string, group_id: string|null, group_label: string}  $groupMeta
     */
    public function createDownloadBatch(
        Connection $connection,
        string $subjectNsid,
        array $groupMeta,
        int $totalCount,
    ): TransferBatch {
        return $this->newQuery()->create([
            'type' => 'download',
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $subjectNsid,
            'group_type' => $groupMeta['group_type'],
            'group_id' => $groupMeta['group_id'],
            'group_label' => $groupMeta['group_label'],
            'status' => TransferBatchStatus::Running->value,
            'total_count' => $totalCount,
        ]);
    }

    public function createUploadBatch(
        Connection $connection,
        int $storageAccountId,
        int $totalCount,
        ?string $subjectNsid = null,
        ?bool $deleteLocalAfterUpload = null,
    ): TransferBatch {
        return $this->newQuery()->create([
            'type' => 'upload',
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $subjectNsid,
            'storage_account_id' => $storageAccountId,
            'status' => TransferBatchStatus::Running->value,
            'total_count' => $totalCount,
            'delete_local_after_upload' => $deleteLocalAfterUpload,
        ]);
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

    public function updateCounts(TransferBatch $batch, int $completed, int $failed, TransferBatchStatus $status): void
    {
        $batch->update([
            'completed_count' => $completed,
            'failed_count' => $failed,
            'status' => $status->value,
        ]);
    }
}
