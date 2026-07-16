<?php

declare(strict_types=1);

namespace Modules\Transfer\Listeners;

use Modules\Storage\Events\StorageRemoteItemsRemoved;
use Modules\Transfer\Repositories\StorageUploadRepository;

final class DeleteStorageUploadRecords
{
    public function __construct(
        private readonly StorageUploadRepository $uploads,
    ) {}

    public function handle(StorageRemoteItemsRemoved $event): void
    {
        $this->uploads->deleteByRemoteReferences($event->storageAccountId, $event->remoteIds);
    }
}
