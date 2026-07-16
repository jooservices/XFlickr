<?php

declare(strict_types=1);

namespace Modules\Transfer\Jobs;

use DateTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Transfer\Enums\TransferExecutionOutcome;
use Modules\Transfer\Services\FileDownloadService;
use Throwable;

final class DownloadFileJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 100;

    public int $maxExceptions = 3;

    public int $backoff = 30;

    public function __construct(
        private readonly string $sourceType,
        private readonly string $sourceId,
        private readonly string $sourceOwner,
        private readonly string $connectionKey,
        private readonly ?int $batchId = null,
    ) {
        $this->onQueue('xflickr-downloads');
    }

    public function retryUntil(): DateTime
    {
        return now()->addHours(6)->toDateTime();
    }

    public function handle(FileDownloadService $service): void
    {
        $outcome = $service->execute(
            $this->sourceType,
            $this->sourceId,
            $this->sourceOwner,
            $this->connectionKey,
            $this->batchId,
        );

        if ($outcome === TransferExecutionOutcome::Deferred) {
            $this->release(10);
        }
    }

    public function failed(Throwable $exception): void
    {
        app(FileDownloadService::class)->handleFailure(
            $this->sourceType,
            $this->sourceId,
            $this->batchId,
            $exception->getMessage(),
        );
    }
}
