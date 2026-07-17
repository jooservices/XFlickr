<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Feature\Jobs;

use Illuminate\Support\Facades\Storage;
use Modules\Transfer\Enums\IntegrityScanStatus;
use Modules\Transfer\Jobs\RunIntegrityScanJob;
use Modules\Transfer\Models\IntegrityScan;
use Modules\Transfer\Services\IntegrityScanRunner;
use Modules\Transfer\Tests\TestCase;

final class RunIntegrityScanJobTest extends TestCase
{
    public function test_handle_runs_the_persisted_scan(): void
    {
        Storage::fake('local');
        $scan = IntegrityScan::factory()->create(['disk' => 'local']);

        (new RunIntegrityScanJob($scan->id))->handle(app(IntegrityScanRunner::class));

        $this->assertSame(IntegrityScanStatus::Completed, $scan->fresh()->status);
    }

    public function test_failed_marks_the_persisted_scan_as_failed_without_exposing_exception_details(): void
    {
        $scan = IntegrityScan::factory()->create();

        (new RunIntegrityScanJob($scan->id))->failed(new \RuntimeException('private filesystem path'));

        $this->assertDatabaseHas('integrity_scans', [
            'id' => $scan->id,
            'status' => IntegrityScanStatus::Failed->value,
            'error_message' => 'Integrity scan failed. Review application logs for details.',
        ]);
    }
}
