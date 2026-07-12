<?php

declare(strict_types=1);

namespace Modules\Storage\Contracts;

use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Support\StorageStreamResult;

interface StorageDownloadStreamer
{
    public function openStreamForAccount(StorageAccount $account, string $remotePath): ?StorageStreamResult;
}
