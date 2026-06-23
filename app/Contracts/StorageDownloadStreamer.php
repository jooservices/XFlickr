<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\StorageAccount;
use App\Support\Storage\StorageStreamResult;

interface StorageDownloadStreamer
{
    public function openStreamForAccount(StorageAccount $account, string $remotePath): ?StorageStreamResult;
}
