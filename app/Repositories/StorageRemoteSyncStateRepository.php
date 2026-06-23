<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\StorageRemoteSyncState;
use Jooservices\LaravelRepository\Repositories\EloquentRepository;
use Jooservices\LaravelRepository\Traits\HasCrud;
use Jooservices\LaravelRepository\Traits\HasFilter;

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
     * @param  list<string>  $remoteIds
     */
    public function appendReconcileSeenRemoteIds(int $accountId, string $parentRemoteId, array $remoteIds): void
    {
        if ($remoteIds === []) {
            return;
        }

        $state = $this->findForParent($accountId, $parentRemoteId);
        if ($state === null || ! $state->reconciling) {
            return;
        }

        $seen = is_array($state->reconcile_seen_remote_ids) ? $state->reconcile_seen_remote_ids : [];
        $merged = array_values(array_unique([...$seen, ...$remoteIds]));

        $state->update(['reconcile_seen_remote_ids' => $merged]);
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
