<?php

declare(strict_types=1);

namespace Modules\Storage\Services;

use Illuminate\Support\Facades\DB;
use Modules\Storage\Enums\TransferBatchStatus;
use Modules\Storage\Enums\TransferItemStatus;
use Modules\Storage\Events\TransferBatchReconciled;
use Modules\Storage\Models\TransferBatch;
use Modules\Storage\Repositories\TransferBatchRepository;
use Modules\Storage\Repositories\TransferItemRepository;

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

        /** @var array<string, mixed>|null $reconciled */
        $reconciled = null;

        DB::transaction(function () use ($batchId, &$reconciled): void {
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

            $reconciled = [
                'batchId' => $batch->id,
                'type' => (string) $batch->type,
                'connectionKey' => (string) $batch->connection_key,
                'subjectNsid' => $batch->subject_nsid !== null ? (string) $batch->subject_nsid : null,
                'status' => $status->value,
                'totalCount' => (int) $batch->total_count,
                'completedCount' => $completed,
                'failedCount' => $failed,
                'sampleError' => $this->sampleError($batch->id),
                'groupType' => $batch->group_type !== null ? (string) $batch->group_type : null,
                'groupId' => $batch->group_id !== null ? (string) $batch->group_id : null,
                'groupLabel' => $batch->group_label !== null ? (string) $batch->group_label : null,
                'storageAccountId' => $batch->storage_account_id !== null ? (int) $batch->storage_account_id : null,
                'updatedAt' => now()->toISOString(),
            ];
        });

        if ($reconciled === null) {
            return;
        }

        event(new TransferBatchReconciled(
            batchId: $reconciled['batchId'],
            type: $reconciled['type'],
            connectionKey: $reconciled['connectionKey'],
            subjectNsid: $reconciled['subjectNsid'],
            status: $reconciled['status'],
            totalCount: $reconciled['totalCount'],
            completedCount: $reconciled['completedCount'],
            failedCount: $reconciled['failedCount'],
            sampleError: $reconciled['sampleError'],
            groupType: $reconciled['groupType'],
            groupId: $reconciled['groupId'],
            groupLabel: $reconciled['groupLabel'],
            storageAccountId: $reconciled['storageAccountId'],
            updatedAt: $reconciled['updatedAt'],
        ));
    }

    public function sampleError(int $batchId): ?string
    {
        return $this->items->latestErrorMessage($batchId);
    }
}
