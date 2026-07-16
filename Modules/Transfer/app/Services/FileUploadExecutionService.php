<?php

declare(strict_types=1);

namespace Modules\Transfer\Services;

use Exception;
use Illuminate\Support\Facades\Storage;
use Modules\Flickr\Services\FlickrPhotoSourceService;
use Modules\Storage\Dto\StorageUploadRequest;
use Modules\Storage\Services\StorageService;
use Modules\Transfer\Enums\StorageUploadStatus;
use Modules\Transfer\Enums\StoredFileStatus;
use Modules\Transfer\Enums\TransferExecutionOutcome;
use Modules\Transfer\Enums\TransferItemStatus;
use Modules\Transfer\Models\StoredFile;
use Modules\Transfer\Repositories\StorageUploadRepository;
use Modules\Transfer\Repositories\StoredFileRepository;
use Modules\Transfer\Repositories\TransferBatchRepository;
use Modules\Transfer\Repositories\TransferItemRepository;
use Modules\Transfer\Support\TransferRuntimeConfig;

final class FileUploadExecutionService
{
    public function __construct(
        private readonly StorageService $storage,
        private readonly TransferBatchReconciler $batchReconciler,
        private readonly StoredFileRepository $storedFiles,
        private readonly StorageUploadRepository $storageUploads,
        private readonly TransferItemRepository $items,
        private readonly TransferBatchRepository $batches,
        private readonly TransferRuntimeConfig $transferConfig,
        private readonly FlickrPhotoSourceService $photoSources,
    ) {}

    public function execute(
        int $storedFileId,
        int $storageAccountId,
        ?int $batchId,
    ): TransferExecutionOutcome {
        $storedFile = $this->storedFiles->findById($storedFileId);

        if ($storedFile === null || $storedFile->status !== StoredFileStatus::Completed->value) {
            return TransferExecutionOutcome::Deferred;
        }

        $sourceId = (string) $storedFile->source_id;

        $upload = $this->storageUploads->firstOrCreateForAccount($storedFile->id, $storageAccountId);

        if ($upload->status === StorageUploadStatus::Completed->value) {
            $this->markItemCompleted($batchId, $sourceId);
            $this->batchReconciler->reconcile($batchId);

            return TransferExecutionOutcome::Completed;
        }

        try {
            $this->storageUploads->markUploading($storedFile->id, $storageAccountId);
            $this->updateItemStatus($batchId, $sourceId, TransferItemStatus::Processing);

            $localPath = $storedFile->local_path;
            if ($localPath === null || ! Storage::exists($localPath)) {
                throw new Exception("Local cached file missing at: {$localPath}");
            }

            $remotePath = $this->photoSources->remoteUploadPath(
                (string) $storedFile->source_owner,
                (string) $storedFile->source_id,
                $localPath,
            );

            $batch = $batchId !== null ? $this->batches->findById($batchId) : null;
            $albumLabel = null;
            if ($batch !== null && $batch->group_label !== null && $batch->group_label !== '' && $this->transferConfig->shouldCreateGooglePhotosAlbums()) {
                $albumLabel = $batch->group_label;
            }

            $remoteMetadata = $this->storage->upload(
                $storageAccountId,
                new StorageUploadRequest(
                    localPath: Storage::path($localPath),
                    remotePath: $remotePath,
                    albumLabel: $albumLabel,
                ),
            )->toArray();

            $this->storageUploads->markCompletedForAccount($storedFile->id, $storageAccountId, $remoteMetadata);

            $this->markItemCompleted($batchId, $sourceId);
            $this->deleteLocalFileIfRequested($batchId, $storedFile);
            $this->batchReconciler->reconcile($batchId);

            return TransferExecutionOutcome::Completed;
        } catch (Exception $e) {
            $this->storageUploads->markPendingForAccount($storedFile->id, $storageAccountId, $e->getMessage());
            $this->updateItemStatus($batchId, $sourceId, TransferItemStatus::Processing, $e->getMessage());

            throw $e;
        }
    }

    public function handleFailure(
        int $storedFileId,
        int $storageAccountId,
        ?int $batchId,
        string $errorMessage,
    ): void {
        $storedFile = $this->storedFiles->findById($storedFileId);

        if ($storedFile !== null) {
            $this->storageUploads->markFailedForAccount(
                $storedFile->id,
                $storageAccountId,
                $errorMessage,
            );

            $sourceId = (string) $storedFile->source_id;
            $this->updateItemStatus($batchId, $sourceId, TransferItemStatus::Failed, $errorMessage);
        }

        $this->batchReconciler->reconcile($batchId);
    }

    private function markItemCompleted(?int $batchId, string $sourceId): void
    {
        if ($batchId === null) {
            return;
        }

        $this->items->markCompleted($batchId, $sourceId);
    }

    private function updateItemStatus(?int $batchId, string $sourceId, TransferItemStatus $status, ?string $error = null): void
    {
        if ($batchId === null) {
            return;
        }

        $this->items->updateStatus($batchId, $sourceId, $status, $error);
    }

    private function deleteLocalFileIfRequested(?int $batchId, StoredFile $storedFile): void
    {
        if (! $this->shouldDeleteLocal($batchId)) {
            return;
        }

        $localPath = $storedFile->local_path;
        if ($localPath === null || $localPath === '') {
            return;
        }

        if (Storage::exists($localPath)) {
            Storage::delete($localPath);
        }

        $this->storedFiles->clearLocalPath($storedFile);
    }

    private function shouldDeleteLocal(?int $batchId): bool
    {
        if ($batchId !== null) {
            $batch = $this->batches->findById($batchId);
            if ($batch !== null && $batch->delete_local_after_upload !== null) {
                return $batch->delete_local_after_upload;
            }
        }

        return $this->transferConfig->shouldDeleteLocalAfterUpload();
    }
}
