<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Feature\Commands;

use Illuminate\Support\Facades\Queue;
use Modules\Transfer\Jobs\RunIntegrityScanJob;
use Modules\Transfer\Tests\TestCase;

final class ScanStorageIntegrityCommandTest extends TestCase
{
    public function test_command_queues_an_integrity_scan(): void
    {
        Queue::fake();

        $this->artisan('xflickr:transfer:integrity-scan')
            ->expectsOutputToContain('Integrity scan queued:')
            ->assertSuccessful();

        Queue::assertPushed(RunIntegrityScanJob::class);
    }
}
