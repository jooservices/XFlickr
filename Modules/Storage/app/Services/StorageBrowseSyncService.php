<?php

declare(strict_types=1);

namespace Modules\Storage\Services;

use Illuminate\Support\Carbon;
use Modules\Storage\Dto\StorageBrowseResult;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Events\StorageRemoteItemsRemoved;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Models\StorageRemoteSyncState;
use Modules\Storage\Repositories\StorageRemoteAlbumRepository;
use Modules\Storage\Repositories\StorageRemoteItemRepository;
use Modules\Storage\Repositories\StorageRemoteSyncStateRepository;

final class StorageBrowseSyncService
{
    private const int PER_PAGE = 100;

    public function __construct(
        private readonly StorageBrowseService $browse,
        private readonly StorageBrowseLocalService $browseLocal,
        private readonly StorageRemoteSyncStateRepository $syncStates,
        private readonly StorageRemoteAlbumRepository $albums,
        private readonly StorageRemoteItemRepository $items,
    ) {}

    /**
     * @return array{
     *     albums_synced: int,
     *     items_synced: int,
     *     has_more: bool,
     *     last_synced_at: string|null,
     *     albums_complete: bool,
     *     items_complete: bool,
     * }
     */
    public function sync(
        StorageAccount $account,
        StorageDriver $driver,
        ?string $containerId = null,
        int $maxBatches = 3,
    ): array {
        $parentRemoteId = $this->parentKey($containerId);

        $this->syncStates->firstOrCreateForParent($account->id, $parentRemoteId);

        $albumsSynced = 0;
        $itemsSynced = 0;

        for ($batch = 0; $batch < $maxBatches; $batch++) {
            $state = $this->syncStates->findForParent($account->id, $parentRemoteId);

            if ($state === null || ($state->albums_complete && $state->items_complete)) {
                break;
            }

            $albumToken = $state->albums_complete ? null : $state->album_page_token;
            $itemToken = $state->items_complete ? null : $state->item_page_token;

            $result = $this->browse->browse(
                $driver,
                $account->id,
                self::PER_PAGE,
                $albumToken,
                $itemToken,
                $containerId,
                includeAlbums: ! $state->albums_complete,
                includeItems: ! $state->items_complete,
            );

            $batchCounts = $this->persistBatch($account, $containerId, $parentRemoteId, $result);
            $albumsSynced += $batchCounts['albums'];
            $itemsSynced += $batchCounts['items'];

            $state = $this->syncStates->findForParent($account->id, $parentRemoteId);

            if ($state === null || ($state->albums_complete && $state->items_complete)) {
                break;
            }
        }

        $state = $this->syncStates->findForParent($account->id, $parentRemoteId);

        if ($state === null) {
            return [
                'albums_synced' => $albumsSynced,
                'items_synced' => $itemsSynced,
                'has_more' => false,
                'last_synced_at' => null,
                'albums_complete' => false,
                'items_complete' => false,
            ];
        }

        return [
            'albums_synced' => $albumsSynced,
            'items_synced' => $itemsSynced,
            'has_more' => ! $state->albums_complete || ! $state->items_complete,
            'last_synced_at' => $state->last_synced_at?->toIso8601String(),
            'albums_complete' => $state->albums_complete,
            'items_complete' => $state->items_complete,
        ];
    }

    public function reconcile(StorageAccount $account, ?string $containerId = null): void
    {
        $parentRemoteId = $this->parentKey($containerId);
        $snapshot = $this->browseLocal->snapshotRemoteIdsForParent($account, $containerId);

        $this->browseLocal->wipeCacheForParent($account, $containerId);
        $this->syncStates->deleteForParent($account->id, $parentRemoteId);

        $state = $this->syncStates->firstOrCreateForParent($account->id, $parentRemoteId);
        $this->syncStates->update($state->id, [
            'reconciling' => true,
            'reconcile_snapshot' => $snapshot,
            'reconcile_seen_remote_ids' => [],
            'albums_complete' => false,
            'items_complete' => false,
            'album_page_token' => null,
            'item_page_token' => null,
        ]);
    }

    public function reset(StorageAccount $account, ?string $containerId = null): void
    {
        $this->syncStates->deleteForParent($account->id, $this->parentKey($containerId));
    }

    /**
     * @return array{albums: int, items: int}
     */
    private function persistBatch(
        StorageAccount $account,
        ?string $containerId,
        string $parentRemoteId,
        StorageBrowseResult $result,
    ): array {
        return $this->syncStates->transaction(function () use ($account, $containerId, $parentRemoteId, $result): array {
            $state = $this->syncStates->lockForParent($account->id, $parentRemoteId);

            if ($state === null || ($state->albums_complete && $state->items_complete)) {
                return ['albums' => 0, 'items' => 0];
            }

            $albumPageToken = $state->album_page_token;
            $albumsComplete = $state->albums_complete;
            $itemPageToken = $state->item_page_token;
            $itemsComplete = $state->items_complete;
            $reconcileSeenRemoteIds = $state->reconcile_seen_remote_ids;

            $albumsSynced = $this->persistAlbumBatch(
                $account,
                $containerId,
                $parentRemoteId,
                $result,
                $albumsComplete,
                $albumPageToken,
            );
            $albumsComplete = $albumsSynced['albumsComplete'];
            $albumPageToken = $albumsSynced['albumPageToken'];

            $itemsSynced = $this->persistItemBatch(
                $account,
                $parentRemoteId,
                $result,
            );

            if ($state->reconciling && $itemsSynced['batchRemoteIds'] !== []) {
                $reconcileSeenRemoteIds = $this->mergeReconcileSeenRemoteIds(
                    $account,
                    $parentRemoteId,
                    $reconcileSeenRemoteIds,
                    $itemsSynced['batchRemoteIds'],
                );
            }

            $wasItemsComplete = $itemsComplete;
            $itemPageToken = $result->itemNextPageToken;
            $itemsComplete = $result->itemNextPageToken === null || $result->itemNextPageToken === '';
            $lastSyncedAt = now();

            $this->syncStates->update($state->id, [
                'album_page_token' => $albumPageToken,
                'albums_complete' => $albumsComplete,
                'item_page_token' => $itemPageToken,
                'items_complete' => $itemsComplete,
                'reconcile_seen_remote_ids' => $reconcileSeenRemoteIds,
                'last_synced_at' => $lastSyncedAt,
            ]);

            $state->fill([
                'album_page_token' => $albumPageToken,
                'albums_complete' => $albumsComplete,
                'item_page_token' => $itemPageToken,
                'items_complete' => $itemsComplete,
                'reconcile_seen_remote_ids' => $reconcileSeenRemoteIds,
                'last_synced_at' => $lastSyncedAt,
            ]);

            if ($state->reconciling && ! $wasItemsComplete && $itemsComplete) {
                $this->finalizeReconciliation($account, $containerId, $state);
            }

            return ['albums' => $albumsSynced['count'], 'items' => $itemsSynced['count']];
        });
    }

