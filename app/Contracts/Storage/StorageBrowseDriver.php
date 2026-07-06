<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

use App\Models\StorageAccount;
use App\Services\Storage\StorageBrowseResult;

interface StorageBrowseDriver
{
    public function browse(
        StorageAccount $account,
        int $perPage,
        ?string $albumPageToken,
        ?string $itemPageToken,
        ?string $containerId,
        bool $includeAlbums,
        bool $includeItems,
    ): StorageBrowseResult;
}
