<?php

declare(strict_types=1);

namespace Modules\Storage\Repositories;

use Jooservices\LaravelRepository\Repositories\EloquentRepository;
use Jooservices\LaravelRepository\Traits\HasCrud;
use Jooservices\LaravelRepository\Traits\HasFilter;
use Modules\Storage\Enums\StorageUploadStatus;
use Modules\Storage\Models\StorageUpload;

/**
 * @extends EloquentRepository<StorageUpload>
 */
final class StorageUploadRepository extends EloquentRepository
{
    use HasCrud;
    use HasFilter;

    public function __construct(StorageUpload $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  list<int>  $storedFileIds
     * @return list<int>
     */
    public function completedStoredFileIdsForAccount(array $storedFileIds, int $storageAccountId): array
    {
        if ($storedFileIds === []) {
            return [];
        }

        return $this->newQuery()
            ->where('storage_account_id', $storageAccountId)
            ->whereIn('stored_file_id', $storedFileIds)
            ->where('status', StorageUploadStatus::Completed->value)
            ->pluck('stored_file_id')
            ->all();
    }

    public function hasCompleted(int $storedFileId, int $storageAccountId): bool
    {
        return $this->newQuery()
            ->where('stored_file_id', $storedFileId)
            ->where('storage_account_id', $storageAccountId)
            ->where('status', StorageUploadStatus::Completed->value)
            ->exists();
    }

    public function firstOrCreateForAccount(int $storedFileId, int $storageAccountId): StorageUpload
    {
        return $this->newQuery()->firstOrCreate(
            [
                'stored_file_id' => $storedFileId,
                'storage_account_id' => $storageAccountId,
            ],
            [
                'status' => StorageUploadStatus::Pending->value,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateForAccount(int $storedFileId, int $storageAccountId, array $attributes): void
    {
        $this->newQuery()
            ->where('stored_file_id', $storedFileId)
            ->where('storage_account_id', $storageAccountId)
            ->update($attributes);
    }

    public function markFailedForAccount(int $storedFileId, int $storageAccountId, string $errorMessage): void
    {
        $this->updateForAccount($storedFileId, $storageAccountId, [
            'status' => StorageUploadStatus::Failed->value,
            'error_message' => $errorMessage,
        ]);
    }

    public function markUploading(int $storedFileId, int $storageAccountId): void
    {
        $this->updateForAccount($storedFileId, $storageAccountId, [
            'status' => StorageUploadStatus::Uploading->value,
        ]);
    }

    /**
     * @param  array{id: string, path: string, etag: string|null}  $remoteMetadata
     */
    public function markCompletedForAccount(int $storedFileId, int $storageAccountId, array $remoteMetadata): void
    {
        $this->updateForAccount($storedFileId, $storageAccountId, [
            'status' => StorageUploadStatus::Completed->value,
            'remote_file_id' => $remoteMetadata['id'],
            'remote_path' => $remoteMetadata['path'],
            'remote_etag' => $remoteMetadata['etag'],
            'uploaded_at' => now(),
            'error_message' => null,
        ]);
    }

    public function markPendingForAccount(int $storedFileId, int $storageAccountId, string $errorMessage): void
    {
        $this->updateForAccount($storedFileId, $storageAccountId, [
            'status' => StorageUploadStatus::Pending->value,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * @param  list<string>  $remoteIds
     */
    public function deleteByRemoteReferences(int $storageAccountId, array $remoteIds): void
    {
        if ($remoteIds === []) {
            return;
        }

        $this->newQuery()
            ->where('storage_account_id', $storageAccountId)
            ->where(function ($query) use ($remoteIds): void {
                $query->whereIn('remote_file_id', $remoteIds)
                    ->orWhereIn('remote_path', $remoteIds);
            })
            ->delete();
    }
}
