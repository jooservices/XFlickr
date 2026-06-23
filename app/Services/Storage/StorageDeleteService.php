<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Enums\StorageDriver;
use App\Models\StorageAccount;
use InvalidArgumentException;

final class StorageDeleteService
{
    public function __construct(
        private readonly GooglePhotosDeleteService $googlePhotos,
        private readonly GoogleDriveDeleteService $googleDrive,
        private readonly OneDriveDeleteService $oneDrive,
        private readonly R2DeleteService $r2,
        private readonly StorageBrowseLocalService $browseLocal,
    ) {}

    /**
     * @param  list<string>  $itemIds
     * @return array{deleted: list<string>, failed: list<array{id: string, message: string}>}
     */
    public function deleteMany(
        StorageAccount $account,
        StorageDriver $driver,
        array $itemIds,
        ?string $containerId = null,
    ): array {
        $itemIds = array_values(array_filter($itemIds, static fn (string $id): bool => $id !== ''));

        if ($itemIds === []) {
            throw new InvalidArgumentException('At least one item id is required.');
        }

        $credentials = $account->credentials ?? [];

        $result = match ($driver) {
            StorageDriver::GooglePhotos => $this->googlePhotos->deleteMany($credentials, $itemIds, $containerId),
            StorageDriver::GoogleDrive => $this->googleDrive->deleteMany($credentials, $itemIds),
            StorageDriver::OneDrive => $this->oneDrive->deleteMany($credentials, $itemIds),
            StorageDriver::R2 => $this->r2->deleteMany($account, $itemIds),
        };

        if ($result['deleted'] !== []) {
            if ($driver === StorageDriver::GooglePhotos) {
                $this->browseLocal->deleteCachedItems($account, $result['deleted'], $containerId);
            } else {
                $this->browseLocal->deleteCachedItems($account, $result['deleted']);
                $this->browseLocal->purgeUploadRecords($account, $result['deleted']);
            }
        }

        return $result;
    }
}
