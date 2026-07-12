<?php

declare(strict_types=1);

namespace Modules\Transfer\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Jooservices\LaravelRepository\Repositories\EloquentRepository;
use Jooservices\LaravelRepository\Traits\HasCrud;
use Jooservices\LaravelRepository\Traits\HasFilter;
use Modules\Transfer\Enums\StoredFileStatus;
use Modules\Transfer\Models\StoredFile;

final class StoredFileRepository extends EloquentRepository
{
    use HasCrud;
    use HasFilter;

    public function __construct(StoredFile $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  list<string>  $flickrPhotoIds
     * @return list<string>
     */
    public function completedOriginalFlickrPhotoIds(array $flickrPhotoIds): array
    {
        if ($flickrPhotoIds === []) {
            return [];
        }

        return $this->newQuery()
            ->whereIn('flickr_photo_id', $flickrPhotoIds)
            ->where('variant', 'original')
            ->where('status', StoredFileStatus::Completed->value)
            ->pluck('flickr_photo_id')
            ->all();
    }

    /**
     * @param  list<string>  $flickrPhotoIds
     * @return Collection<string, StoredFile>
     */
    public function originalsByFlickrPhotoIds(array $flickrPhotoIds): Collection
    {
        if ($flickrPhotoIds === []) {
            return collect();
        }

        /** @var Collection<string, StoredFile> */
        return $this->newQuery()
            ->whereIn('flickr_photo_id', $flickrPhotoIds)
            ->where('variant', 'original')
            ->get()
            ->keyBy('flickr_photo_id');
    }

    public function hasCompletedOriginal(string $flickrPhotoId): bool
    {
        return $this->newQuery()
            ->where('flickr_photo_id', $flickrPhotoId)
            ->where('variant', 'original')
            ->where('status', StoredFileStatus::Completed->value)
            ->exists();
    }

    public function findOriginalByFlickrPhotoId(string $flickrPhotoId): ?StoredFile
    {
        $stored = $this->newQuery()
            ->where('flickr_photo_id', $flickrPhotoId)
            ->where('variant', 'original')
            ->first();

        return $stored instanceof StoredFile ? $stored : null;
    }

    public function findByUuid(string $uuid): ?StoredFile
    {
        $stored = $this->newQuery()->where('uuid', $uuid)->first();

        return $stored instanceof StoredFile ? $stored : null;
    }

    public function firstOrCreateOriginal(string $flickrPhotoId, string $ownerNsid): StoredFile
    {
        /** @var StoredFile */
        return $this->newQuery()->firstOrCreate(
            [
                'flickr_photo_id' => $flickrPhotoId,
                'variant' => 'original',
            ],
            [
                'owner_nsid' => $ownerNsid,
                'status' => StoredFileStatus::Pending->value,
                'original_name' => "{$flickrPhotoId}_original",
            ],
        );
    }

    public function markDownloading(string $flickrPhotoId): void
    {
        $this->findOriginalByFlickrPhotoId($flickrPhotoId)?->update(['status' => StoredFileStatus::Downloading->value]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function markCompleted(string $flickrPhotoId, array $attributes): void
    {
        $this->newQuery()
            ->where('flickr_photo_id', $flickrPhotoId)
            ->where('variant', 'original')
            ->update(array_merge(['status' => StoredFileStatus::Completed->value], $attributes));
    }

    public function markPending(string $flickrPhotoId, ?string $errorMessage = null): void
    {
        $this->newQuery()
            ->where('flickr_photo_id', $flickrPhotoId)
            ->where('variant', 'original')
            ->update([
                'status' => StoredFileStatus::Pending->value,
                'error_message' => $errorMessage,
            ]);
    }

    public function markFailed(string $flickrPhotoId, string $errorMessage): void
    {
        $this->newQuery()
            ->where('flickr_photo_id', $flickrPhotoId)
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
     * @param  list<string>  $ownerNsids
     * @return Collection<int, StoredFile>
     */
    public function originalsForOwners(array $ownerNsids): Collection
    {
        if ($ownerNsids === []) {
            return collect();
        }

        return $this->newQuery()
            ->whereIn('owner_nsid', $ownerNsids)
            ->where('variant', 'original')
            ->get(['owner_nsid', 'status', 'local_path']);
    }

    /**
     * @return Builder<StoredFile>
     */
    public function completedOriginalCountSubquery(): Builder
    {
        return $this->newQuery()
            ->where('variant', 'original')
            ->where('status', StoredFileStatus::Completed->value)
            ->selectRaw('owner_nsid as contact_nsid, count(*) as aggregate')
            ->groupBy('owner_nsid');
    }
}
