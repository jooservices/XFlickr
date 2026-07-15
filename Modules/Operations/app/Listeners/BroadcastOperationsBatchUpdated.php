<?php

declare(strict_types=1);

namespace Modules\Operations\Listeners;

use Modules\Operations\Services\OperationsBroadcastService;
use Modules\Storage\Events\TransferBatchReconciled;

final class BroadcastOperationsBatchUpdated
{
    public function __construct(
        private readonly OperationsBroadcastService $broadcast,
    ) {}

    public function handle(TransferBatchReconciled $event): void
    {
        $this->broadcast->broadcastBatchUpdated([
            'id' => $event->batchId,
            'type' => $event->type,
            'connection_key' => $event->connectionKey,
            'subject_nsid' => $event->subjectNsid,
            'group_type' => $event->groupType,
            'group_id' => $event->groupId,
            'group_label' => $event->groupLabel,
            'storage_account_id' => $event->storageAccountId,
            'status' => $event->status,
            'total_count' => $event->totalCount,
            'completed_count' => $event->completedCount,
            'failed_count' => $event->failedCount,
            'sample_error' => $event->sampleError,
            'updated_at' => $event->updatedAt,
        ]);
    }
}
