<?php

declare(strict_types=1);

namespace Modules\Storage\Services\Drivers;

use Modules\Storage\Contracts\StorageBrowseDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\R2BrowseService;
use Modules\Storage\Services\StorageBrowseResult;

final class R2StorageBrowseDriver implements StorageBrowseDriver
{
    public function __construct(
        private readonly R2BrowseService $browse,
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
            $includeAlbums,
            $includeItems,
        );
    }
}
