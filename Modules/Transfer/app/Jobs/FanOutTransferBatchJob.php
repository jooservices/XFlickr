<?php

declare(strict_types=1);

namespace Modules\Transfer\Jobs;

use App\Repositories\Crawler\ConnectionQueryRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Storage\Repositories\StorageAccountRepository;
use Modules\Transfer\Enums\TransferType;
use Modules\Transfer\Services\PhotoDownloadService;
use Modules\Transfer\Services\PhotoUploadService;

final class FanOutTransferBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly TransferType $transferType,
        private readonly string $connectionKey,
        private readonly ?string $ownerNsid = null,
        private readonly ?int $storageAccountId = null,
    ) {
        $this->onQueue(
            $this->transferType === TransferType::Upload ? 'xflickr-uploads' : 'xflickr-downloads',
        );
    }

    public function handle(
        PhotoDownloadService $downloads,
        PhotoUploadService $uploads,
        ConnectionQueryRepository $connections,
        StorageAccountRepository $storageAccounts,
    ): void {
        $connection = $connections->findByConnectionKey($this->connectionKey);

        if ($connection === null) {
            Log::warning('Fan-out skipped: Flickr connection missing.', [
                'transfer_type' => $this->transferType->value,
                'connection_key' => $this->connectionKey,
                'owner_nsid' => $this->ownerNsid,
            ]);

            return;
        }

        $ownerNsid = $this->ownerNsid ?? $connection->connection_key;

        if ($this->transferType === TransferType::Download) {
            $downloads->fanOutDownloads($connection, $ownerNsid);

            return;
        }

        if ($this->storageAccountId === null) {
            Log::warning('Fan-out skipped: storage account id missing for upload.', [
                'transfer_type' => $this->transferType->value,
                'connection_key' => $this->connectionKey,
                'owner_nsid' => $ownerNsid,
            ]);

            return;
        }

        $storageAccount = $storageAccounts->findById($this->storageAccountId);

        if ($storageAccount === null) {
            Log::warning('Fan-out skipped: storage account not found.', [
                'transfer_type' => $this->transferType->value,
                'connection_key' => $this->connectionKey,
                'storage_account_id' => $this->storageAccountId,
            ]);

            return;
        }

        $uploads->fanOutUploads(
            $connection,
            $storageAccount,
            $ownerNsid !== $connection->connection_key ? $ownerNsid : null,
        );
    }
}
