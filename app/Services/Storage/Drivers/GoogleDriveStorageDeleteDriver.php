<?php

declare(strict_types=1);

namespace App\Services\Storage\Drivers;

use App\Contracts\Storage\StorageDeleteDriver;
use App\Models\StorageAccount;
use App\Services\Storage\GoogleDriveDeleteService;
use App\Services\Storage\StorageBrowseLocalService;

final class GoogleDriveStorageDeleteDriver implements StorageDeleteDriver
{
    public function __construct(
        private readonly GoogleDriveDeleteService $delete,
        private readonly StorageBrowseLocalService $browseLocal,
    ) {}

    public function deleteMany(StorageAccount $account, array $itemIds, ?string $containerId = null): array
    {
        $result = $this->delete->deleteMany($account, $account->credentials ?? [], $itemIds);

        if ($result['deleted'] !== []) {
            $this->browseLocal->deleteCachedItems($account, $result['deleted']);
            $this->browseLocal->purgeUploadRecords($account, $result['deleted']);
        }

        return $result;
    }
}
