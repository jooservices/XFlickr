<?php

declare(strict_types=1);

namespace Modules\Storage\Services\Drivers;

use Modules\Storage\Contracts\StorageBrowseDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\GooglePhotosBrowseService;
use Modules\Storage\Services\StorageBrowseResult;

final class GooglePhotosStorageBrowseDriver implements StorageBrowseDriver
{
    public function __construct(
        private readonly GooglePhotosBrowseService $browse,
    ) {}

    public function browse(
        StorageAccount $account,
        int $perPage,
        ?string $albumPageToken,
        ?string $itemPageToken,
        ?string $containerId,
        bool $includeAlbums,
        bool $includeItems,
    ): StorageBrowseResult {
        return $this->browse->browse(
            $account->credentials ?? [],
            $account,
            $perPage,
            $albumPageToken,
            $itemPageToken,
            $containerId,
            $account->id,
            $includeAlbums,
            $includeItems,
        );
    }
}
