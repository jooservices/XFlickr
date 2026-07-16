<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Services;

use Illuminate\Support\Facades\Queue;
use Modules\Transfer\Enums\IntegrityScanStatus;
use Modules\Transfer\Jobs\RunIntegrityScanJob;
use Modules\Transfer\Services\IntegrityScanService;
use Modules\Transfer\Tests\TestCase;

final class IntegrityScanServiceTest extends TestCase
{
    public function test_start_scan_persists_pending_scan_and_dispatches_job(): void
    {
        Queue::fake();

        $scan = app(IntegrityScanService::class)->startScan();

        $this->assertSame(IntegrityScanStatus::Pending, $scan->status);
        $this->assertNotSame('', $scan->uuid);
        Queue::assertPushed(RunIntegrityScanJob::class);
    }
}
