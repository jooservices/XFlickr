<?php

declare(strict_types=1);

namespace Modules\Storage\Events;

final readonly class StorageRemoteItemsRemoved
{
    /** @param list<string> $remoteIds */
    public function __construct(
        public int $storageAccountId,
        public array $remoteIds,
    ) {}
}
