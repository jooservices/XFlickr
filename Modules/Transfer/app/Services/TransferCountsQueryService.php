<?php

declare(strict_types=1);

namespace Modules\Transfer\Services;

use Modules\Transfer\Repositories\StoredFileRepository;
use Modules\Transfer\Repositories\TransferBatchRepository;
use Modules\Transfer\Repositories\TransferItemRepository;

final class TransferCountsQueryService
{
    public function __construct(
        private readonly StoredFileRepository $storedFiles,
        private readonly TransferBatchRepository $batches,
        private readonly TransferItemRepository $items,
    ) {}

    public function countStoredFiles(): int
    {
        return $this->storedFiles->countAll();
    }

    public function countBatchesByTypeAndStatus(string $type, string $status): int
    {
        return $this->batches->countByTypeAndStatus($type, $status);
    }

    /**
     * @param  list<string>  $connectionKeys
     * @return array<string, int>
     */
    public function countActiveBatchesGroupedByConnection(array $connectionKeys, string $type): array
    {
        return $this->batches->countActiveGroupedByConnection($connectionKeys, $type);
    }

    public function countFailedItemsSince(\DateTimeInterface $since): int
    {
        return $this->items->countFailedSince($since);
    }

    /**
     * @param  list<string>  $connectionKeys
     * @return array<string, int>
     */
    public function countFailedItemsGroupedByConnectionSince(array $connectionKeys, \DateTimeInterface $since): array
    {
        return $this->items->countFailedGroupedByConnectionSince($connectionKeys, $since);
    }
}
