<?php

declare(strict_types=1);

namespace App\Services\Transfer;

use App\Enums\TransferBatchStatus;
use App\Enums\TransferItemStatus;
use App\Models\TransferBatch;
use App\Repositories\TransferBatchRepository;
use App\Repositories\TransferItemRepository;

final class TransferBatchReconciler
{
    public function __construct(
        private readonly TransferBatchRepository $batches,
        private readonly TransferItemRepository $items,
    ) {}

    public function reconcile(TransferBatch|int|null $batch): void
    {
        if ($batch === null) {
            return;
        }

        $batch = $batch instanceof TransferBatch
            ? $batch->fresh()
            : $this->batches->findById($batch);

        if ($batch === null) {
            return;
        }

        $completed = $this->items->countByStatus($batch->id, TransferItemStatus::Completed);
        $failed = $this->items->countByStatus($batch->id, TransferItemStatus::Failed);
        $processed = $completed + $failed;
        $status = TransferBatchStatus::tryFrom((string) $batch->status) ?? TransferBatchStatus::Running;

        if ($processed >= $batch->total_count) {
            $status = $failed >= $batch->total_count ? TransferBatchStatus::Failed : TransferBatchStatus::Completed;
        } elseif ($batch->status !== TransferBatchStatus::Running->value) {
            $status = TransferBatchStatus::Running;
        }

        $this->batches->updateCounts($batch, $completed, $failed, $status);
    }

    public function sampleError(int $batchId): ?string
    {
        return $this->items->latestErrorMessage($batchId);
    }
}
