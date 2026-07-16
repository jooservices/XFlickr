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
    ) {}

    public function reconcile(TransferBatch|int|null $batch): void
    {
        if ($batch === null) {
            return;
        }

        $batchId = $batch instanceof TransferBatch ? $batch->id : $batch;

        $reconciled = $this->batches->reconcile($batchId);

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
