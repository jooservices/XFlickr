<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use Illuminate\Support\Facades\Cache;
use Modules\Storage\Enums\StoredFileStatus;
use Modules\Storage\Enums\TransferExecutionOutcome;
use Modules\Storage\Enums\TransferItemStatus;
use Modules\Storage\Models\StoredFile;
use Modules\Storage\Models\TransferBatch;
use Modules\Storage\Models\TransferItem;
use Modules\Storage\Services\FileDownloadService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class FileDownloadServiceTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_execute_returns_completed_for_already_completed_stored_file(): void
    {
        Cache::flush();

        $sourceId = fake()->numerify('#########');
        $batch = TransferBatch::factory()->create(['total_count' => 1]);

        StoredFile::factory()->create([
            'source_type' => 'flickr_photo',
            'source_id' => $sourceId,
            'variant' => 'original',
            'status' => StoredFileStatus::Completed->value,
        ]);

        TransferItem::factory()->create([
            'transfer_batch_id' => $batch->id,
            'source_id' => $sourceId,
        ]);

        $service = app(FileDownloadService::class);
        $result = $service->execute('flickr_photo', $sourceId, 'owner@N00', 'conn-1', $batch->id);

        $this->assertSame(TransferExecutionOutcome::Completed, $result);
    }

    public function test_handle_failure_marks_file_and_item_failed(): void
    {
        $sourceId = fake()->numerify('#########');
        $batch = TransferBatch::factory()->create(['total_count' => 1]);

        StoredFile::factory()->create([
            'source_type' => 'flickr_photo',
            'source_id' => $sourceId,
            'variant' => 'original',
            'status' => StoredFileStatus::Pending->value,
        ]);

        TransferItem::factory()->create([
            'transfer_batch_id' => $batch->id,
            'source_id' => $sourceId,
        ]);

        $service = app(FileDownloadService::class);
        $service->handleFailure('flickr_photo', $sourceId, $batch->id, 'Connection timed out');

        $storedFile = StoredFile::query()
            ->where('source_id', $sourceId)
            ->first();

        $this->assertSame(StoredFileStatus::Failed->value, $storedFile->status);

        $item = TransferItem::query()
            ->where('transfer_batch_id', $batch->id)
            ->where('source_id', $sourceId)
            ->first();

        $this->assertSame(TransferItemStatus::Failed->value, $item->status);
    }
}
