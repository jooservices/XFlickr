<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Enums\StorageDriver;
use App\Models\StorageAccount;
use App\Models\StorageRemoteSyncState;
use App\Repositories\StorageRemoteAlbumRepository;
use App\Repositories\StorageRemoteItemRepository;
use App\Repositories\StorageRemoteSyncStateRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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

        return [
            'albums_synced' => $albumsSynced,
            'items_synced' => $itemsSynced,
            'has_more' => $state !== null && (! $state->albums_complete || ! $state->items_complete),
            'last_synced_at' => $state?->last_synced_at?->toIso8601String(),
            'albums_complete' => $state?->albums_complete ?? false,
            'items_complete' => $state?->items_complete ?? false,
        ];
    }

    public function reconcile(StorageAccount $account, ?string $containerId = null): void
    {
        $parentRemoteId = $this->parentKey($containerId);
        $snapshot = $this->browseLocal->snapshotRemoteIdsForParent($account, $containerId);

        $this->browseLocal->wipeCacheForParent($account, $containerId);
        $this->syncStates->deleteForParent($account->id, $parentRemoteId);

        $state = $this->syncStates->firstOrCreateForParent($account->id, $parentRemoteId);
        $state->update([
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
        return DB::transaction(function () use ($account, $containerId, $parentRemoteId, $result): array {
            $state = $this->syncStates->lockForParent($account->id, $parentRemoteId);

            if ($state === null || ($state->albums_complete && $state->items_complete)) {
                return ['albums' => 0, 'items' => 0];
            }

            $albumsSynced = 0;
            $itemsSynced = 0;

            if (! $state->albums_complete && $containerId === null) {
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

                $state->album_page_token = $result->albumNextPageToken;
                $state->albums_complete = $result->albumNextPageToken === null || $result->albumNextPageToken === '';
            } else {
                $state->albums_complete = true;
            }

            $batchRemoteIds = [];
            foreach ($result->items as $item) {
                $remoteId = (string) ($item['id'] ?? '');
                $this->items->upsertByRemoteId($account->id, $remoteId, [
                    'parent_remote_id' => $parentRemoteId,
                    'name' => (string) ($item['name'] ?? 'Untitled'),
                    'mime_type' => $item['mime_type'] ?? null,
                    'thumbnail_url' => $item['thumbnail_url'] ?? null,
                    'size' => isset($item['size']) && is_numeric($item['size']) ? (int) $item['size'] : null,
                    'modified_at' => isset($item['modified_at']) && $item['modified_at'] !== null
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

            if ($state->reconciling && $batchRemoteIds !== []) {
                $seen = is_array($state->reconcile_seen_remote_ids) ? $state->reconcile_seen_remote_ids : [];
                $merged = array_values(array_unique([...$seen, ...$batchRemoteIds]));
                $this->syncStates->updateReconcileSeenRemoteIds($account->id, $parentRemoteId, $merged);
                $state->reconcile_seen_remote_ids = $merged;
            }

            $state->item_page_token = $result->itemNextPageToken;
            $wasItemsComplete = $state->items_complete;
            $state->items_complete = $result->itemNextPageToken === null || $result->itemNextPageToken === '';
            $state->last_synced_at = now();
            $state->save();

            if ($state->reconciling && ! $wasItemsComplete && $state->items_complete) {
                $this->finalizeReconciliation($account, $containerId, $state);
            }

            return ['albums' => $albumsSynced, 'items' => $itemsSynced];
        });
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

        $removed = [];
        foreach ($snapshot as $remoteId) {
            if (! is_string($remoteId) || $remoteId === '') {
                continue;
            }

            if (! isset($seenLookup[$remoteId])) {
                $removed[] = $remoteId;
            }
        }

        if ($removed !== []) {
            $this->browseLocal->purgeUploadRecords($account, $removed);
        }

        $this->syncStates->clearReconcileState($account->id, $parentRemoteId);
    }

    private function parentKey(?string $containerId): string
    {
        return $containerId !== null && $containerId !== '' ? $containerId : '';
    }
}
