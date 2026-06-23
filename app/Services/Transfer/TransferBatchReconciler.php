<?php

declare(strict_types=1);

namespace App\Services\Transfer;

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

        $completed = $this->items->countByStatus($batch->id, 'completed');
        $failed = $this->items->countByStatus($batch->id, 'failed');
        $processed = $completed + $failed;
        $status = $batch->status;

        if ($processed >= $batch->total_count) {
            $status = $failed >= $batch->total_count ? 'failed' : 'completed';
        } elseif ($batch->status !== 'running') {
            $status = 'running';
        }

        $this->batches->updateCounts($batch, $completed, $failed, $status);
    }

    public function sampleError(int $batchId): ?string
    {
        return $this->items->latestErrorMessage($batchId);
    }
}
