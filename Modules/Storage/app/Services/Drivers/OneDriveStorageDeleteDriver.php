<?php

declare(strict_types=1);

namespace Modules\Storage\Services\Drivers;

use Modules\Storage\Contracts\StorageDeleteDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\OneDriveDeleteService;
use Modules\Storage\Services\StorageBrowseLocalService;

final class OneDriveStorageDeleteDriver implements StorageDeleteDriver
{
    public function __construct(
        private readonly OneDriveDeleteService $delete,
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
