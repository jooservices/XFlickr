<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Repositories\Crawler\ConnectionQueryRepository;
use App\Services\Flickr\PhotoDownloadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * @deprecated Use PhotoDownloadService directly. Kept for in-flight queue jobs.
 */
final class DownloadContactsPhotosJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly string $connectionKey,
        private readonly string $contactNsid,
    ) {}

    public function handle(
        PhotoDownloadService $photoDownloadService,
        ConnectionQueryRepository $connections,
    ): void {
        $connection = $connections->findByConnectionKeyOrFail($this->connectionKey);

        $photoDownloadService->queueDownloads($connection, $this->contactNsid);
    }
}
