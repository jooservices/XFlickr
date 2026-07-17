<?php

declare(strict_types=1);

namespace Modules\Transfer\Services;

use Modules\Transfer\Events\TransferBatchReconciled;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Repositories\TransferBatchRepository;
use Modules\Transfer\Repositories\TransferItemRepository;

final class TransferBatchReconciler
{
    public function __construct(
        private readonly TransferBatchRepository $batches,
        private readonly TransferItemRepository $items,
        private readonly TransferObservability $observability,
    ) {}

    public function reconcile(TransferBatch|int|null $batch): void
    {
        if ($batch === null) {
            return;
        }

        $batchId = $batch instanceof TransferBatch ? $batch->id : $batch;
        $existing = $batch instanceof TransferBatch ? $batch : $this->batches->findById($batchId);
        $previousStatus = $existing !== null ? (string) $existing->status : null;

        $reconciled = $this->batches->reconcile($batchId);

        if ($reconciled === null) {
            return;
        }

        $openCounts = $this->items->countOpenGroupedByBatchIds([$batchId]);
        $open = $openCounts[$batchId] ?? ['pending' => 0, 'processing' => 0];

        if ($previousStatus !== null && $previousStatus !== $reconciled['status'] && $existing !== null) {
            $existing->status = $reconciled['status'];
            $existing->completed_count = $reconciled['completedCount'];
            $existing->failed_count = $reconciled['failedCount'];
            $this->observability->batchStatusChanged($existing, $previousStatus, $reconciled['status']);
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
            pendingCount: $open['pending'],
            processingCount: $open['processing'],
        ));
    }

    public function sampleError(int $batchId): ?string
    {
        return $this->items->latestErrorMessage($batchId);
    }

    /** @param list<int> $batchIds @return array<int, string> */
    public function sampleErrors(array $batchIds): array
    {
        return $this->items->latestErrorsByBatchIds($batchIds);
    }
}
