<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PhotoTransferExecutionOutcome;
use App\Services\Flickr\PhotoUploadExecutionService;
use DateTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class UploadPhotoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $maxExceptions = 3;

    public int $backoff = 45;

    public function __construct(
        private readonly string $flickrPhotoId,
        private readonly int $storageAccountId,
        private readonly ?int $batchId = null,
        private readonly string $ownerNsid = '',
        private readonly int $batchItemCount = 1,
    ) {
        $this->onQueue('xflickr-uploads');
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("upload:{$this->storageAccountId}"))
                ->releaseAfter(30)
                ->expireAfter(300),
        ];
    }

    public function retryUntil(): DateTime
    {
        $estimatedSeconds = max(6 * 3600, $this->batchItemCount * 10);

        return now()->addSeconds($estimatedSeconds)->toDateTime();
    }

    public function handle(PhotoUploadExecutionService $service): void
    {
        $outcome = $service->execute(
            $this->flickrPhotoId,
            $this->storageAccountId,
            $this->batchId,
            $this->ownerNsid,
        );

        if ($outcome === PhotoTransferExecutionOutcome::Deferred) {
            $this->release(30);
        }
    }

    public function failed(Throwable $exception): void
    {
        app(PhotoUploadExecutionService::class)->handleFailure(
            $this->flickrPhotoId,
            $this->storageAccountId,
            $this->batchId,
            $exception->getMessage(),
        );
    }
}
