<?php

declare(strict_types=1);

namespace Modules\Transfer\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Transfer\Dto\TransferQueueResult;
use Modules\Transfer\Enums\TransferItemStatus;
use Modules\Transfer\Enums\TransferType;
use Modules\Transfer\Jobs\DownloadFileJob;
use Modules\Transfer\Jobs\UploadFileJob;
use Modules\Transfer\Models\StoredFile;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Repositories\StoredFileRepository;
use Modules\Transfer\Repositories\TransferBatchRepository;
use Modules\Transfer\Repositories\TransferItemRepository;

final class TransferBatchService
{
    /** @var list<string> */
    private const BATCH_SORTS = ['id', 'type', 'subject_nsid', 'status', 'total_count', 'completed_count', 'failed_count', 'created_at'];

    public function __construct(
        private readonly StoredFileRepository $storedFiles,
        private readonly TransferBatchRepository $batches,
        private readonly TransferItemRepository $items,
        private readonly TransferBatchReconciler $batchReconciler,
    ) {}

    // ── Batch queue ─────────────────────────────────────────

    /**
     * @param  list<array{source_type: string, source_id: string, source_owner: string}>  $downloadItems
     */
    public function queueDownloads(
        array $downloadItems,
        string $connectionKey,
        ?string $subjectNsid = null,
        ?string $groupType = null,
        ?string $groupId = null,
        ?string $groupLabel = null,
    ): TransferQueueResult {
        if ($downloadItems === []) {
            return TransferQueueResult::error('No items to download.');
        }

        $this->storedFiles->ensurePendingOriginals($downloadItems);

        $sourceIds = array_column($downloadItems, 'source_id');
        $batch = $this->batches->createDownloadBatchWithItems(
            $connectionKey,
            $subjectNsid ?? $connectionKey,
            [
                'group_type' => $groupType ?? 'bulk',
                'group_id' => $groupId,
                'group_label' => $groupLabel ?? 'Bulk download',
            ],
            $sourceIds,
        );

        foreach ($downloadItems as $item) {
            DownloadFileJob::dispatch(
                $item['source_type'],
                $item['source_id'],
                $item['source_owner'],
                $connectionKey,
                $batch->id,
            );
        }

        return TransferQueueResult::success(
            "Queued {$batch->total_count} file(s) for download.",
            (int) $batch->total_count,
        );
    }

    /**
     * @param  list<int>  $storedFileIds
     */
    public function queueUploads(
        array $storedFileIds,
        int $storageAccountId,
        string $connectionKey,
        ?string $subjectNsid = null,
        ?bool $deleteLocalAfterUpload = null,
    ): TransferQueueResult {
        if ($storedFileIds === []) {
            return TransferQueueResult::error('No files to upload.');
        }

        $storedFiles = [];
        $validStoredFileIds = [];
        foreach ($storedFileIds as $id) {
            $sf = $this->storedFiles->findById($id);
            if ($sf !== null) {
                $storedFiles[] = $sf;
                $validStoredFileIds[] = $sf->id;
            }
        }

        if ($validStoredFileIds === []) {
            return TransferQueueResult::error('No files to upload.');
        }

        $sourceIds = array_map(fn (StoredFile $sf): string => (string) $sf->source_id, $storedFiles);
        $batch = $this->batches->createUploadBatchWithItems(
            $connectionKey,
            $storageAccountId,
            $sourceIds,
            $subjectNsid,
            $deleteLocalAfterUpload,
        );

        foreach ($validStoredFileIds as $storedFileId) {
            UploadFileJob::dispatch(
                $storedFileId,
                $storageAccountId,
                $batch->id,
                count($validStoredFileIds),
            );
        }

        return TransferQueueResult::success(
            "Queued {$batch->total_count} file(s) for upload.",
            (int) $batch->total_count,
        );
    }

    // ── Query ───────────────────────────────────────────────

    public function findStoredFile(string $sourceType, string $sourceId): ?StoredFile
    {
        return $this->storedFiles->findOriginalBySourceId($sourceType, $sourceId);
    }

