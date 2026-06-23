<?php

declare(strict_types=1);

namespace App\Repositories;

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
            'status' => 'pending',
        ]);
    }

    public function updateStatus(int $batchId, string $flickrPhotoId, string $status, ?string $error = null): void
    {
        $this->newQuery()
            ->where('transfer_batch_id', $batchId)
            ->where('flickr_photo_id', $flickrPhotoId)
            ->update([
                'status' => $status,
                'error_message' => $error,
            ]);
    }

    public function markCompleted(int $batchId, string $flickrPhotoId): void
    {
        $this->updateStatus($batchId, $flickrPhotoId, 'completed');
    }

    public function countByStatus(int $batchId, string $status): int
    {
        return $this->newQuery()
            ->where('transfer_batch_id', $batchId)
            ->where('status', $status)
            ->count();
    }

    public function countFailedSince(\DateTimeInterface $since): int
    {
        return $this->newQuery()
            ->where('status', 'failed')
            ->where('created_at', '>=', $since)
            ->count();
    }

    public function countFailedForConnectionSince(string $connectionKey, \DateTimeInterface $since): int
    {
        return $this->newQuery()
            ->where('status', 'failed')
            ->where('created_at', '>=', $since)
            ->whereHas('batch', fn ($q) => $q->where('connection_key', $connectionKey))
            ->count();
    }

    public function latestErrorMessage(int $batchId): ?string
    {
        $value = $this->newQuery()
            ->where('transfer_batch_id', $batchId)
            ->where('status', 'failed')
            ->whereNotNull('error_message')
            ->orderByDesc('id')
            ->value('error_message');

        return $value !== null ? (string) $value : null;
    }
}
