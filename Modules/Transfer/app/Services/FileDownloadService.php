<?php

declare(strict_types=1);

namespace Modules\Transfer\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Flickr\Services\FlickrPhotoSourceService;
use Modules\Transfer\Enums\StoredFileStatus;
use Modules\Transfer\Enums\TransferExecutionOutcome;
use Modules\Transfer\Enums\TransferItemStatus;
use Modules\Transfer\Repositories\StoredFileRepository;
use Modules\Transfer\Repositories\TransferItemRepository;
use Modules\Transfer\Support\TransferRuntimeConfig;

final class FileDownloadService
{
    public function __construct(
        private readonly FlickrPhotoSourceService $photoSources,
        private readonly TransferBatchReconciler $reconciler,
        private readonly StoredFileRepository $storedFiles,
        private readonly TransferItemRepository $items,
        private readonly TransferRuntimeConfig $config,
    ) {}

    public function execute(
        string $sourceType,
        string $sourceId,
        string $sourceOwner,
        string $connectionKey,
        ?int $batchId,
    ): TransferExecutionOutcome {
        $lockKey = "download_lock:{$sourceType}:{$sourceId}";
        $lock = Cache::lock($lockKey, 120);

        if (! $lock->get()) {
            return TransferExecutionOutcome::Deferred;
        }

        $partPath = null;

        try {
            $storedFile = $this->storedFiles->firstOrCreateOriginal($sourceType, $sourceId, $sourceOwner);

            if ($storedFile->status === StoredFileStatus::Completed->value) {
                $this->markItemCompleted($batchId, $sourceId);
                $this->reconciler->reconcile($batchId);

                return TransferExecutionOutcome::Completed;
            }

            $this->storedFiles->markDownloading($sourceId);
            $this->updateItemStatus($batchId, $sourceId, TransferItemStatus::Processing);

            $resolved = $this->photoSources->resolveDownload($sourceId, $connectionKey);

            $partPath = "{$resolved->destinationPath}.part";
            Storage::makeDirectory(dirname($resolved->destinationPath));
            $response = Http::timeout($this->config->timeoutSeconds())->withHeaders([
                'User-Agent' => 'XFlickr Download Client 1.0',
            ])->sink(Storage::path($partPath))->get($resolved->url);

            if (! $response->successful()) {
                throw new Exception('HTTP download failed with status: '.$response->status());
            }

            Storage::move($partPath, $resolved->destinationPath);
            $partPath = null;

            $size = Storage::size($resolved->destinationPath);
            $sha256 = hash_file('sha256', Storage::path($resolved->destinationPath));

            $this->storedFiles->markCompleted($sourceId, [
                'local_path' => $resolved->destinationPath,
                'original_name' => $resolved->originalName,
                'bytes' => $size,
                'content_sha256' => $sha256,
                'downloaded_at' => now(),
                'error_message' => null,
                'metadata' => array_merge([
                    'download_variant' => $resolved->variant,
                ], $resolved->metadata),
            ]);

            $this->markItemCompleted($batchId, $sourceId);
            $this->reconciler->reconcile($batchId);

            return TransferExecutionOutcome::Completed;
        } catch (Exception $e) {
            if ($partPath !== null && Storage::exists($partPath)) {
                Storage::delete($partPath);
            }

            $this->storedFiles->markPending($sourceId, $e->getMessage());
            $this->updateItemStatus($batchId, $sourceId, TransferItemStatus::Processing, $e->getMessage());

            throw $e;
        } finally {
            $lock->release();
        }
    }

    public function handleFailure(string $sourceType, string $sourceId, ?int $batchId, string $error): void
    {
        $this->storedFiles->markFailed($sourceId, $error);
        $this->updateItemStatus($batchId, $sourceId, TransferItemStatus::Failed, $error);
        $this->reconciler->reconcile($batchId);
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
}
