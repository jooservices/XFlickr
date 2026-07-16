<?php

declare(strict_types=1);

namespace Modules\Transfer\Services;

use App\Repositories\Crawler\PhotoQueryRepository;
use Illuminate\Support\Collection;
use Modules\Crawler\Models\Connection;
use Modules\Crawler\Models\Photo;
use Modules\Storage\Services\StorageService;
use Modules\Transfer\Dto\TransferQueueResult;
use Modules\Transfer\Enums\StoredFileStatus;
use Modules\Transfer\Enums\TransferType;
use Modules\Transfer\Jobs\FanOutTransferJob;

final class PhotoTransferService
{
    private const SOURCE_TYPE = 'flickr_photo';

    private const CHUNK_SIZE = 250;

    public function __construct(
        private readonly PhotoQueryRepository $photos,
        private readonly TransferBatchService $transfers,
        private readonly StorageService $storage,
    ) {}

    public function resolveStorageAccountId(?int $storageAccountId): ?int
    {
        return $this->storage->resolveAccountId($storageAccountId);
    }

    /**
     * @param  list<string>  $contactNsids
     * @param  list<string>  $flickrPhotoIds
     */
    public function queueDownloadsFromInput(
        Connection $connection,
        ?string $flickrPhotoId = null,
        ?string $contactNsid = null,
        array $contactNsids = [],
        array $flickrPhotoIds = [],
    ): TransferQueueResult {
        if ($flickrPhotoIds !== []) {
            return $this->queuePhotoDownloads($connection, $flickrPhotoIds);
        }

        if ($flickrPhotoId !== null && $flickrPhotoId !== '') {
            return $this->queuePhotoDownloads($connection, [$flickrPhotoId]);
        }

        if ($contactNsids !== []) {
            $queuedBatches = 0;
            foreach ($contactNsids as $selectedNsid) {
                $queuedBatches += $this->queueFanOutDownload($connection, $selectedNsid);
            }

            return TransferQueueResult::success(
                $queuedBatches === 0
                    ? 'No photos pending download.'
                    : "{$queuedBatches} contact download batch(es) queued.",
                $queuedBatches,
            );
        }

        $queuedBatches = $this->queueFanOutDownload($connection, $contactNsid);

        return TransferQueueResult::success(
            $queuedBatches === 0 ? 'No photos pending download.' : "{$queuedBatches} download batch(es) queued.",
            $queuedBatches,
        );
    }

    /**
     * @param  list<string>  $flickrPhotoIds
     */
    public function queuePhotoDownloads(Connection $connection, array $flickrPhotoIds): TransferQueueResult
    {
        $photos = $this->photos->listByFlickrPhotoIds($flickrPhotoIds, ['id', 'flickr_photo_id', 'owner_nsid']);

        if ($photos->isEmpty()) {
            return TransferQueueResult::error('No matching photos found in catalog.');
        }

        $completedIds = $this->transfers->completedSourceIds(
            self::SOURCE_TYPE,
            $photos->pluck('flickr_photo_id')->all(),
        );

        $pending = $photos->reject(
            fn (Photo $photo): bool => in_array($photo->flickr_photo_id, $completedIds, true),
        );

        if ($pending->isEmpty()) {
            return TransferQueueResult::success('All selected photos already downloaded.', 0);
        }

        $downloadItems = $pending->map(
            static fn (Photo $photo): array => [
                'source_type' => self::SOURCE_TYPE,
                'source_id' => $photo->flickr_photo_id,
                'source_owner' => $photo->owner_nsid,
            ],
        )->values()->all();

        return $this->transfers->queueDownloads(
            $downloadItems,
            $connection->connection_key,
            $pending->first()->owner_nsid,
            'bulk',
            null,
            'Selected photos',
        );
    }

    /**
     * Fan-out download: dispatch a job that will chunk through all photos for an owner.
     */
    public function queueFanOutDownload(Connection $connection, ?string $ownerNsid = null): int
    {
        $ownerNsid = $ownerNsid ?? $connection->connection_key;

        if (! $this->photos->existsForOwnerNsid($ownerNsid)) {
            return 0;
        }

        FanOutTransferJob::dispatch(
            transferType: TransferType::Download,
            connectionKey: $connection->connection_key,
            ownerNsid: $ownerNsid,
        );

        return 1;
    }

    /**
     * Fan-out all contact downloads by chunking through catalog photos.
     */
    public function fanOutDownloads(Connection $connection, string $ownerNsid): int
    {
        $batchCount = 0;

        $this->photos->chunkByOwnerNsid(
            $ownerNsid,
            self::CHUNK_SIZE,
            function (Collection $chunk) use ($connection, $ownerNsid, &$batchCount): void {
                $photoIds = $chunk->pluck('flickr_photo_id')->all();
                $completedIds = $this->transfers->completedSourceIds(self::SOURCE_TYPE, $photoIds);

                $pending = $chunk->reject(
                    fn (Photo $photo): bool => in_array($photo->flickr_photo_id, $completedIds, true),
                );

                if ($pending->isEmpty()) {
                    return;
                }

                $groups = $this->groupByMembership($pending);

                foreach ($groups as $group) {
                    if ($group['photos']->isEmpty()) {
                        continue;
                    }

                    $downloadItems = $group['photos']->map(
                        static fn (Photo $photo): array => [
                            'source_type' => self::SOURCE_TYPE,
                            'source_id' => $photo->flickr_photo_id,
                            'source_owner' => $photo->owner_nsid,
                        ],
                    )->values()->all();

                    $this->transfers->queueDownloads(
                        $downloadItems,
                        $connection->connection_key,
                        $ownerNsid,
                        $group['group_type'],
                        $group['group_id'],
                        $group['group_label'],
                    );

                    $batchCount++;
                }
            },
        );

        return $batchCount;
    }

