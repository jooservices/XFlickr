<?php

declare(strict_types=1);

namespace Modules\Transfer\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Transfer\Models\StoredFile;
use Modules\Transfer\Repositories\StoredFileRepository;

final class StoredFileService
{
    public function __construct(
        private readonly StoredFileRepository $storedFiles,
    ) {}

    /**
     * @param  list<string>  $sourceIds
     * @return Collection<string, StoredFile>
     */
    public function originalsBySourceIds(array $sourceIds): Collection
    {
        return $this->storedFiles->originalsBySourceIds($sourceIds);
    }

    /**
     * @param  list<string>  $sourceOwners
     * @return Collection<int, StoredFile>
     */
    public function originalsForOwners(array $sourceOwners): Collection
    {
        return $this->storedFiles->originalsForOwners($sourceOwners);
    }

    /** @return Builder<StoredFile> */
    public function completedOriginalCountSubquery(): Builder
    {
        return $this->storedFiles->completedOriginalCountSubquery();
    }
}
