<?php

declare(strict_types=1);

namespace Modules\Transfer\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Jooservices\LaravelRepository\Repositories\EloquentRepository;
use Jooservices\LaravelRepository\Traits\HasCrud;
use Jooservices\LaravelRepository\Traits\HasFilter;
use Modules\Transfer\Enums\StoredFileStatus;
use Modules\Transfer\Models\StoredFile;

/**
 * @extends EloquentRepository<StoredFile>
 */
final class StoredFileRepository extends EloquentRepository
{
    use HasCrud;
    use HasFilter;

    public function __construct(StoredFile $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  list<string>  $sourceIds
     * @return list<string>
     */
    public function completedOriginalSourceIds(array $sourceIds): array
    {
        if ($sourceIds === []) {
            return [];
        }

        return $this->newQuery()
            ->whereIn('source_id', $sourceIds)
            ->where('variant', 'original')
            ->completed()
            ->pluck('source_id')
            ->all();
    }

    /**
     * @param  list<string>  $sourceIds
     * @return Collection<string, StoredFile>
     */
    public function originalsBySourceIds(array $sourceIds): Collection
    {
        if ($sourceIds === []) {
            return collect();
        }

        /** @var Collection<string, StoredFile> */
        return $this->newQuery()
            ->whereIn('source_id', $sourceIds)
            ->where('variant', 'original')
            ->get()
            ->keyBy('source_id');
    }

    public function hasCompletedOriginal(string $sourceId): bool
    {
        return $this->newQuery()
            ->where('source_id', $sourceId)
            ->where('variant', 'original')
            ->completed()
            ->exists();
    }

    public function findOriginalBySourceId(string $sourceType, string $sourceId): ?StoredFile
    {
        $stored = $this->newQuery()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('variant', 'original')
            ->first();

        return $stored instanceof StoredFile ? $stored : null;
    }

    public function findBySourceId(string $sourceType, string $sourceId): ?StoredFile
    {
        $stored = $this->newQuery()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('variant', 'original')
            ->first();

        return $stored instanceof StoredFile ? $stored : null;
    }

    public function findById(int $id): ?StoredFile
    {
        $stored = $this->newQuery()->find($id);

        return $stored instanceof StoredFile ? $stored : null;
    }

    public function findByUuid(string $uuid): ?StoredFile
    {
        $stored = $this->newQuery()->where('uuid', $uuid)->first();

        return $stored instanceof StoredFile ? $stored : null;
    }

    public function firstOrCreateOriginal(string $sourceType, string $sourceId, string $sourceOwner): StoredFile
    {
        /** @var StoredFile */
        return $this->newQuery()->firstOrCreate(
            [
                'source_id' => $sourceId,
                'variant' => 'original',
            ],
            [
                'source_type' => $sourceType,
                'source_owner' => $sourceOwner,
                'status' => StoredFileStatus::Pending->value,
                'original_name' => "{$sourceId}_original",
            ],
        );
    }

    /**
     * Ensure original StoredFile rows exist as pending for queued downloads.
     * Leaves completed and downloading rows unchanged.
     *
     * @param  list<array{source_type: string, source_id: string, source_owner: string}>  $rows
     */
    public function ensurePendingOriginals(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $bySourceId = [];
        foreach ($rows as $row) {
            $sourceId = $row['source_id'];
            if ($sourceId === '') {
                continue;
            }
            $bySourceId[$sourceId] = $row;
        }

        if ($bySourceId === []) {
            return;
        }

        $existing = $this->originalsBySourceIds(array_keys($bySourceId));
        $toInsert = [];
        $toRepend = [];
        $now = now();

        foreach ($bySourceId as $sourceId => $row) {
            $stored = $existing->get($sourceId);
            $sourceType = $row['source_type'];

            if ($stored === null) {
                $toInsert[] = [
                    'uuid' => (string) Str::uuid(),
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'source_owner' => $row['source_owner'],
                    'variant' => 'original',
                    'status' => StoredFileStatus::Pending->value,
                    'original_name' => "{$sourceId}_original",
                    'dedup_key' => "{$sourceType}:{$sourceId}:original",
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                continue;
            }

            if (in_array($stored->status, [
                StoredFileStatus::Completed->value,
                StoredFileStatus::Downloading->value,
                StoredFileStatus::Pending->value,
            ], true)) {
                continue;
            }

            $toRepend[] = $sourceId;
        }

        foreach (array_chunk($toInsert, 500) as $chunk) {
            $this->newQuery()->insertOrIgnore($chunk);
        }

        if ($toRepend !== []) {
            $this->newQuery()
                ->whereIn('source_id', $toRepend)
                ->where('variant', 'original')
                ->whereNotIn('status', [
                    StoredFileStatus::Completed->value,
                    StoredFileStatus::Downloading->value,
                ])
                ->update([
                    'status' => StoredFileStatus::Pending->value,
                    'error_message' => null,
                    'updated_at' => $now,
                ]);
        }
    }

    public function markDownloading(string $sourceId): void
    {
        $this->newQuery()
            ->where('source_id', $sourceId)
            ->where('variant', 'original')
            ->update(['status' => StoredFileStatus::Downloading->value]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function markCompleted(string $sourceId, array $attributes): void
    {
        $this->newQuery()
            ->where('source_id', $sourceId)
            ->where('variant', 'original')
            ->update(array_merge(['status' => StoredFileStatus::Completed->value], $attributes));
    }

    public function markPending(string $sourceId, ?string $errorMessage = null): void
    {
        $this->newQuery()
            ->where('source_id', $sourceId)
            ->where('variant', 'original')
            ->update([
                'status' => StoredFileStatus::Pending->value,
                'error_message' => $errorMessage,
            ]);
    }

    public function clearLocalPath(StoredFile $storedFile): void
    {
        $storedFile->update(['local_path' => null]);
    }

    public function markFailed(string $sourceId, string $errorMessage): void
    {
        $this->newQuery()
            ->where('source_id', $sourceId)
            ->where('variant', 'original')
            ->update([
                'status' => StoredFileStatus::Failed->value,
                'error_message' => $errorMessage,
            ]);
    }

    public function countAll(): int
    {
        return $this->newQuery()->count();
    }

    /**
     * @param  list<string>  $sourceOwners
     * @return Collection<int, StoredFile>
     */
    public function originalsForOwners(array $sourceOwners): Collection
    {
        if ($sourceOwners === []) {
            return collect();
        }

        return $this->newQuery()
            ->whereIn('source_owner', $sourceOwners)
            ->where('variant', 'original')
            ->get(['source_owner', 'status', 'local_path']);
    }

    /**
     * @return Builder<StoredFile>
     */
    public function completedOriginalCountSubquery(): Builder
    {
        return $this->newQuery()
            ->where('variant', 'original')
            ->completed()
            ->selectRaw('source_owner as contact_nsid, count(*) as aggregate')
            ->groupBy('source_owner');
    }

    /**
     * @return Collection<int, StoredFile>
     */
    public function completedOriginals(): Collection
    {
        return $this->newQuery()
            ->where('variant', 'original')
            ->completed()
            ->get();
    }

    public function markStatusAndPath(
        int $id,
        string $status,
        ?string $path = null,
        ?int $bytes = null,
        ?string $sha256 = null,
        ?string $error = null,
    ): void {
        $this->newQuery()->whereKey($id)->update([
            'status' => $status,
            'local_path' => $path,
            'bytes' => $bytes,
            'content_sha256' => $sha256,
            'error_message' => $error,
            'updated_at' => now(),
        ]);
    }

    public function createStoredFile(array $attributes): StoredFile
    {
        return $this->newQuery()->create($attributes);
    }
}
