<?php

declare(strict_types=1);

namespace Modules\Storage\Repositories;

use Jooservices\LaravelRepository\Repositories\EloquentRepository;
use Jooservices\LaravelRepository\Traits\HasCrud;
use Jooservices\LaravelRepository\Traits\HasFilter;
use Modules\Storage\Models\StorageRemoteSyncState;

/**
 * @extends EloquentRepository<StorageRemoteSyncState>
 */
final class StorageRemoteSyncStateRepository extends EloquentRepository
{
    use HasCrud;
    use HasFilter;

    public function __construct(StorageRemoteSyncState $model)
    {
        parent::__construct($model);
    }

    public function findForParent(int $accountId, string $parentRemoteId): ?StorageRemoteSyncState
    {
        return $this->newQuery()
            ->where('storage_account_id', $accountId)
            ->where('parent_remote_id', $parentRemoteId)
            ->first();
    }

    public function lockForParent(int $accountId, string $parentRemoteId): ?StorageRemoteSyncState
    {
        return $this->newQuery()
            ->where('storage_account_id', $accountId)
            ->where('parent_remote_id', $parentRemoteId)
            ->lockForUpdate()
            ->first();
    }

    public function firstOrCreateForParent(int $accountId, string $parentRemoteId): StorageRemoteSyncState
    {
        return $this->newQuery()->firstOrCreate(
            [
                'storage_account_id' => $accountId,
                'parent_remote_id' => $parentRemoteId,
            ],
            [
                'albums_complete' => false,
                'items_complete' => false,
            ],
        );
    }

    public function deleteForParent(int $accountId, string $parentRemoteId): void
    {
        $this->newQuery()
            ->where('storage_account_id', $accountId)
            ->where('parent_remote_id', $parentRemoteId)
            ->delete();
    }

    /**
     * @param  list<string>  $mergedIds
     */
    public function updateReconcileSeenRemoteIds(int $accountId, string $parentRemoteId, array $mergedIds): void
    {
        $this->newQuery()
            ->where('storage_account_id', $accountId)
            ->where('parent_remote_id', $parentRemoteId)
            ->update(['reconcile_seen_remote_ids' => $mergedIds]);
    }

    public function clearReconcileState(int $accountId, string $parentRemoteId): void
    {
        $this->newQuery()
            ->where('storage_account_id', $accountId)
            ->where('parent_remote_id', $parentRemoteId)
            ->update([
                'reconciling' => false,
                'reconcile_snapshot' => null,
                'reconcile_seen_remote_ids' => null,
            ]);
    }
}
