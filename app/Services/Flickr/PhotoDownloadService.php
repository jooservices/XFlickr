<?php

declare(strict_types=1);

namespace App\Services\Flickr;

use App\Enums\TransferType;
use App\Jobs\DownloadPhotoJob;
use App\Jobs\FanOutTransferBatchJob;
use App\Repositories\Crawler\PhotoQueryRepository;
use App\Repositories\StoredFileRepository;
use App\Repositories\TransferBatchRepository;
use App\Repositories\TransferItemRepository;
use App\Services\Transfer\TransferQueueResult;
use Illuminate\Support\Collection;
use JOOservices\XFlickrCrawler\Models\Connection;
use JOOservices\XFlickrCrawler\Models\Photo;

final class PhotoDownloadService
{
    private const CHUNK_SIZE = 250;

    public function __construct(
        private readonly PhotoQueryRepository $photos,
        private readonly StoredFileRepository $storedFiles,
        private readonly TransferBatchRepository $batches,
        private readonly TransferItemRepository $items,
    ) {}

    /**
     * @param  list<string>  $contactNsids
     */
    public function queueFromInput(
        Connection $connection,
        ?string $flickrPhotoId = null,
        ?string $contactNsid = null,
        array $contactNsids = [],
    ): TransferQueueResult {
        if ($flickrPhotoId !== null && $flickrPhotoId !== '') {
            $queuedBatches = $this->queuePhotoDownload($connection, $flickrPhotoId);

            return TransferQueueResult::success(
                $queuedBatches === 0 ? 'No download queued for this photo.' : 'Photo download queued.',
                $queuedBatches,
            );
        }

        if ($contactNsids !== []) {
            $queuedBatches = 0;

            foreach ($contactNsids as $selectedContactNsid) {
                $queuedBatches += $this->queueDownloads($connection, $selectedContactNsid);
            }

            return TransferQueueResult::success(
                $queuedBatches === 0
                    ? 'No photos pending download.'
                    : "{$queuedBatches} contact download batch(es) queued.",
                $queuedBatches,
            );
        }

        $queuedBatches = $this->queueDownloads($connection, $contactNsid);

        return TransferQueueResult::success(
            $queuedBatches === 0 ? 'No photos pending download.' : "{$queuedBatches} download batch(es) queued.",
            $queuedBatches,
        );
    }

    /**
     * @return int Number of fan-out jobs queued
     */
    public function queueDownloads(Connection $connection, ?string $ownerNsid = null): int
    {
        $ownerNsid = $ownerNsid ?? $connection->connection_key;

        if (! $this->photos->existsForOwnerNsid($ownerNsid)) {
            return 0;
        }

        FanOutTransferBatchJob::dispatch(
            transferType: TransferType::Download,
            connectionKey: $connection->connection_key,
            ownerNsid: $ownerNsid,
        );

        return 1;
    }

    /**
     * @return int Number of batches queued (0 or 1)
     */
    public function queuePhotoDownload(Connection $connection, string $flickrPhotoId): int
    {
        $photo = $this->photos->findByFlickrPhotoId($flickrPhotoId, ['id', 'flickr_photo_id', 'owner_nsid']);

        if ($photo === null) {
            return 0;
        }

        return $this->queuePendingPhotos($connection, collect([$photo]), $photo->owner_nsid);
    }

    /**
     * @param  Collection<int, Photo>  $photos
     * @return int Number of batches queued
     */
    public function queueSelectedDownloads(Connection $connection, Collection $photos, string $ownerNsid): int
    {
        return $this->queuePendingPhotos($connection, $photos, $ownerNsid);
    }

    /**
     * @return int Number of batches created
     */
    public function fanOutDownloads(Connection $connection, string $ownerNsid): int
    {
        $batchCount = 0;

        $this->photos->chunkByOwnerNsid(
            $ownerNsid,
            self::CHUNK_SIZE,
            function (Collection $chunk) use ($connection, $ownerNsid, &$batchCount): void {
                $photoIds = $chunk->pluck('flickr_photo_id')->all();
                $completedIds = $this->storedFiles->completedOriginalFlickrPhotoIds($photoIds);

                $pending = $chunk->reject(
                    fn (Photo $photo): bool => in_array($photo->flickr_photo_id, $completedIds, true),
                );

                if ($pending->isEmpty()) {
                    return;
                }

                $batchCount += $this->queuePendingPhotosWithoutDedup($connection, $pending, $ownerNsid);
            },
        );

        return $batchCount;
    }

