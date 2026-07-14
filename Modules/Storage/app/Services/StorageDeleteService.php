<?php

declare(strict_types=1);

namespace Modules\Storage\Services;

use InvalidArgumentException;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Models\StorageAccount;

final class StorageDeleteService
{
    public function __construct(
        private readonly StorageDriverRegistry $drivers,
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

        $result = $this->drivers->deleteDriver($driver)->deleteMany($account, $itemIds, $containerId);

        if ($result['deleted'] === []) {
            return $result;
        }

        if ($driver === StorageDriver::GooglePhotos) {
            $this->browseLocal->deleteCachedItems($account, $result['deleted'], $containerId);
        } else {
            $this->browseLocal->deleteCachedItems($account, $result['deleted']);
            $this->browseLocal->purgeUploadRecords($account, $result['deleted']);
        }

        return $result;
    }
}
