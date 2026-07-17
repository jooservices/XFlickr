<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Services;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Transfer\Enums\IntegrityAnomalyType;
use Modules\Transfer\Enums\IntegrityResolution;
use Modules\Transfer\Enums\IntegrityScanStatus;
use Modules\Transfer\Enums\StoredFileStatus;
use Modules\Transfer\Jobs\DownloadFileJob;
use Modules\Transfer\Jobs\RunIntegrityScanJob;
use Modules\Transfer\Models\IntegrityAnomaly;
use Modules\Transfer\Models\IntegrityScan;
use Modules\Transfer\Models\StoredFile;
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

    public function test_import_resolution_registers_a_server_recorded_orphan_file(): void
    {
        Storage::fake('local');
        $path = 'flickr/connection-a/photos/123456789_abc.jpg';
        Storage::disk('local')->put($path, 'orphan content');
        $scan = IntegrityScan::factory()->create(['disk' => 'local']);
        $anomaly = IntegrityAnomaly::factory()->create([
            'integrity_scan_id' => $scan->id,
            'type' => IntegrityAnomalyType::Orphaned,
            'local_path' => $path,
            'connection_key' => 'connection-a',
            'source_id' => '123456789',
        ]);

        $result = app(IntegrityScanService::class)->resolve($scan, IntegrityResolution::Import, [$anomaly->uuid]);

        $this->assertSame(['resolved_count' => 1, 'skipped_count' => 0], $result);
        $this->assertDatabaseHas('stored_files', [
            'source_id' => '123456789',
            'local_path' => $path,
            'status' => StoredFileStatus::Completed->value,
        ]);
        $this->assertNotNull($anomaly->fresh()->resolved_at);
    }

    public function test_redownload_resolution_marks_missing_file_pending_and_queues_recovery(): void
    {
        Queue::fake();
        $scan = IntegrityScan::factory()->create();
        $file = StoredFile::factory()->create([
            'source_owner' => 'connection-a',
            'status' => StoredFileStatus::Completed->value,
        ]);
        $anomaly = IntegrityAnomaly::factory()->create([
            'integrity_scan_id' => $scan->id,
            'type' => IntegrityAnomalyType::Missing,
            'stored_file_id' => $file->id,
        ]);

        $result = app(IntegrityScanService::class)->resolve($scan, IntegrityResolution::Redownload, [$anomaly->uuid]);

        $this->assertSame(['resolved_count' => 1, 'skipped_count' => 0], $result);
        $this->assertSame(StoredFileStatus::Pending->value, $file->fresh()->status);
        $this->assertNotNull($anomaly->fresh()->resolved_at);
        Queue::assertPushed(DownloadFileJob::class);
    }
}
