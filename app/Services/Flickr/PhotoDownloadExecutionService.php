<?php

declare(strict_types=1);

namespace App\Services\Flickr;

use App\Enums\PhotoTransferExecutionOutcome;
use App\Enums\StoredFileStatus;
use App\Enums\TransferItemStatus;
use App\Repositories\Crawler\ConnectionQueryRepository;
use App\Repositories\Crawler\PhotoQueryRepository;
use App\Repositories\StoredFileRepository;
use App\Repositories\TransferItemRepository;
use App\Services\Transfer\TransferBatchReconciler;
use App\Support\FlickrPhotoUrlHelper;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use JOOservices\XFlickrCrawler\Models\Photo as CrawlerPhoto;
use RuntimeException;

final class PhotoDownloadExecutionService
{
    public function __construct(
        private readonly FlickrPhotoSizeResolver $resolver,
        private readonly TransferBatchReconciler $batchReconciler,
        private readonly ConnectionQueryRepository $connections,
        private readonly PhotoQueryRepository $photos,
        private readonly StoredFileRepository $storedFiles,
        private readonly TransferItemRepository $items,
    ) {}

    public function execute(
        string $flickrPhotoId,
        string $ownerNsid,
        string $connectionKey,
        ?int $batchId,
    ): PhotoTransferExecutionOutcome {
        $lockKey = "download_lock:{$flickrPhotoId}";
        $lock = Cache::lock($lockKey, 120);

        if (! $lock->get()) {
            return PhotoTransferExecutionOutcome::Deferred;
        }

        $partPath = null;

        try {
            $connection = $this->connections->findByConnectionKey($connectionKey);
            if ($connection === null) {
                throw new RuntimeException("Flickr connection [{$connectionKey}] was not found.");
            }

            $photo = $this->photos->findByFlickrPhotoId($flickrPhotoId);
            if ($photo === null) {
                throw new RuntimeException("Photo [{$flickrPhotoId}] was not found in catalog.");
            }

            $storedFile = $this->storedFiles->firstOrCreateOriginal($flickrPhotoId, $ownerNsid);

            if ($storedFile->status === StoredFileStatus::Completed->value) {
                $this->markItemCompleted($batchId, $flickrPhotoId);
                $this->batchReconciler->reconcile($batchId);

                return PhotoTransferExecutionOutcome::Completed;
            }

            $this->storedFiles->markDownloading($flickrPhotoId);
            $this->updateItemStatus($batchId, $flickrPhotoId, TransferItemStatus::Processing);

            $download = $this->resolver->resolve($flickrPhotoId, $connection);
            $finalPath = $this->finalPathFor($flickrPhotoId, $ownerNsid, $photo, $download->url);
            $partPath = "{$finalPath}.part";

            Storage::makeDirectory(dirname($finalPath));
            $response = Http::timeout((int) config('xflickr.download.timeout_seconds', 120))->withHeaders([
                'User-Agent' => 'XFlickr Download Client 1.0',
            ])->sink(Storage::path($partPath))->get($download->url);

            if (! $response->successful()) {
                throw new Exception('HTTP download failed with status: '.$response->status());
            }

            Storage::move($partPath, $finalPath);
            $partPath = null;

            $size = Storage::size($finalPath);
            $sha256 = hash_file('sha256', Storage::path($finalPath));

            $extension = FlickrPhotoUrlHelper::resolveExtension(
                $download->url,
                is_array($photo->raw_payload) ? ($photo->raw_payload['originalformat'] ?? null) : null,
            );

            $this->storedFiles->markCompleted($flickrPhotoId, [
                'local_path' => $finalPath,
                'original_name' => FlickrPhotoUrlHelper::originalNameFor($flickrPhotoId, $extension),
                'bytes' => $size,
                'content_sha256' => $sha256,
                'downloaded_at' => now(),
                'error_message' => null,
                'metadata' => [
                    'download_variant' => $download->variant,
                    'sizes_source' => 'flickr.photos.getSizes',
                ],
            ]);

            $this->markItemCompleted($batchId, $flickrPhotoId);
            $this->batchReconciler->reconcile($batchId);

            return PhotoTransferExecutionOutcome::Completed;
        } catch (Exception $e) {
            if ($partPath !== null && Storage::exists($partPath)) {
                Storage::delete($partPath);
            }

            $this->storedFiles->markPending($flickrPhotoId, $e->getMessage());
            $this->updateItemStatus($batchId, $flickrPhotoId, TransferItemStatus::Processing, $e->getMessage());

            throw $e;
        } finally {
            $lock->release();
        }
    }

    public function handleFailure(string $flickrPhotoId, ?int $batchId, string $errorMessage): void
    {
        $this->storedFiles->markFailed($flickrPhotoId, $errorMessage);
        $this->updateItemStatus($batchId, $flickrPhotoId, TransferItemStatus::Failed, $errorMessage);
        $this->batchReconciler->reconcile($batchId);
    }

    private function finalPathFor(string $flickrPhotoId, string $ownerNsid, CrawlerPhoto $photo, string $downloadUrl): string
    {
        $secret = $photo->secret ?? 'unknown';
        $extension = FlickrPhotoUrlHelper::resolveExtension(
            $downloadUrl,
            is_array($photo->raw_payload) ? ($photo->raw_payload['originalformat'] ?? null) : null,
        );

        return "flickr/{$ownerNsid}/photos/{$flickrPhotoId}_{$secret}.{$extension}";
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
