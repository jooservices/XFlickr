<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Services;

use Illuminate\Support\Facades\Storage;
use Modules\Transfer\Enums\IntegrityAnomalyType;
use Modules\Transfer\Enums\IntegrityScanStatus;
use Modules\Transfer\Enums\StoredFileStatus;
use Modules\Transfer\Models\IntegrityScan;
use Modules\Transfer\Models\StoredFile;
use Modules\Transfer\Services\IntegrityScanRunner;
use Modules\Transfer\Tests\TestCase;

final class IntegrityScanRunnerTest extends TestCase
{
    public function test_run_records_orphaned_and_missing_files(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('flickr/account/photos/orphan_abc.jpg', 'orphan');
        $missing = StoredFile::factory()->create([
            'status' => StoredFileStatus::Completed->value,
            'variant' => 'original',
            'local_path' => 'flickr/account/photos/missing_abc.jpg',
        ]);
        $scan = IntegrityScan::factory()->create(['disk' => 'local']);

        app(IntegrityScanRunner::class)->run($scan->id);

        $scan->refresh();
        $this->assertSame(IntegrityScanStatus::Completed, $scan->status);
        $this->assertSame(1, $scan->orphaned_count);
        $this->assertSame(1, $scan->missing_count);
        $this->assertDatabaseHas('integrity_anomalies', [
            'integrity_scan_id' => $scan->id,
            'type' => IntegrityAnomalyType::Orphaned->value,
        ]);
        $this->assertDatabaseHas('integrity_anomalies', [
            'integrity_scan_id' => $scan->id,
            'type' => IntegrityAnomalyType::Missing->value,
            'stored_file_id' => $missing->id,
        ]);
    }

    public function test_fail_marks_scan_with_safe_error_message(): void
    {
        $scan = IntegrityScan::factory()->create();

        app(IntegrityScanRunner::class)->fail($scan->id, new \RuntimeException('secret local path'));

        $this->assertDatabaseHas('integrity_scans', [
            'id' => $scan->id,
            'status' => IntegrityScanStatus::Failed->value,
            'error_message' => 'Integrity scan failed. Review application logs for details.',
        ]);
    }
}
