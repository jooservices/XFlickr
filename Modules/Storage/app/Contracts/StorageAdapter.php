<?php

declare(strict_types=1);

namespace Modules\Storage\Contracts;

use Modules\Storage\Dto\StorageBrowseResult;
use Modules\Storage\Dto\StorageDeleteOptions;
use Modules\Storage\Dto\StorageListOptions;
use Modules\Storage\Dto\StorageUploadRequest;
use Modules\Storage\Dto\StorageUploadResult;
use Modules\Storage\Exceptions\StorageOperationException;

interface StorageAdapter
{
    /**
     * @throws StorageOperationException on provider/IO failure
     */
    public function upload(StorageUploadRequest $request): StorageUploadResult;

    /**
     * @param  list<string>  $itemIds
     * @return array{deleted: list<string>, failed: list<array{id: string, message: string}>}
     */
    public function delete(array $itemIds, ?StorageDeleteOptions $options = null): array;

    public function list(?StorageListOptions $options = null): StorageBrowseResult;
}
