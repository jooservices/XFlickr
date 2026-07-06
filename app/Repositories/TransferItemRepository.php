<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\TransferItemStatus;
use App\Models\TransferItem;
use Jooservices\LaravelRepository\Repositories\EloquentRepository;
use Jooservices\LaravelRepository\Traits\HasCrud;
use Jooservices\LaravelRepository\Traits\HasFilter;

final class TransferItemRepository extends EloquentRepository
{
    use HasCrud;
    use HasFilter;

    public function __construct(TransferItem $model)
    {
        parent::__construct($model);
    }

    public function createPending(int $batchId, string $flickrPhotoId): TransferItem
    {
        return $this->newQuery()->create([
            'transfer_batch_id' => $batchId,
            'flickr_photo_id' => $flickrPhotoId,
            'status' => TransferItemStatus::Pending->value,
        ]);
    }

    /**
     * @param  list<string>  $flickrPhotoIds
     */
    public function createPendingBulk(int $batchId, array $flickrPhotoIds): void
    {
        if ($flickrPhotoIds === []) {
            return;
        }

        $now = now();

        $rows = array_map(
            static fn (string $flickrPhotoId): array => [
                'transfer_batch_id' => $batchId,
                'flickr_photo_id' => $flickrPhotoId,
                'status' => TransferItemStatus::Pending->value,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $flickrPhotoIds,
        );

        foreach (array_chunk($rows, 500) as $chunk) {
            $this->newQuery()->insert($chunk);
        }
    }

    public function updateStatus(int $batchId, string $flickrPhotoId, TransferItemStatus $status, ?string $error = null): void
    {
        $this->newQuery()
            ->where('transfer_batch_id', $batchId)
            ->where('flickr_photo_id', $flickrPhotoId)
            ->update([
                'status' => $status->value,
                'error_message' => $error,
            ]);
    }

    public function markCompleted(int $batchId, string $flickrPhotoId): void
    {
        $this->updateStatus($batchId, $flickrPhotoId, TransferItemStatus::Completed);
    }

    public function countByStatus(int $batchId, TransferItemStatus $status): int
    {
        return $this->newQuery()
            ->where('transfer_batch_id', $batchId)
            ->where('status', $status->value)
            ->count();
    }

    public function countFailedSince(\DateTimeInterface $since): int
    {
        return $this->newQuery()
            ->where('status', TransferItemStatus::Failed->value)
            ->where('created_at', '>=', $since)
            ->count();
    }

    public function countFailedForConnectionSince(string $connectionKey, \DateTimeInterface $since): int
    {
        return $this->newQuery()
            ->where('status', TransferItemStatus::Failed->value)
            ->where('created_at', '>=', $since)
            ->whereHas('batch', fn ($q) => $q->where('connection_key', $connectionKey))
            ->count();
    }

    public function latestErrorMessage(int $batchId): ?string
    {
        $value = $this->newQuery()
            ->where('transfer_batch_id', $batchId)
            ->where('status', TransferItemStatus::Failed->value)
            ->whereNotNull('error_message')
            ->orderByDesc('id')
            ->value('error_message');

        return $value !== null ? (string) $value : null;
    }
}
