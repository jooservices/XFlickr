<?php

declare(strict_types=1);

namespace App\Services\Transfer;

use App\Enums\TransferBatchStatus;
use App\Enums\TransferItemStatus;
use App\Models\TransferBatch;
use App\Repositories\TransferBatchRepository;
use App\Repositories\TransferItemRepository;
use Illuminate\Support\Facades\DB;

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

        $batchId = $batch instanceof TransferBatch ? $batch->id : $batch;

        DB::transaction(function () use ($batchId): void {
            $batch = $this->batches->lockById($batchId);

            if ($batch === null) {
                return;
            }

            $completed = $this->items->countByStatus($batch->id, TransferItemStatus::Completed);
            $failed = $this->items->countByStatus($batch->id, TransferItemStatus::Failed);
            $processed = $completed + $failed;
            $status = TransferBatchStatus::tryFrom((string) $batch->status) ?? TransferBatchStatus::Running;

            if ($processed >= $batch->total_count) {
                $status = match (true) {
                    $failed >= $batch->total_count => TransferBatchStatus::Failed,
                    $failed > 0 => TransferBatchStatus::CompletedWithErrors,
                    default => TransferBatchStatus::Completed,
                };
            } elseif ($batch->status !== TransferBatchStatus::Running->value) {
                $status = TransferBatchStatus::Running;
            }

            $this->batches->updateCounts($batch, $completed, $failed, $status);
        });
    }

    public function sampleError(int $batchId): ?string
    {
        return $this->items->latestErrorMessage($batchId);
    }
}
