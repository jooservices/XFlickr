<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PhotoTransferExecutionOutcome;
use App\Services\Flickr\PhotoDownloadExecutionService;
use DateTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class DownloadPhotoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 100;

    public int $maxExceptions = 3;

    public int $backoff = 30;

    public function __construct(
        private readonly string $flickrPhotoId,
        private readonly string $ownerNsid,
        private readonly string $connectionKey,
        private readonly ?int $batchId = null,
    ) {
        $this->onQueue('xflickr-downloads');
    }

    public function retryUntil(): DateTime
    {
        return now()->addHours(6)->toDateTime();
    }

    public function handle(PhotoDownloadExecutionService $service): void
    {
        $outcome = $service->execute(
            $this->flickrPhotoId,
            $this->ownerNsid,
            $this->connectionKey,
            $this->batchId,
        );

        if ($outcome === PhotoTransferExecutionOutcome::Deferred) {
            $this->release(10);
        }
    }

    public function failed(Throwable $exception): void
    {
        app(PhotoDownloadExecutionService::class)->handleFailure(
            $this->flickrPhotoId,
            $this->batchId,
            $exception->getMessage(),
        );
    }
}
