<?php

declare(strict_types=1);

namespace App\Services\Flickr;

use App\Enums\PhotoTransferExecutionOutcome;
use App\Jobs\DownloadPhotoJob;
use App\Repositories\StorageUploadRepository;
use App\Repositories\StoredFileRepository;
use App\Repositories\TransferBatchRepository;
use App\Repositories\TransferItemRepository;
use App\Services\Storage\StorageUploadService;
use App\Services\Transfer\TransferBatchReconciler;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

final class PhotoUploadExecutionService
{
    public function __construct(
        private readonly StorageUploadService $uploadService,
        private readonly TransferBatchReconciler $batchReconciler,
        private readonly StoredFileRepository $storedFiles,
        private readonly StorageUploadRepository $storageUploads,
        private readonly TransferBatchRepository $batches,
        private readonly TransferItemRepository $items,
    ) {}

    public function execute(
        string $flickrPhotoId,
        int $storageAccountId,
        ?int $batchId,
        string $ownerNsid,
        int $attempt,
        int $maxAttempts,
    ): PhotoTransferExecutionOutcome {
        $storedFile = $this->storedFiles->findOriginalByFlickrPhotoId($flickrPhotoId);

        if ($storedFile === null || $storedFile->status !== 'completed') {
            if ($ownerNsid !== '') {
                $connectionKey = $batchId !== null
                    ? ($this->batches->connectionKeyForId($batchId) ?? '')
                    : '';

                if ($connectionKey !== '') {
                    DownloadPhotoJob::dispatch(
                        $flickrPhotoId,
                        $ownerNsid,
                        $connectionKey,
                        $batchId,
                    );
                }
            }

            return PhotoTransferExecutionOutcome::Deferred;
        }

        $upload = $this->storageUploads->firstOrCreateForAccount($storedFile->id, $storageAccountId);

        if ($upload->status === 'completed') {
            $this->markItemCompleted($batchId, $flickrPhotoId);
            $this->batchReconciler->reconcile($batchId);

            return PhotoTransferExecutionOutcome::Completed;
        }

        $lockKey = "upload_lock:storage_account:{$storageAccountId}";
        $lock = Cache::lock($lockKey, 300);

        try {
            $lock->block(60);

            $this->storageUploads->markUploading($storedFile->id, $storageAccountId);
            $this->updateItemStatus($batchId, $flickrPhotoId, 'processing');

            $localPath = $storedFile->local_path;
            if ($localPath === null || ! Storage::exists($localPath)) {
                throw new Exception("Local cached file missing at: {$localPath}");
            }

            $fileOwnerNsid = $storedFile->owner_nsid;
            $remoteMetadata = $this->uploadService->uploadStream(
                $storageAccountId,
                Storage::path($localPath),
                "Flickr/{$fileOwnerNsid}/Photos/{$storedFile->flickr_photo_id}_original.jpg",
            );

            $this->storageUploads->markCompletedForAccount($storedFile->id, $storageAccountId, $remoteMetadata);

            $this->markItemCompleted($batchId, $flickrPhotoId);
            $this->batchReconciler->reconcile($batchId);

            return PhotoTransferExecutionOutcome::Completed;
        } catch (Exception $e) {
            if ($attempt < $maxAttempts) {
                $this->storageUploads->markPendingForAccount($storedFile->id, $storageAccountId, $e->getMessage());
                $this->updateItemStatus($batchId, $flickrPhotoId, 'processing', $e->getMessage());
            }

            throw $e;
        } finally {
            $lock->release();
        }
    }

    public function handleFailure(
        string $flickrPhotoId,
        int $storageAccountId,
        ?int $batchId,
        string $errorMessage,
    ): void {
        $storedFile = $this->storedFiles->findOriginalByFlickrPhotoId($flickrPhotoId);

        if ($storedFile !== null) {
            $this->storageUploads->markFailedForAccount(
                $storedFile->id,
                $storageAccountId,
                $errorMessage,
            );
        }

        $this->updateItemStatus($batchId, $flickrPhotoId, 'failed', $errorMessage);
        $this->batchReconciler->reconcile($batchId);
    }

    private function markItemCompleted(?int $batchId, string $flickrPhotoId): void
    {
        if ($batchId === null) {
            return;
        }

        $this->items->markCompleted($batchId, $flickrPhotoId);
    }

    private function updateItemStatus(?int $batchId, string $flickrPhotoId, string $status, ?string $error = null): void
    {
        if ($batchId === null) {
            return;
        }

        $this->items->updateStatus($batchId, $flickrPhotoId, $status, $error);
    }
}
