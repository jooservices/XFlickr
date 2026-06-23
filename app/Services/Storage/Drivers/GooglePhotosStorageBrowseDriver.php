<?php

declare(strict_types=1);

namespace App\Services\Storage\Drivers;

use App\Contracts\Storage\StorageBrowseDriver;
use App\Models\StorageAccount;
use App\Services\Storage\GooglePhotosBrowseService;
use App\Services\Storage\StorageBrowseResult;

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
