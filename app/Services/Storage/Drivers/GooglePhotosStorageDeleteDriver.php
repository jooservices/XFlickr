<?php

declare(strict_types=1);

namespace App\Services\Storage\Drivers;

use App\Contracts\Storage\StorageDeleteDriver;
use App\Models\StorageAccount;
use App\Services\Storage\GooglePhotosDeleteService;
use App\Services\Storage\StorageBrowseLocalService;

final class GooglePhotosStorageDeleteDriver implements StorageDeleteDriver
{
    public function __construct(
        private readonly GooglePhotosDeleteService $delete,
        private readonly StorageBrowseLocalService $browseLocal,
    ) {}

    public function deleteMany(StorageAccount $account, array $itemIds, ?string $containerId = null): array
    {
        $result = $this->delete->deleteMany($account->credentials ?? [], $itemIds, $containerId);

        if ($result['deleted'] !== []) {
            $this->browseLocal->deleteCachedItems($account, $result['deleted'], $containerId);
        }

        return $result;
    }
}
