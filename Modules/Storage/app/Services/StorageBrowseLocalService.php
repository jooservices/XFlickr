<?php

declare(strict_types=1);

namespace Modules\Storage\Services;

use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Models\StorageRemoteAlbum;
use Modules\Storage\Models\StorageRemoteItem;
use Modules\Storage\Repositories\StorageRemoteAlbumRepository;
use Modules\Storage\Repositories\StorageRemoteItemRepository;
use Modules\Storage\Repositories\StorageRemoteSyncStateRepository;
use Modules\Storage\Repositories\StorageUploadRepository;

final class StorageBrowseLocalService
{
    public function __construct(
        private readonly StorageRemoteAlbumRepository $albums,
        private readonly StorageRemoteItemRepository $items,
        private readonly StorageRemoteSyncStateRepository $syncStates,
        private readonly StorageUploadRepository $uploads,
    ) {}

    public function browse(
        StorageAccount $account,
        ?string $containerId,
        int $albumPage,
        int $itemPage,
        int $perPage,
    ): StorageBrowseResult {
        $parentRemoteId = $this->parentKey($containerId);

        $albumPaginator = $this->albums->paginateForParent($account->id, $parentRemoteId, $perPage, $albumPage);
        $itemPaginator = $this->items->paginateForParent($account->id, $parentRemoteId, $perPage, $itemPage);
        $syncState = $this->syncStates->findForParent($account->id, $parentRemoteId);

        return new StorageBrowseResult(
            albums: $containerId === null
                ? collect($albumPaginator->items())->map(fn (StorageRemoteAlbum $album): array => $album->toBrowseArray())->all()
                : [],
            items: collect($itemPaginator->items())->map(fn (StorageRemoteItem $item): array => $item->toBrowseArray())->all(),
            localMeta: [
                'source' => 'local',
                'album_page' => $albumPaginator->currentPage(),
                'album_last_page' => $albumPaginator->lastPage(),
                'album_total' => $albumPaginator->total(),
                'item_page' => $itemPaginator->currentPage(),
                'item_last_page' => $itemPaginator->lastPage(),
                'item_total' => $itemPaginator->total(),
                'last_synced_at' => $syncState?->last_synced_at?->toIso8601String(),
                'sync_has_more' => $syncState !== null && (! $syncState->albums_complete || ! $syncState->items_complete),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function upsertItem(StorageAccount $account, ?string $containerId, array $item): StorageRemoteItem
    {
        return $this->items->upsertFromBrowseItem($account->id, $this->parentKey($containerId), $item);
    }

    /**
     * @param  list<string>  $remoteIds
     */
    public function deleteCachedItems(StorageAccount $account, array $remoteIds, ?string $parentRemoteId = null): void
    {
        $this->items->deleteByRemoteIds(
            $account->id,
            $remoteIds,
            $parentRemoteId !== null && $parentRemoteId !== '' ? $parentRemoteId : null,
        );
    }

    /**
     * @param  list<string>  $remoteIds
     */
    public function purgeUploadRecords(StorageAccount $account, array $remoteIds): void
    {
        $this->uploads->deleteByRemoteReferences($account->id, $remoteIds);
    }

    /**
     * @return list<string>
     */
    public function snapshotRemoteIdsForParent(StorageAccount $account, ?string $containerId): array
    {
        return $this->items->listRemoteIdsForParent($account->id, $this->parentKey($containerId));
    }

    public function wipeCacheForParent(StorageAccount $account, ?string $containerId): void
    {
        $parentRemoteId = $this->parentKey($containerId);

        $this->items->deleteAllForParent($account->id, $parentRemoteId);

        if ($containerId === null) {
            $this->albums->deleteAllForAccount($account->id);
        }
    }

    private function parentKey(?string $containerId): string
    {
        return $containerId !== null && $containerId !== '' ? $containerId : '';
    }
}
