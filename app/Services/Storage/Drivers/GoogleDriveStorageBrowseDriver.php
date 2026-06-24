<?php

declare(strict_types=1);

namespace App\Services\Storage\Drivers;

use App\Contracts\Storage\StorageBrowseDriver;
use App\Models\StorageAccount;
use App\Services\Storage\GoogleDriveBrowseService;
use App\Services\Storage\StorageBrowseResult;

final class GoogleDriveStorageBrowseDriver implements StorageBrowseDriver
{
    public function __construct(
        private readonly GoogleDriveBrowseService $browse,
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
            $includeAlbums,
            $includeItems,
        );
    }
}
