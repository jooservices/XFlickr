<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\TransferBatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Jooservices\LaravelRepository\Repositories\EloquentRepository;
use Jooservices\LaravelRepository\Traits\HasCrud;
use Jooservices\LaravelRepository\Traits\HasFilter;
use JOOservices\XFlickrCrawler\Models\Connection;

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
            'status' => 'running',
            'total_count' => $totalCount,
        ]);
    }

    public function createUploadBatch(
        Connection $connection,
        int $storageAccountId,
        int $totalCount,
        ?string $subjectNsid = null,
    ): TransferBatch {
        return $this->newQuery()->create([
            'type' => 'upload',
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $subjectNsid,
            'storage_account_id' => $storageAccountId,
            'status' => 'running',
            'total_count' => $totalCount,
        ]);
    }

    public function findById(int $id): ?TransferBatch
    {
        return $this->newQuery()->find($id);
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
            ->where('status', $status)
            ->count();
    }

    public function countActiveForConnection(string $connectionKey, string $type): int
    {
        return $this->newQuery()
            ->where('connection_key', $connectionKey)
            ->where('type', $type)
            ->where('status', 'running')
            ->count();
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
            ->where('connection_key', $connectionKey)
            ->where('type', 'download')
            ->where('status', 'running')
            ->whereIn('subject_nsid', $subjectNsids)
            ->get(['subject_nsid', 'completed_count', 'total_count']);
    }

    /**
     * @return Builder<TransferBatch>
     */
    public function queryForConnection(string $connectionKey): Builder
    {
        return $this->newQuery()->where('connection_key', $connectionKey);
    }

    public function updateCounts(TransferBatch $batch, int $completed, int $failed, string $status): void
    {
        $batch->update([
            'completed_count' => $completed,
            'failed_count' => $failed,
            'status' => $status,
        ]);
    }
}