    /**
     * @param  list<string>  $sourceIds
     * @return list<string>
     */
    public function completedSourceIds(string $sourceType, array $sourceIds): array
    {
        return $this->storedFiles->completedOriginalSourceIds($sourceIds);
    }

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

    /**
     * @param  list<string>  $subjectNsids
     * @return Collection<int, TransferBatch>
     */
    public function runningDownloadsForSubjects(string $connectionKey, array $subjectNsids): Collection
    {
        return $this->batches->runningDownloadsForSubjects($connectionKey, $subjectNsids);
    }

    /**
     * @return array{batch: array<string, mixed>, items: mixed}|null
     */
    public function batchDetail(string $connectionKey, TransferBatch $batch): ?array
    {
        $batch = $this->batches->findWithItemsForConnection($batch->id, $connectionKey);
        if ($batch === null) {
            return null;
        }

        return [
            'batch' => [
                'id' => $batch->id,
                'type' => $batch->type,
                'status' => $batch->status,
                'total_count' => $batch->total_count,
                'completed_count' => $batch->completed_count,
                'failed_count' => $batch->failed_count,
                'connection_key' => $batch->connection_key,
                'subject_nsid' => $batch->subject_nsid,
                'group_type' => $batch->group_type,
                'group_id' => $batch->group_id,
                'group_label' => $batch->group_label,
                'storage_account_id' => $batch->storage_account_id,
            ],
            'items' => $batch->items,
        ];
    }

    /**
     * @return array{data: mixed}
     */
    public function batchesForConnection(
        string $connectionKey,
        ?string $status,
        ?string $type,
        bool $active,
        string $sort,
        string $direction,
        int $limit,
    ): array {
        $batchList = $this->batches->listForConnection(
            $connectionKey,
            $status,
            $type,
            $active,
            $sort,
            $direction,
            $limit,
            self::BATCH_SORTS,
        );

        return [
            'data' => $batchList->map(fn (TransferBatch $batch): array => [
                ...$batch->toArray(),
                'sample_error' => $batch->failed_count > 0
                    ? $this->batchReconciler->sampleError($batch->id)
                    : null,
            ]),
        ];
    }

    // ── Retry ───────────────────────────────────────────────

    public function retryItem(string $connectionKey, TransferBatch $batch, string $sourceId): void
    {
        if ($batch->connection_key !== $connectionKey) {
            abort(404);
        }

        $item = $this->items->findForBatch($batch->id, $sourceId);

        if ($item === null || $item->status !== TransferItemStatus::Failed->value) {
            throw ValidationException::withMessages([
                'source_id' => 'Only failed transfer items can be retried.',
            ]);
        }

        $this->items->updateStatus($batch->id, $sourceId, TransferItemStatus::Pending);
        $this->batchReconciler->reconcile($batch->id);

        $sourceOwner = $batch->subject_nsid !== null ? (string) $batch->subject_nsid : $connectionKey;
        $sourceType = 'flickr_photo';

        $storedFile = $this->storedFiles->findOriginalBySourceId($sourceType, $sourceId);
        if ($storedFile !== null) {
            $fileOwner = $storedFile->source_owner;
            if (is_string($fileOwner) && $fileOwner !== '') {
                $sourceOwner = $fileOwner;
            }

            $fileSourceType = $storedFile->source_type;
            if (is_string($fileSourceType) && $fileSourceType !== '') {
                $sourceType = $fileSourceType;
            }
        }

        $type = TransferType::tryFrom((string) $batch->type);

        if ($type === TransferType::Download) {
            DownloadFileJob::dispatch(
                $sourceType,
                $sourceId,
                $sourceOwner,
                $connectionKey,
                $batch->id,
            );

            return;
        }

        if ($batch->storage_account_id === null) {
            throw ValidationException::withMessages([
                'batch' => 'Upload batch is missing a storage account.',
            ]);
        }

        if ($storedFile === null) {
            throw ValidationException::withMessages([
                'source_id' => 'Stored file not found for retry.',
            ]);
        }

        UploadFileJob::dispatch(
            $storedFile->id,
            (int) $batch->storage_account_id,
            $batch->id,
            (int) $batch->total_count,
        );
    }
}