    /**
     * @param  list<string>  $contactNsids
     * @param  list<string>  $flickrPhotoIds
     */
    public function queueUploadsFromInput(
        Connection $connection,
        ?int $storageAccountId,
        ?string $flickrPhotoId = null,
        ?string $contactNsid = null,
        array $contactNsids = [],
        array $flickrPhotoIds = [],
        ?bool $deleteLocalAfterUpload = null,
    ): TransferQueueResult {
        $storageAccountId = $this->resolveStorageAccountId($storageAccountId);
        if ($storageAccountId === null) {
            return TransferQueueResult::error('No storage account configured.');
        }

        if ($flickrPhotoIds !== []) {
            return $this->queuePhotoUploads($connection, $flickrPhotoIds, $storageAccountId, $deleteLocalAfterUpload);
        }

        if ($flickrPhotoId !== null && $flickrPhotoId !== '') {
            return $this->queuePhotoUploads($connection, [$flickrPhotoId], $storageAccountId, $deleteLocalAfterUpload);
        }

        if ($contactNsids !== []) {
            $totalQueued = 0;
            foreach ($contactNsids as $selectedNsid) {
                $result = $this->queueContactUploads($connection, $selectedNsid, $storageAccountId, $deleteLocalAfterUpload);
                $totalQueued += $result->queuedCount;
            }

            return TransferQueueResult::success(
                $totalQueued === 0 ? 'No photos ready for upload.' : "{$totalQueued} photo(s) queued for upload.",
                $totalQueued,
            );
        }

        return $this->queueContactUploads($connection, $contactNsid, $storageAccountId, $deleteLocalAfterUpload);
    }

    /**
     * @param  list<string>  $flickrPhotoIds
     */
    public function queuePhotoUploads(
        Connection $connection,
        array $flickrPhotoIds,
        int $storageAccountId,
        ?bool $deleteLocalAfterUpload = null,
    ): TransferQueueResult {
        $photos = $this->photos->listByFlickrPhotoIds($flickrPhotoIds, ['id', 'flickr_photo_id', 'owner_nsid']);

        if ($photos->isEmpty()) {
            return TransferQueueResult::error('No matching photos found in catalog.');
        }

        $storedFileIds = [];
        $needsDownload = collect();

        foreach ($photos as $photo) {
            $storedFile = $this->transfers->findStoredFile(self::SOURCE_TYPE, $photo->flickr_photo_id);

            if ($storedFile === null || $storedFile->status !== StoredFileStatus::Completed->value) {
                $needsDownload->push($photo);

                continue;
            }

            $storedFileIds[] = $storedFile->id;
        }

        if ($needsDownload->isNotEmpty()) {
            $downloadItems = $needsDownload->map(
                static fn (Photo $photo): array => [
                    'source_type' => self::SOURCE_TYPE,
                    'source_id' => $photo->flickr_photo_id,
                    'source_owner' => $photo->owner_nsid,
                ],
            )->values()->all();

            $this->transfers->queueDownloads(
                $downloadItems,
                $connection->connection_key,
                $needsDownload->first()->owner_nsid,
            );
        }

        if ($storedFileIds === []) {
            return TransferQueueResult::success('Downloads queued. Re-trigger upload after downloads complete.', 0);
        }

        return $this->transfers->queueUploads(
            $storedFileIds,
            $storageAccountId,
            $connection->connection_key,
            $photos->first()->owner_nsid,
            $deleteLocalAfterUpload,
        );
    }

    public function queueContactUploads(
        Connection $connection,
        ?string $contactNsid,
        int $storageAccountId,
        ?bool $deleteLocalAfterUpload = null,
    ): TransferQueueResult {
        $ownerNsid = $contactNsid ?? $connection->connection_key;
        $photos = $this->photos->listByOwnerNsid($ownerNsid, ['id', 'flickr_photo_id', 'owner_nsid']);

        if ($photos->isEmpty()) {
            return TransferQueueResult::error('No photos found for this contact.');
        }

        return $this->queuePhotoUploads(
            $connection,
            $photos->pluck('flickr_photo_id')->all(),
            $storageAccountId,
            $deleteLocalAfterUpload,
        );
    }

    /**
     * Fan-out upload: dispatch a job that will chunk through all photos for an owner.
     */
    public function queueFanOutUpload(
        Connection $connection,
        string $ownerNsid,
        int $storageAccountId,
        ?bool $deleteLocalAfterUpload = null,
    ): int {
        if (! $this->photos->existsForOwnerNsid($ownerNsid)) {
            return 0;
        }

        FanOutTransferJob::dispatch(
            transferType: TransferType::Upload,
            connectionKey: $connection->connection_key,
            ownerNsid: $ownerNsid,
            storageAccountId: $storageAccountId,
            deleteLocalAfterUpload: $deleteLocalAfterUpload,
        );

        return 1;
    }

    /**
     * @param  Collection<int, Photo>  $photos
     * @return list<array{group_type: string, group_id: string|null, group_label: string, photos: Collection<int, Photo>}>
     */
    private function groupByMembership(Collection $photos): array
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
