<?php

declare(strict_types=1);

namespace App\Services\Flickr;

use App\Enums\PhotoTransferExecutionOutcome;
use App\Enums\StorageUploadStatus;
use App\Enums\StoredFileStatus;
use App\Enums\TransferItemStatus;
use App\Repositories\StorageUploadRepository;
use App\Repositories\StoredFileRepository;
use App\Repositories\TransferItemRepository;
use App\Services\Storage\StorageUploadService;
use App\Services\Transfer\TransferBatchReconciler;
use App\Support\FlickrPhotoUrlHelper;
use Exception;
use Illuminate\Support\Facades\Storage;

final class PhotoUploadExecutionService
{
    public function __construct(
        private readonly StorageUploadService $uploadService,
        private readonly TransferBatchReconciler $batchReconciler,
        private readonly StoredFileRepository $storedFiles,
        private readonly StorageUploadRepository $storageUploads,
        private readonly TransferItemRepository $items,
    ) {}

    public function execute(
        string $flickrPhotoId,
        int $storageAccountId,
        ?int $batchId,
        string $ownerNsid,
    ): PhotoTransferExecutionOutcome {
        $storedFile = $this->storedFiles->findOriginalByFlickrPhotoId($flickrPhotoId);

        if ($storedFile === null || $storedFile->status !== StoredFileStatus::Completed->value) {
            return PhotoTransferExecutionOutcome::Deferred;
        }

        $upload = $this->storageUploads->firstOrCreateForAccount($storedFile->id, $storageAccountId);

        if ($upload->status === StorageUploadStatus::Completed->value) {
            $this->markItemCompleted($batchId, $flickrPhotoId);
            $this->batchReconciler->reconcile($batchId);

            return PhotoTransferExecutionOutcome::Completed;
        }

        try {
            $this->storageUploads->markUploading($storedFile->id, $storageAccountId);
            $this->updateItemStatus($batchId, $flickrPhotoId, TransferItemStatus::Processing);

            $localPath = $storedFile->local_path;
            if ($localPath === null || ! Storage::exists($localPath)) {
                throw new Exception("Local cached file missing at: {$localPath}");
            }

            $fileOwnerNsid = $storedFile->owner_nsid;
            $extension = FlickrPhotoUrlHelper::resolveExtension((string) $localPath);
            $remoteMetadata = $this->uploadService->uploadStream(
                $storageAccountId,
                Storage::path($localPath),
                'Flickr/'.$fileOwnerNsid.'/Photos/'.FlickrPhotoUrlHelper::originalNameFor($storedFile->flickr_photo_id, $extension),
            );

            $this->storageUploads->markCompletedForAccount($storedFile->id, $storageAccountId, $remoteMetadata);

            $this->markItemCompleted($batchId, $flickrPhotoId);
            $this->batchReconciler->reconcile($batchId);

            return PhotoTransferExecutionOutcome::Completed;
        } catch (Exception $e) {
            $this->storageUploads->markPendingForAccount($storedFile->id, $storageAccountId, $e->getMessage());
            $this->updateItemStatus($batchId, $flickrPhotoId, TransferItemStatus::Processing, $e->getMessage());

            throw $e;
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

        $this->updateItemStatus($batchId, $flickrPhotoId, TransferItemStatus::Failed, $errorMessage);
        $this->batchReconciler->reconcile($batchId);
    }

    private function markItemCompleted(?int $batchId, string $flickrPhotoId): void
    {
        if ($batchId === null) {
            return;
        }

        $this->items->markCompleted($batchId, $flickrPhotoId);
    }

    private function updateItemStatus(?int $batchId, string $flickrPhotoId, TransferItemStatus $status, ?string $error = null): void
    {
        if ($batchId === null) {
            return;
        }

        $this->items->updateStatus($batchId, $flickrPhotoId, $status, $error);
    }
}
