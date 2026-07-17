<?php

declare(strict_types=1);

namespace Modules\Transfer\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TransferBatchReconciled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $batchId,
        public readonly string $type,
        public readonly string $connectionKey,
        public readonly ?string $subjectNsid,
        public readonly string $status,
        public readonly int $totalCount,
        public readonly int $completedCount,
        public readonly int $failedCount,
        public readonly ?string $sampleError,
        public readonly ?string $groupType = null,
        public readonly ?string $groupId = null,
        public readonly ?string $groupLabel = null,
        public readonly ?int $storageAccountId = null,
        public readonly ?string $updatedAt = null,
        public readonly int $pendingCount = 0,
        public readonly int $processingCount = 0,
    ) {}
}
