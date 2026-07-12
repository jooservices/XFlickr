<?php

declare(strict_types=1);

namespace Modules\Storage\Contracts;

use Modules\Storage\Models\StorageAccount;

interface StorageDeleteDriver
{
    /**
     * @param  list<string>  $itemIds
     * @return array{deleted: list<string>, failed: list<array{id: string, message: string}>}
     */
    public function deleteMany(StorageAccount $account, array $itemIds, ?string $containerId = null): array;
}
