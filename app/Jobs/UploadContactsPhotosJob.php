<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Repositories\Crawler\ConnectionQueryRepository;
use App\Repositories\StorageAccountRepository;
use App\Services\Flickr\PhotoUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * @deprecated Use PhotoUploadService directly. Kept for in-flight queue jobs.
 */
final class UploadContactsPhotosJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly string $connectionKey,
        private readonly string $contactNsid,
        private readonly ?int $storageAccountId = null,
    ) {}

    public function handle(
        ConnectionQueryRepository $connections,
        StorageAccountRepository $storageAccounts,
        PhotoUploadService $photoUploadService,
    ): void {
        $connection = $connections->findByConnectionKeyOrFail($this->connectionKey);

        $storageAccount = $this->storageAccountId !== null
            ? $storageAccounts->findByIdOrFail($this->storageAccountId)
            : $storageAccounts->findDefault();

        if ($storageAccount === null) {
            return;
        }

        $photoUploadService->queueUploads($connection, $storageAccount, $this->contactNsid);
    }
}