    /**
     * @return array{count: int, albumPageToken: string|null, albumsComplete: bool}
     */
    private function persistAlbumBatch(
        StorageAccount $account,
        ?string $containerId,
        string $parentRemoteId,
        StorageBrowseResult $result,
        bool $albumsComplete,
        ?string $albumPageToken,
    ): array {
        if ($albumsComplete || $containerId !== null) {
            return [
                'count' => 0,
                'albumPageToken' => $albumPageToken,
                'albumsComplete' => $containerId !== null ? true : $albumsComplete,
            ];
        }

        $albumsSynced = 0;
        foreach ($result->albums as $album) {
            $this->albums->upsertByRemoteId($account->id, (string) ($album['id'] ?? ''), [
                'parent_remote_id' => $parentRemoteId,
                'title' => (string) ($album['title'] ?? 'Untitled'),
                'cover_thumbnail_url' => $album['cover_thumbnail_url'] ?? null,
                'media_items_count' => $album['media_items_count'] ?? null,
                'synced_at' => now(),
            ]);
            $albumsSynced++;
        }

        return [
            'count' => $albumsSynced,
            'albumPageToken' => $result->albumNextPageToken,
            'albumsComplete' => $result->albumNextPageToken === null || $result->albumNextPageToken === '',
        ];
    }

    /**
     * @return array{count: int, batchRemoteIds: list<string>}
     */
    private function persistItemBatch(
        StorageAccount $account,
        string $parentRemoteId,
        StorageBrowseResult $result,
    ): array {
        $itemsSynced = 0;
        $batchRemoteIds = [];

        foreach ($result->items as $item) {
            $remoteId = (string) ($item['id'] ?? '');
            $this->items->upsertByRemoteId($account->id, $remoteId, [
                'parent_remote_id' => $parentRemoteId,
                'name' => (string) ($item['name'] ?? 'Untitled'),
                'mime_type' => $item['mime_type'] ?? null,
                'thumbnail_url' => $item['thumbnail_url'] ?? null,
                'size' => isset($item['size']) && is_numeric($item['size']) ? (int) $item['size'] : null,
                'modified_at' => isset($item['modified_at'])
                    ? Carbon::parse((string) $item['modified_at'])
                    : null,
                'web_url' => $item['web_url'] ?? null,
                'synced_at' => now(),
            ]);
            if ($remoteId !== '') {
                $batchRemoteIds[] = $remoteId;
            }
            $itemsSynced++;
        }

        return [
            'count' => $itemsSynced,
            'batchRemoteIds' => $batchRemoteIds,
        ];
    }

    /**
     * @param  list<string>|null  $reconcileSeenRemoteIds
     * @param  list<string>  $batchRemoteIds
     * @return list<string>
     */
    private function mergeReconcileSeenRemoteIds(
        StorageAccount $account,
        string $parentRemoteId,
        ?array $reconcileSeenRemoteIds,
        array $batchRemoteIds,
    ): array {
        $seen = is_array($reconcileSeenRemoteIds) ? $reconcileSeenRemoteIds : [];
        $merged = array_values(array_unique([...$seen, ...$batchRemoteIds]));
        $this->syncStates->updateReconcileSeenRemoteIds($account->id, $parentRemoteId, $merged);

        return $merged;
    }

    private function finalizeReconciliation(
        StorageAccount $account,
        ?string $containerId,
        ?StorageRemoteSyncState $state = null,
    ): void {
        $parentRemoteId = $this->parentKey($containerId);
        $state ??= $this->syncStates->findForParent($account->id, $parentRemoteId);

        if ($state === null || ! $state->reconciling) {
            return;
        }

        $snapshot = is_array($state->reconcile_snapshot) ? $state->reconcile_snapshot : [];
        $seen = is_array($state->reconcile_seen_remote_ids) ? $state->reconcile_seen_remote_ids : [];
        $seenLookup = array_fill_keys($seen, true);
        $removed = array_values(array_filter(
            $snapshot,
            static fn (mixed $remoteId): bool => is_string($remoteId)
                && $remoteId !== ''
                && ! isset($seenLookup[$remoteId]),
        ));

        if ($removed !== []) {
            event(new StorageRemoteItemsRemoved($account->id, $removed));
        }

        $this->syncStates->clearReconcileState($account->id, $parentRemoteId);
    }

    private function parentKey(?string $containerId): string
    {
        return $containerId !== null && $containerId !== '' ? $containerId : '';
    }
}
