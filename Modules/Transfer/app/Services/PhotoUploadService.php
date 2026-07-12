<?php

declare(strict_types=1);

namespace Modules\Transfer\Services;

use App\Repositories\Crawler\PhotoQueryRepository;
use Illuminate\Support\Collection;
use JOOservices\XFlickrCrawler\Models\Connection;
use JOOservices\XFlickrCrawler\Models\Photo;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Repositories\StorageAccountRepository;
use Modules\Storage\Repositories\StorageUploadRepository;
use Modules\Transfer\Enums\StoredFileStatus;
use Modules\Transfer\Enums\TransferType;
use Modules\Transfer\Jobs\FanOutTransferBatchJob;
use Modules\Transfer\Jobs\UploadPhotoJob;
use Modules\Transfer\Repositories\StoredFileRepository;
use Modules\Transfer\Repositories\TransferBatchRepository;
use Modules\Transfer\Repositories\TransferItemRepository;

final class PhotoUploadService
{
    private const CHUNK_SIZE = 250;

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
     * @param  list<string>  $contactNsids
     */
    public function queueFromInput(
        Connection $connection,
        ?int $storageAccountId = null,
        ?string $flickrPhotoId = null,
        ?string $contactNsid = null,
        array $contactNsids = [],
    ): TransferQueueResult {
        $storageAccount = $this->resolveStorageAccount($storageAccountId);

        if ($storageAccount === null) {
            return TransferQueueResult::error('No storage account configured.');
        }

        if ($flickrPhotoId !== null && $flickrPhotoId !== '') {
            $queued = $this->queuePhotoUpload($connection, $storageAccount, $flickrPhotoId);

            return TransferQueueResult::success(
                $queued === 0 ? 'No upload queued for this photo.' : 'Photo upload queued.',
                $queued,
            );
        }

        if ($contactNsids !== []) {
            $queued = 0;

            foreach ($contactNsids as $selectedContactNsid) {
                $queued += $this->queueUploads($connection, $storageAccount, $selectedContactNsid);
            }

            $contactCount = count($contactNsids);

            return TransferQueueResult::success(
                $queued === 0
                    ? 'No photos pending upload.'
                    : "{$queued} photo(s) queued for upload across {$contactCount} contact(s).",
                $queued,
            );
        }

        $queued = $this->queueUploads($connection, $storageAccount, $contactNsid);

        return TransferQueueResult::success(
            $queued === 0
                ? 'No photos pending upload.'
                : ($contactNsid !== null && $contactNsid !== ''
                    ? "{$queued} photo(s) queued for upload."
                    : 'Account photo upload queued.'),
            $queued,
        );
    }

    /**
     * @return int Number of fan-out jobs queued
     */
    public function queueUploads(Connection $connection, StorageAccount $storageAccount, ?string $ownerNsid = null): int
    {
        $ownerNsid = ($ownerNsid !== null && $ownerNsid !== '')
            ? $ownerNsid
            : $connection->connection_key;

        if (! $this->photos->existsForOwnerNsid($ownerNsid)) {
            return 0;
        }

        FanOutTransferBatchJob::dispatch(
            transferType: TransferType::Upload,
            connectionKey: $connection->connection_key,
            ownerNsid: $ownerNsid,
            storageAccountId: $storageAccount->id,
        );

        return 1;
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
     * @return int Number of photos queued
     */
    public function fanOutUploads(Connection $connection, StorageAccount $storageAccount, ?string $subjectNsid = null): int
    {
        $ownerNsid = ($subjectNsid !== null && $subjectNsid !== '')
            ? $subjectNsid
            : $connection->connection_key;

        $queuedCount = 0;

        $this->photos->chunkByOwnerNsid(
            $ownerNsid,
            self::CHUNK_SIZE,
            function (Collection $chunk) use (
                $connection,
                $storageAccount,
                $subjectNsid,
                &$queuedCount,
            ): void {
                $photoIds = $chunk->pluck('flickr_photo_id')->all();
                $storedFiles = $this->storedFiles->originalsByFlickrPhotoIds($photoIds);
                $completedUploadFileIds = $this->storageUploads->completedStoredFileIdsForAccount(
                    $storedFiles->pluck('id')->all(),
                    $storageAccount->id,
                );

                /** @var Collection<int, Photo> $needsDownload */
                $needsDownload = collect();
                /** @var Collection<int, Photo> $readyForUpload */
                $readyForUpload = collect();

                foreach ($chunk as $photo) {
                    $storedFile = $storedFiles->get($photo->flickr_photo_id);

                    if ($storedFile === null || $storedFile->status !== StoredFileStatus::Completed->value) {
                        $needsDownload->push($photo);

                        continue;
                    }

                    if (! in_array($storedFile->id, $completedUploadFileIds, true)) {
                        $readyForUpload->push($photo);
                    }
                }

                if ($needsDownload->isNotEmpty()) {
                    $firstNeedingDownload = $needsDownload->first();
                    $downloadOwnerNsid = $subjectNsid ?? $firstNeedingDownload->owner_nsid;
                    $this->downloads->queueSelectedDownloads($connection, $needsDownload, $downloadOwnerNsid);
                }

                if ($readyForUpload->isNotEmpty()) {
                    $queuedCount += $this->dispatchUploadBatch(
                        $connection,
                        $storageAccount,
                        $readyForUpload,
                        $subjectNsid,
                    );
                }
            },
        );

        return $queuedCount;
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
            $downloadOwnerNsid = $subjectNsid ?? $firstNeedingDownload->owner_nsid;
            $this->downloads->queueSelectedDownloads($connection, $needsDownload, $downloadOwnerNsid);
        }

        if ($readyForUpload->isEmpty()) {
            return 0;
        }

        return $this->dispatchUploadBatch($connection, $storageAccount, $readyForUpload, $subjectNsid);
    }

    /**
     * @param  Collection<int, Photo>  $readyForUpload
     */
    private function dispatchUploadBatch(
        Connection $connection,
        StorageAccount $storageAccount,
        Collection $readyForUpload,
        ?string $subjectNsid,
    ): int {
        $batch = $this->batches->createUploadBatch(
            $connection,
            $storageAccount->id,
            $readyForUpload->count(),
            $subjectNsid,
        );

        $this->items->createPendingBulk(
            $batch->id,
            $readyForUpload->pluck('flickr_photo_id')->all(),
        );

        foreach ($readyForUpload as $photo) {
            UploadPhotoJob::dispatch(
                $photo->flickr_photo_id,
                $storageAccount->id,
                $batch->id,
                $photo->owner_nsid,
                $readyForUpload->count(),
            );
        }

        return $readyForUpload->count();
    }
}
