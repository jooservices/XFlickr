<?php

declare(strict_types=1);

namespace App\Services\Transfer;

use App\Enums\TransferItemStatus;
use App\Enums\TransferType;
use App\Jobs\DownloadPhotoJob;
use App\Jobs\UploadPhotoJob;
use App\Models\TransferBatch;
use App\Repositories\Crawler\PhotoQueryRepository;
use App\Repositories\TransferItemRepository;
use Illuminate\Validation\ValidationException;
use JOOservices\XFlickrCrawler\Models\Connection;

final class TransferItemRetryService
{
    public function __construct(
        private readonly TransferItemRepository $items,
        private readonly TransferBatchReconciler $batchReconciler,
        private readonly PhotoQueryRepository $photos,
    ) {}

    public function retry(Connection $connection, TransferBatch $batch, string $flickrPhotoId): void
    {
        if ($batch->connection_key !== $connection->connection_key) {
            abort(404);
        }

        $item = $this->items->findForBatch($batch->id, $flickrPhotoId);

        if ($item === null || $item->status !== TransferItemStatus::Failed->value) {
            throw ValidationException::withMessages([
                'flickr_photo_id' => 'Only failed transfer items can be retried.',
            ]);
        }

        $this->items->updateStatus($batch->id, $flickrPhotoId, TransferItemStatus::Pending);
        $this->batchReconciler->reconcile($batch->id);

        $ownerNsid = $batch->subject_nsid ?? $connection->connection_key;
        $photo = $this->photos->findByFlickrPhotoId($flickrPhotoId, ['owner_nsid']);

        if ($photo !== null) {
            $photoOwnerNsid = $photo->getAttribute('owner_nsid');

            if (is_string($photoOwnerNsid) && $photoOwnerNsid !== '') {
                $ownerNsid = $photoOwnerNsid;
            }
        }

        $type = TransferType::tryFrom((string) $batch->type);

        if ($type === TransferType::Download) {
            DownloadPhotoJob::dispatch(
                $flickrPhotoId,
                $ownerNsid,
                $connection->connection_key,
                $batch->id,
            );

            return;
        }

        if ($batch->storage_account_id === null) {
            throw ValidationException::withMessages([
                'batch' => 'Upload batch is missing a storage account.',
            ]);
        }

        UploadPhotoJob::dispatch(
            $flickrPhotoId,
            (int) $batch->storage_account_id,
            $batch->id,
            $ownerNsid,
            (int) $batch->total_count,
        );
    }
}
