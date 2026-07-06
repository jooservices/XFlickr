<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Repositories\Crawler\ConnectionQueryRepository;
use App\Repositories\StorageAccountRepository;
use App\Services\Flickr\PhotoDownloadService;
use App\Services\Flickr\PhotoUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class FanOutTransferBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly string $transferType,
        private readonly string $connectionKey,
        private readonly ?string $ownerNsid = null,
        private readonly ?int $storageAccountId = null,
    ) {
        $this->onQueue($transferType === 'upload' ? 'xflickr-uploads' : 'xflickr-downloads');
    }

    public function handle(
        PhotoDownloadService $downloads,
        PhotoUploadService $uploads,
        ConnectionQueryRepository $connections,
        StorageAccountRepository $storageAccounts,
    ): void {
        $connection = $connections->findByConnectionKey($this->connectionKey);

        if ($connection === null) {
            return;
        }

        $ownerNsid = $this->ownerNsid ?? $connection->connection_key;

        if ($this->transferType === 'download') {
            $downloads->fanOutDownloads($connection, $ownerNsid);

            return;
        }

        if ($this->storageAccountId === null) {
            return;
        }

        $storageAccount = $storageAccounts->findById($this->storageAccountId);

        if ($storageAccount === null) {
            return;
        }

        $uploads->fanOutUploads(
            $connection,
            $storageAccount,
            $ownerNsid !== $connection->connection_key ? $ownerNsid : null,
        );
    }
}
