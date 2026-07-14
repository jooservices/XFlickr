<?php

declare(strict_types=1);

namespace Modules\Storage\Contracts;

use Modules\Storage\Dto\StorageStreamResult;
use Modules\Storage\Models\StorageAccount;

interface StorageDownloadStreamer
{
    public function openStreamForAccount(StorageAccount $account, string $remotePath): ?StorageStreamResult;
}
