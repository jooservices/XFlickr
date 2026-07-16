<?php

declare(strict_types=1);

namespace Modules\Transfer\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Transfer\Services\IntegrityScanRunner;
use Throwable;

final class RunIntegrityScanJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(private readonly int $scanId)
    {
        $this->onQueue('xflickr-downloads');
    }

    public function handle(IntegrityScanRunner $runner): void
    {
        $runner->run($this->scanId);
    }

    public function failed(Throwable $exception): void
    {
        app(IntegrityScanRunner::class)->fail($this->scanId, $exception);
    }
}
