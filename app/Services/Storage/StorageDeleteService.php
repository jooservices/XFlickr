<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Enums\StorageDriver;
use App\Models\StorageAccount;
use InvalidArgumentException;

final class StorageDeleteService
{
    public function __construct(
        private readonly StorageDriverRegistry $drivers,
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

        return $this->drivers->deleteDriver($driver)->deleteMany($account, $itemIds, $containerId);
    }
}