    /**
     * @param  Collection<int, Photo>  $photos
     * @return int Number of batches queued
     */
    private function queuePendingPhotos(Connection $connection, Collection $photos, string $ownerNsid): int
    {
        $pending = $photos->reject(
            fn (Photo $photo): bool => $this->storedFiles->hasCompletedOriginal($photo->flickr_photo_id),
        );

        return $this->queuePendingPhotosWithoutDedup($connection, $pending, $ownerNsid);
    }

    /**
     * @param  Collection<int, Photo>  $photos
     * @return int Number of batches queued
     */
    private function queuePendingPhotosWithoutDedup(Connection $connection, Collection $photos, string $ownerNsid): int
    {
        if ($photos->isEmpty()) {
            return 0;
        }

        $groups = $this->groupPendingPhotos($photos);
        $batchCount = 0;

        foreach ($groups as $group) {
            if ($group['photos']->isEmpty()) {
                continue;
            }

            $batch = $this->batches->createDownloadBatch(
                $connection,
                $ownerNsid,
                [
                    'group_type' => $group['group_type'],
                    'group_id' => $group['group_id'],
                    'group_label' => $group['group_label'],
                ],
                $group['photos']->count(),
            );

            $this->items->createPendingBulk(
                $batch->id,
                $group['photos']->pluck('flickr_photo_id')->all(),
            );

            foreach ($group['photos'] as $photo) {
                DownloadPhotoJob::dispatch(
                    $photo->flickr_photo_id,
                    $photo->owner_nsid,
                    $connection->connection_key,
                    $batch->id,
                );
            }

            $batchCount++;
        }

        return $batchCount;
    }

    /**
     * @param  Collection<int, Photo>  $photos
     * @return list<array{group_type: string, group_id: string|null, group_label: string, photos: Collection<int, Photo>}>
     */
    private function groupPendingPhotos(Collection $photos): array
    {
        $photoIds = $photos->pluck('id')->all();
        $memberships = $this->photos->photosetAndGalleryMemberships($photoIds);
        $photosetByPhotoId = $memberships['photoset_rows']->groupBy('photo_id')->map->first();
        $galleryByPhotoId = $memberships['gallery_rows']->groupBy('photo_id')->map->first();

        /** @var array<string, array{group_type: string, group_id: string|null, group_label: string, photos: Collection<int, Photo>}> $groups */
        $groups = [];

        foreach ($photos as $photo) {
            $photoset = $photosetByPhotoId->get($photo->id);

            if ($photoset !== null) {
                $groupKey = 'photoset:'.$photoset->flickr_photoset_id;
                $groupType = 'photoset';
                $groupId = (string) $photoset->flickr_photoset_id;
                $groupLabel = (string) ($photoset->title ?: 'Untitled photoset');
            } elseif (($gallery = $galleryByPhotoId->get($photo->id)) !== null) {
                $groupKey = 'gallery:'.$gallery->flickr_gallery_id;
                $groupType = 'gallery';
                $groupId = (string) $gallery->flickr_gallery_id;
                $groupLabel = (string) ($gallery->title ?: 'Untitled gallery');
            } else {
                $groupKey = 'owner:'.$photo->owner_nsid.':loose';
                $groupType = 'owner';
                $groupId = $photo->owner_nsid;
                $groupLabel = 'Loose photos';
            }

            if (! isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'group_type' => $groupType,
                    'group_id' => $groupId,
                    'group_label' => $groupLabel,
                    'photos' => collect(),
                ];
            }

            $groups[$groupKey]['photos']->push($photo);
        }

        uasort($groups, static function (array $left, array $right): int {
            $order = ['photoset' => 0, 'gallery' => 1, 'owner' => 2];

            return [$order[$left['group_type']] ?? 9, $left['group_label']]
                <=> [$order[$right['group_type']] ?? 9, $right['group_label']];
        });

        return array_values($groups);
    }
}
