<?php

declare(strict_types=1);

namespace App\Services\Flickr;

use App\Enums\PhotoTransferExecutionOutcome;
use App\Repositories\Crawler\ConnectionQueryRepository;
use App\Repositories\Crawler\PhotoQueryRepository;
use App\Repositories\StoredFileRepository;
use App\Repositories\TransferItemRepository;
use App\Services\Transfer\TransferBatchReconciler;
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
        int $attempt,
        int $maxAttempts,
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

            if ($storedFile->status === 'completed') {
                $this->markItemCompleted($batchId, $flickrPhotoId);
                $this->batchReconciler->reconcile($batchId);
                $lock->release();

                return PhotoTransferExecutionOutcome::Completed;
            }

            $this->storedFiles->markDownloading($flickrPhotoId);
            $this->updateItemStatus($batchId, $flickrPhotoId, 'processing');

            $download = $this->resolver->resolve($flickrPhotoId, $connection);
            $finalPath = $this->finalPathFor($flickrPhotoId, $ownerNsid, $photo);
            $partPath = "{$finalPath}.part";

            Storage::makeDirectory(dirname($finalPath));
            $response = Http::timeout(120)->withHeaders([
                'User-Agent' => 'XFlickr Download Client 1.0',
            ])->sink(Storage::path($partPath))->get($download['url']);

            if (! $response->successful()) {
                throw new Exception('HTTP download failed with status: '.$response->status());
            }

            Storage::move($partPath, $finalPath);
            $partPath = null;

            $size = Storage::size($finalPath);
            $sha256 = hash_file('sha256', Storage::path($finalPath));

            $this->storedFiles->markCompleted($flickrPhotoId, [
                'local_path' => $finalPath,
                'bytes' => $size,
                'content_sha256' => $sha256,
                'downloaded_at' => now(),
                'error_message' => null,
                'metadata' => [
                    'download_variant' => $download['variant'],
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

            if ($attempt < $maxAttempts) {
                $this->storedFiles->markPending($flickrPhotoId, $e->getMessage());
                $this->updateItemStatus($batchId, $flickrPhotoId, 'processing', $e->getMessage());
            }

            throw $e;
        } finally {
            $lock->release();
        }
    }

    public function handleFailure(string $flickrPhotoId, ?int $batchId, string $errorMessage): void
    {
        $this->storedFiles->markFailed($flickrPhotoId, $errorMessage);
        $this->updateItemStatus($batchId, $flickrPhotoId, 'failed', $errorMessage);
        $this->batchReconciler->reconcile($batchId);
    }

    private function finalPathFor(string $flickrPhotoId, string $ownerNsid, CrawlerPhoto $photo): string
    {
        $secret = $photo->secret ?? 'unknown';

        return "flickr/{$ownerNsid}/photos/{$flickrPhotoId}_{$secret}.jpg";
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
