<?php

declare(strict_types=1);

namespace App\Services\Flickr;

use App\Enums\StoredFileStatus;
use App\Jobs\UploadPhotoJob;
use App\Models\StorageAccount;
use App\Repositories\Crawler\PhotoQueryRepository;
use App\Repositories\StorageAccountRepository;
use App\Repositories\StorageUploadRepository;
use App\Repositories\StoredFileRepository;
use App\Repositories\TransferBatchRepository;
use App\Repositories\TransferItemRepository;
use Illuminate\Support\Collection;
use JOOservices\XFlickrCrawler\Models\Connection;
use JOOservices\XFlickrCrawler\Models\Photo;

final class PhotoUploadService
{
    public function __construct(
        private readonly PhotoQueryRepository $photos,
        private readonly PhotoDownloadService $downloads,
        private readonly StorageAccountRepository $storageAccounts,
        private readonly StoredFileRepository $storedFiles,
        private readonly StorageUploadRepository $storageUploads,
        private readonly TransferBatchRepository $batches,
        private readonly TransferItemRepository $items,
    ) {}

    public function resolveStorageAccount(?int $storageAccountId = null): ?StorageAccount
    {
        if ($storageAccountId !== null && $storageAccountId > 0) {
            return $this->storageAccounts->findByIdOrFail($storageAccountId);
        }

        return $this->storageAccounts->findDefault();
    }

    /**
     * @return int Number of photos queued
     */
    public function queueUploads(Connection $connection, StorageAccount $storageAccount, ?string $ownerNsid = null): int
    {
        $ownerNsid = ($ownerNsid !== null && $ownerNsid !== '')
            ? $ownerNsid
            : $connection->connection_key;

        $photoList = $this->photos->listByOwnerNsid($ownerNsid, ['id', 'flickr_photo_id', 'owner_nsid']);

        return $this->queuePendingPhotos($connection, $photoList, $storageAccount, $ownerNsid !== $connection->connection_key ? $ownerNsid : null);
    }

    /**
     * @return int Number of photos queued (0 or 1)
     */
    public function queuePhotoUpload(Connection $connection, StorageAccount $storageAccount, string $flickrPhotoId): int
    {
        $photo = $this->photos->findByFlickrPhotoId($flickrPhotoId, ['id', 'flickr_photo_id', 'owner_nsid']);

        if ($photo === null) {
            return 0;
        }

        return $this->queuePendingPhotos($connection, collect([$photo]), $storageAccount);
    }

    /**
     * @param  Collection<int, Photo>  $photos
     * @return int Number of photos queued
     */
    private function queuePendingPhotos(
        Connection $connection,
        Collection $photos,
        StorageAccount $storageAccount,
        ?string $subjectNsid = null,
    ): int {
        /** @var Collection<int, Photo> $needsDownload */
        $needsDownload = collect();
        /** @var Collection<int, Photo> $readyForUpload */
        $readyForUpload = collect();

        foreach ($photos as $photo) {
            $storedFile = $this->storedFiles->findOriginalByFlickrPhotoId($photo->flickr_photo_id);

            if ($storedFile === null) {
                $needsDownload->push($photo);

                continue;
            }

            if ($storedFile->status !== StoredFileStatus::Completed->value) {
                $needsDownload->push($photo);

                continue;
            }

            if (! $this->storageUploads->hasCompleted($storedFile->id, $storageAccount->id)) {
                $readyForUpload->push($photo);
            }
        }

        if ($needsDownload->isNotEmpty()) {
            $firstNeedingDownload = $needsDownload->first();
            $downloadOwnerNsid = $subjectNsid ?? ($firstNeedingDownload instanceof Photo
                ? $firstNeedingDownload->owner_nsid
                : $connection->connection_key);
            $this->downloads->queueSelectedDownloads($connection, $needsDownload, $downloadOwnerNsid);
        }

        if ($readyForUpload->isEmpty()) {
            return 0;
        }

        $batch = $this->batches->createUploadBatch(
            $connection,
            $storageAccount->id,
            $readyForUpload->count(),
            $subjectNsid,
        );

        foreach ($readyForUpload as $photo) {
            $this->items->createPending($batch->id, $photo->flickr_photo_id);

            UploadPhotoJob::dispatch(
                $photo->flickr_photo_id,
                $storageAccount->id,
                $batch->id,
                $photo->owner_nsid,
            );
        }

        return $readyForUpload->count();
    }
}
