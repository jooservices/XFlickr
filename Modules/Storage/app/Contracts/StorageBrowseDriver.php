<?php

declare(strict_types=1);

namespace Modules\Storage\Contracts;

use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\StorageBrowseResult;

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
